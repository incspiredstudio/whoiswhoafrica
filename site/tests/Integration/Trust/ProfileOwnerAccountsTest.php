<?php
/**
 * Profile owner accounts on the public site (REQ-274, REQ-253, REQ-261): the role and its capabilities, never wp-admin, sign-in
 * through the guard's lockout with one generic error, the account pages (IDOR, CSRF, open redirect, rate limits), edits that wait
 * for review, "Approve and create account", the migration that withdraws the wp-admin My profiles capability, data export and
 * erasure, and the pages being noindex and never cached. Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Cache\PageCache\PageCachePolicy;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Forms\BotChallenge;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Forms\TransientStore;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Revisions\RevisionService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Trust\Account\AccountRoute;
use WhoIsWho\Core\Trust\Account\AccountService;
use WhoIsWho\Core\Trust\Account\OwnerAccess;
use WhoIsWho\Core\Trust\Account\OwnerAccounts;
use WhoIsWho\Core\Trust\Account\OwnerPrivacy;
use WhoIsWho\Core\Trust\Account\OwnerRole;
use WhoIsWho\Core\Trust\ClaimDecision;
use WhoIsWho\Core\Trust\ClaimRepository;
use WhoIsWho\Core\Trust\OwnerEdits;
use WhoIsWho\Core\Trust\ProfileManager;
use WhoIsWho\Tests\WpTestCase;

final class ProfileOwnerAccountsTest extends WpTestCase {

	private const PASSWORD = 'Correct-Horse-91!';

	/**
	 * Mail sent through the injected mailer.
	 *
	 * @var array<int, array{to: string, subject: string, text: string}>
	 */
	private array $sent = array();

	protected function setUp(): void {
		parent::setUp();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		if ( ! taxonomy_exists( Taxonomies::ORG_TYPE ) ) {
			( new Taxonomies() )->register();
		}
		foreach ( array( 'wiw_reviewer', 'wiw_editor', OwnerRole::ROLE ) as $role ) {
			remove_role( $role );
		}
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$this->sent = array();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * An owner account with a known password.
	 *
	 * @param string $email Address.
	 */
	private function owner( string $email = '' ): int {
		return (int) self::factory()->user->create(
			array(
				'role'       => OwnerRole::ROLE,
				'user_email' => '' !== $email ? $email : 'owner' . wp_generate_password( 6, false ) . '@example.test',
				'user_pass'  => self::PASSWORD,
			)
		);
	}

	/**
	 * A published, claimed profile owned by a user (0 for none).
	 *
	 * @param int    $owner Owner.
	 * @param string $name  Title.
	 */
	private function profile( int $owner, string $name = 'Owner Test Bank' ): int {
		$id      = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$machine = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		update_post_meta( $id, 'wiw_summary', 'Original summary' );
		( new StateRepository() )->update(
			$id,
			array(
				'claimed'          => 1,
				'assigned_user_id' => $owner > 0 ? $owner : null,
			)
		);
		return $id;
	}

	/**
	 * The route with an always-passing bot challenge and a fresh rate limiter.
	 *
	 * @param array<string, array<string, array{0: int, 1: int}>>|null $limits Limits.
	 */
	private function route( ?array $limits = null ): AccountRoute {
		$pass = new class() implements BotChallenge {
			/**
			 * Always passes.
			 *
			 * @param FormInput $input Input.
			 * @param string    $form  Form.
			 */
			public function verify( FormInput $input, string $form ): string {
				unset( $input, $form );
				return BotChallenge::OK;
			}
		};
		return new AccountRoute(
			new AccountService( $this->accounts() ),
			new OwnerEdits(),
			new RateLimiter( new TransientStore(), 'test-' . wp_generate_password( 8, false ), $limits ?? AccountRoute::LIMITS ),
			$pass
		);
	}

	/**
	 * Owner accounts with a capturing mailer.
	 */
	private function accounts(): OwnerAccounts {
		return new OwnerAccounts(
			null,
			function ( string $to, string $subject, string $text ): bool {
				$this->sent[] = compact( 'to', 'subject', 'text' );
				return true;
			}
		);
	}

	/**
	 * Run one request through the route.
	 *
	 * @param string               $view    View.
	 * @param string               $method  Method.
	 * @param array<string, mixed> $values  Body and query values.
	 * @param int                  $profile Profile id.
	 * @param AccountRoute|null    $route   Route.
	 * @return array<string, mixed>
	 */
	private function call( string $view, string $method = 'GET', array $values = array(), int $profile = 0, ?AccountRoute $route = null ): array {
		$route = $route ?? $this->route();
		return $route->respond( $view, $profile, $method, new Request( $values ), new FormInput( $values ), '203.0.113.9', get_current_user_id() );
	}

	/**
	 * A sign-in body with a nonce made while signed out.
	 *
	 * @param string $login    Email.
	 * @param string $password Password.
	 * @return array<string, string>
	 */
	private function signin_body( string $login, string $password ): array {
		wp_set_current_user( 0 );
		return array(
			'log'                     => $login,
			'pwd'                     => $password,
			AccountRoute::NONCE_FIELD => wp_create_nonce( 'wiw_account_signin' ),
			'redirect_to'             => home_url( '/account/' ),
		);
	}

	/**
	 * Audit rows for an action.
	 *
	 * @param string $action Action.
	 * @return array<int, array<string, mixed>>
	 */
	private function audit( string $action ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s ORDER BY id ASC", $action ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	public function test_REQ_274_owner_role_has_only_read_and_own_profiles_and_no_admin_capability(): void {
		$role = get_role( OwnerRole::ROLE );
		$this->assertNotNull( $role, 'the sync creates the Profile owner role' );
		$granted = array_keys( array_filter( $role->capabilities ) );
		sort( $granted );
		$this->assertSame( array( 'read', 'wiw_own_profiles' ), $granted );
		$owner = $this->owner();
		foreach ( array( 'edit_posts', 'upload_files', 'read_private_wiw_profiles', 'edit_wiw_profiles', 'wiw_review_records', 'manage_options', 'list_users' ) as $cap ) {
			$this->assertFalse( user_can( $owner, $cap ), "an owner has no $cap" );
		}
		$this->assertFalse( (bool) get_option( 'users_can_register' ), 'public registration stays off' );
		$this->assertNotContains( OwnerRole::ROLE, ( new CapabilityCatalogue() )->staff_roles(), 'the owner role is not a staff role' );
	}

	public function test_REQ_274_owner_is_sent_from_wp_admin_to_the_account_and_never_sees_the_admin_bar(): void {
		$owner = $this->owner();
		$staff = (int) self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$this->assertSame( home_url( '/account/' ), OwnerAccess::admin_redirect( $owner, false ) );
		$this->assertSame( '', OwnerAccess::admin_redirect( $staff, false ), 'staff stay in wp-admin' );
		$this->assertSame( '', OwnerAccess::admin_redirect( $owner, true ), 'admin-ajax.php is left to its own handlers' );

		wp_set_current_user( $owner );
		$this->assertFalse( apply_filters( 'show_admin_bar', true ), 'no admin bar for an owner' );
		wp_set_current_user( $staff );
		$this->assertTrue( apply_filters( 'show_admin_bar', true ), 'staff keep it' );

		$this->assertSame( home_url( '/account/' ), apply_filters( 'login_redirect', admin_url(), admin_url(), get_userdata( $owner ) ), 'after wp-login.php (the two-factor step) an owner lands on /account/' );
		$this->assertSame( admin_url(), apply_filters( 'login_redirect', admin_url(), admin_url(), get_userdata( $staff ) ) );
	}

	public function test_REQ_274_sign_in_by_email_goes_to_the_account_and_staff_go_to_wp_admin(): void {
		$email = 'signin' . wp_generate_password( 5, false ) . '@example.test';
		$owner = $this->owner( $email );
		$state = $this->call( 'home', 'POST', $this->signin_body( $email, self::PASSWORD ) );
		$this->assertSame( home_url( '/account/' ), $state['redirect'] );
		$this->assertSame( $owner, get_current_user_id() );

		$state = $this->call( 'home' );
		$this->assertSame( 'dashboard', $state['view'] );

		$body                = $this->signin_body( $email, self::PASSWORD );
		$body['redirect_to'] = 'https://evil.example/account/';
		$this->assertSame( home_url( '/account/' ), $this->call( 'home', 'POST', $body )['redirect'], 'an outside redirect_to is ignored' );
	}

	public function test_REQ_274_sign_in_errors_are_generic_and_the_guard_lockout_applies(): void {
		$email = 'lock' . wp_generate_password( 5, false ) . '@example.test';
		$this->owner( $email );
		$unknown = $this->call( 'home', 'POST', $this->signin_body( 'nobody' . wp_generate_password( 5, false ) . '@example.test', self::PASSWORD ) );
		$wrong   = $this->call( 'home', 'POST', $this->signin_body( $email, 'Wrong-Password-1!' ) );
		$this->assertSame( 'signin', $unknown['view'] );
		$this->assertSame( $unknown['errors'], $wrong['errors'], 'an unknown address and a wrong password read the same' );
		$this->assertSame( array( 'Sign in failed. Check your details and try again.' ), $wrong['errors'] );
		$this->assertSame( '', $wrong['data']['login'] === $email ? '' : 'refilled', 'the address is refilled, never the password' );

		for ( $i = 1; $i < 5; $i++ ) {
			$this->call( 'home', 'POST', $this->signin_body( $email, 'Wrong-Password-1!' ) );
		}
		$locked = $this->call( 'home', 'POST', $this->signin_body( $email, self::PASSWORD ) );
		$this->assertSame( '', $locked['redirect'], 'the right password is refused while the account is locked' );
		$this->assertSame( $wrong['errors'], $locked['errors'], 'with the same generic message' );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_REQ_274_sign_in_and_lost_password_are_rate_limited_and_need_a_nonce(): void {
		$limits = array(
			'account_signin' => array(
				'ip'      => array( 2, 900 ),
				'subject' => array( 2, 900 ),
			),
			'account_lost'   => array(
				'ip'      => array( 1, 3600 ),
				'subject' => array( 1, 3600 ),
			),
		);
		$route  = $this->route( $limits );
		$body   = $this->signin_body( 'rate@example.test', 'x' );
		$this->call( 'home', 'POST', $body, 0, $route );
		$this->call( 'home', 'POST', $body, 0, $route );
		$this->assertSame( 429, $this->call( 'home', 'POST', $body, 0, $route )['status'] );

		$lost = array(
			'user_email'              => 'rate@example.test',
			AccountRoute::NONCE_FIELD => wp_create_nonce( 'wiw_account_lost' ),
		);
		$this->assertSame( 'lost_sent', $this->call( 'lost', 'POST', $lost, 0, $route )['view'] );
		$this->assertSame( 429, $this->call( 'lost', 'POST', $lost, 0, $route )['status'] );

		$no_nonce = array(
			'log' => 'rate2@example.test',
			'pwd' => 'x',
		);
		$this->assertSame( 400, $this->call( 'home', 'POST', $no_nonce )['status'], 'a sign-in without the nonce is refused' );
	}

	public function test_REQ_274_lost_password_reads_the_same_for_any_address_and_mails_only_owners(): void {
		$email = 'lost' . wp_generate_password( 5, false ) . '@example.test';
		$owner = $this->owner( $email );
		$staff = 'staff' . wp_generate_password( 5, false ) . '@example.test';
		self::factory()->user->create(
			array(
				'role'       => 'wiw_editor',
				'user_email' => $staff,
			)
		);
		$answers = array();
		foreach ( array( $email, $staff, 'nobody@example.test' ) as $address ) {
			$state     = $this->call(
				'lost',
				'POST',
				array(
					'user_email'              => $address,
					AccountRoute::NONCE_FIELD => wp_create_nonce( 'wiw_account_lost' ),
				)
			);
			$answers[] = $state['view'] . '|' . $state['notice'];
		}
		$this->assertCount( 1, array_unique( $answers ), 'the same answer for an owner, a staff member and an unknown address' );
		$this->assertCount( 1, $this->sent, 'only the owner gets an email' );
		$this->assertSame( $email, $this->sent[0]['to'] );
		$this->assertSame( 1, preg_match( '~/account/lost-password/\?key=([^&\s]+)&(?:amp;)?id=' . $owner . '~', $this->sent[0]['text'], $m ), 'the link goes to the public reset page' );

		$key = rawurldecode( $m[1] );
		$this->assertSame(
			'reset',
			$this->call(
				'lost',
				'GET',
				array(
					'key' => $key,
					'id'  => $owner,
				)
			)['view']
		);
		$weak = $this->call(
			'lost',
			'POST',
			array(
				'key'                     => $key,
				'id'                      => $owner,
				'pass1'                   => 'short',
				'pass2'                   => 'short',
				AccountRoute::NONCE_FIELD => wp_create_nonce( 'wiw_account_reset' ),
			)
		);
		$this->assertSame( 422, $weak['status'], 'the site password rules apply' );
		$done = $this->call(
			'lost',
			'POST',
			array(
				'key'                     => $key,
				'id'                      => $owner,
				'pass1'                   => 'New-Strong-Pass-77!',
				'pass2'                   => 'New-Strong-Pass-77!',
				AccountRoute::NONCE_FIELD => wp_create_nonce( 'wiw_account_reset' ),
			)
		);
		$this->assertSame( add_query_arg( 'reset', '1', home_url( '/account/' ) ), $done['redirect'] );
		$this->assertTrue( wp_check_password( 'New-Strong-Pass-77!', get_userdata( $owner )->user_pass, $owner ) );
		$this->assertSame(
			'reset_expired',
			$this->call(
				'lost',
				'GET',
				array(
					'key' => $key,
					'id'  => $owner,
				)
			)['view'],
			'a link works once'
		);
		$this->assertNotEmpty( $this->audit( 'owner.password_reset' ) );
	}

	public function test_REQ_274_an_owner_can_open_only_their_own_profiles(): void {
		$owner = $this->owner();
		$mine  = $this->profile( $owner, 'Mine Owner Bank' );
		$other = $this->profile( $this->owner(), 'Other Owner Bank' );
		wp_set_current_user( $owner );
		$this->assertSame( 'edit', $this->call( 'edit', 'GET', array(), $mine )['view'] );
		$refused = $this->call( 'edit', 'GET', array(), $other );
		$this->assertSame( 404, $refused['status'], 'another owner\'s profile answers 404' );
		$this->assertSame( 'not_found', $refused['view'] );
		$post = $this->call(
			'edit',
			'POST',
			array(
				'summary'                 => 'Hijack.',
				AccountRoute::NONCE_FIELD => wp_create_nonce( 'wiw_account_edit' ),
			),
			$other
		);
		$this->assertSame( 404, $post['status'] );
		$this->assertSame( array(), ( new RevisionService() )->pending( $other ), 'nothing was filed for the other profile' );

		$dashboard = $this->call( 'home' );
		$this->assertSame( array( 'Mine Owner Bank' ), array_column( $dashboard['data']['profiles'], 'name' ) );

		wp_set_current_user( 0 );
		$this->assertSame( home_url( '/account/' ), $this->call( 'edit', 'GET', array(), $mine )['redirect'], 'signed out goes to sign in' );
	}

	public function test_REQ_274_an_edit_waits_for_review_blocks_a_second_and_goes_live_on_approval(): void {
		$owner = $this->owner();
		$id    = $this->profile( $owner, 'Review Owner Bank' );
		wp_set_current_user( $owner );

		$csrf = $this->call( 'edit', 'POST', array( 'summary' => 'No nonce.' ), $id );
		$this->assertSame( 422, $csrf['status'], 'an edit without the nonce is refused' );
		$this->assertSame( array(), ( new RevisionService() )->pending( $id ) );

		$state = $this->call(
			'edit',
			'POST',
			array(
				'summary'                 => 'A clearer summary.',
				AccountRoute::NONCE_FIELD => wp_create_nonce( 'wiw_account_edit' ),
			),
			$id
		);
		$this->assertSame( add_query_arg( 'sent', '1', home_url( '/account/profiles/' . $id . '/edit/' ) ), $state['redirect'] );
		$this->assertSame( 'Original summary', get_post_meta( $id, 'wiw_summary', true ), 'the live profile does not change' );
		$pending = ( new RevisionService() )->pending( $id );
		$this->assertCount( 1, $pending );

		$view = $this->call( 'edit', 'GET', array( 'sent' => '1' ), $id );
		$this->assertTrue( $view['data']['pending'], 'the page shows Waiting for review' );
		$this->assertSame( 'Changes waiting for review', $this->call( 'home' )['data']['profiles'][0]['status'] );

		$second = $this->call(
			'edit',
			'POST',
			array(
				'summary'                 => 'Another change.',
				AccountRoute::NONCE_FIELD => wp_create_nonce( 'wiw_account_edit' ),
			),
			$id
		);
		$this->assertSame( array( 'Your last change is still waiting for review.' ), $second['errors'] );
		$this->assertCount( 1, ( new RevisionService() )->pending( $id ) );

		$reviewer = (int) self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		$mailed   = array();
		( new RevisionService(
			null,
			static function ( int $user_id, string $outcome ) use ( &$mailed ): void {
				$mailed[] = array( $user_id, $outcome );
			}
		) )->approve( (int) $pending[0]['id'], $reviewer );
		$this->assertSame( 'A clearer summary.', get_post_meta( $id, 'wiw_summary', true ), 'approved: now live' );
		$this->assertSame( array( array( $owner, 'approved' ) ), $mailed, 'the owner is told the outcome' );
		$this->assertSame( 'Live', $this->call( 'home' )['data']['profiles'][0]['status'] );
	}

	public function test_REQ_274_account_details_change_the_name_and_password_and_are_audited(): void {
		$owner = $this->owner();
		wp_set_current_user( $owner );
		$wrong = $this->call(
			'details',
			'POST',
			array(
				'display_name'            => 'Ada Owner',
				'current_password'        => 'not-it',
				'pass1'                   => 'Another-Strong-1!',
				'pass2'                   => 'Another-Strong-1!',
				AccountRoute::NONCE_FIELD => wp_create_nonce( 'wiw_account_details' ),
			)
		);
		$this->assertSame( 422, $wrong['status'], 'the current password is required' );
		$ok = $this->call(
			'details',
			'POST',
			array(
				'display_name'            => 'Ada Owner',
				'current_password'        => self::PASSWORD,
				'pass1'                   => 'Another-Strong-1!',
				'pass2'                   => 'Another-Strong-1!',
				AccountRoute::NONCE_FIELD => wp_create_nonce( 'wiw_account_details' ),
			)
		);
		$this->assertSame( add_query_arg( 'saved', '1', home_url( '/account/details/' ) ), $ok['redirect'] );
		$this->assertSame( 'Ada Owner', get_userdata( $owner )->display_name );
		$this->assertTrue( wp_check_password( 'Another-Strong-1!', get_userdata( $owner )->user_pass, $owner ) );
		$rows = $this->audit( 'owner.details_changed' );
		$this->assertNotEmpty( $rows );
		$last = end( $rows );
		$this->assertStringContainsString( 'password', (string) $last['new_value'] );
		$this->assertStringNotContainsString( 'Another-Strong', (string) $last['new_value'], 'no value is audited' );
	}

	public function test_REQ_274_sign_out_needs_the_nonce(): void {
		$owner = $this->owner();
		wp_set_current_user( $owner );
		$this->assertSame( home_url( '/account/' ), $this->call( 'signout' )['redirect'] );
		$this->assertSame( $owner, get_current_user_id(), 'without the nonce nothing happens' );
		$this->assertSame( add_query_arg( 'signed_out', '1', home_url( '/account/' ) ), $this->call( 'signout', 'GET', array( '_wpnonce' => wp_create_nonce( 'wiw_account_signout' ) ) )['redirect'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_REQ_274_redirect_to_is_honoured_only_under_account_on_this_site(): void {
		$base = home_url( '/account/' );
		$this->assertSame( $base . 'details/', AccountRoute::safe_redirect( $base . 'details/' ) );
		foreach ( array( 'https://evil.example/account/', '//evil.example/account/', admin_url(), $base . '../wp-admin/', $base . "\n" ) as $bad ) {
			$this->assertSame( $base, AccountRoute::safe_redirect( $bad ), $bad );
		}
	}

	public function test_REQ_274_approve_and_create_account_creates_or_reuses_the_owner_emails_and_audits(): void {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . ClaimRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$reviewer = (int) self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		wp_set_current_user( $reviewer );
		$claims   = new ClaimRepository();
		$decision = new ClaimDecision( $claims, new StateRepository(), \WhoIsWho\Core\Platform\Mail\Notifier::for_site() );
		$email    = 'claimant' . wp_generate_password( 5, false ) . '@example.test';
		$claim    = static function ( int $profile_id, string $address ) use ( $claims ) {
			return $claims->create(
				array(
					'profile_id'   => $profile_id,
					'relationship' => 'self',
					'name'         => 'Ada Obi',
					'work_email'   => $address,
					'evidence'     => 'Listed on the registry.',
					'consent'      => true,
				)
			);
		};
		$first    = $this->profile( 0, 'First Claimed Bank' );
		( new StateRepository() )->update( $first, array( 'claimed' => 0 ) );
		$result = $decision->approve_with_account( $claim( $first, $email )->id, $reviewer, $this->accounts() );
		$this->assertTrue( $result['created'] );
		$this->assertTrue( $result['emailed'] );
		$owner = get_user_by( 'email', $email );
		$this->assertInstanceOf( \WP_User::class, $owner );
		$this->assertSame( array( OwnerRole::ROLE ), array_values( $owner->roles ) );
		$this->assertStringNotContainsString( 'claimant', $owner->user_login, 'the login holds no part of the address' );
		$this->assertSame( (int) $owner->ID, ( new ProfileManager() )->assigned( $first ) );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'Set your password to manage your profile', $this->sent[0]['subject'] );
		$this->assertStringContainsString( '/account/lost-password/?key=', $this->sent[0]['text'] );

		$second = $this->profile( 0, 'Second Claimed Bank' );
		( new StateRepository() )->update( $second, array( 'claimed' => 0 ) );
		$again = $decision->approve_with_account( $claim( $second, $email )->id, $reviewer, $this->accounts() );
		$this->assertFalse( $again['created'], 'the existing owner account is reused' );
		$this->assertSame( (int) $owner->ID, $again['owner_id'] );
		$this->assertSame( array( $first, $second ), ( new ProfileManager() )->owned( (int) $owner->ID ), 'one owner, several profiles' );

		$staff_email = 'staffer' . wp_generate_password( 5, false ) . '@example.test';
		self::factory()->user->create(
			array(
				'role'       => 'wiw_reviewer',
				'user_email' => $staff_email,
			)
		);
		$third = $this->profile( 0, 'Third Claimed Bank' );
		( new StateRepository() )->update( $third, array( 'claimed' => 0 ) );
		$refused = $claim( $third, $staff_email );
		try {
			$decision->approve_with_account( $refused->id, $reviewer, $this->accounts() );
			$this->fail( 'A staff address must not become an owner account.' );
		} catch ( \DomainException $e ) {
			$this->assertStringContainsString( 'staff account', $e->getMessage() );
		}
		$this->assertTrue( $claims->find( $refused->id )->is_open(), 'the claim stays open' );

		$this->assertCount( 1, $this->audit( 'owner.account_created' ) );
		$this->assertCount( 1, $this->audit( 'owner.account_reused' ) );
		$this->assertCount( 2, $this->audit( 'claim.approve_with_account' ) );
		foreach ( array_merge( $this->audit( 'owner.account_created' ), $this->audit( 'claim.approve_with_account' ) ) as $row ) {
			$this->assertStringNotContainsString( $email, (string) wp_json_encode( $row ), 'no address in the audit log' );
		}
	}

	public function test_REQ_274_the_migration_withdraws_the_wp_admin_my_profiles_capability(): void {
		$admin = get_role( 'administrator' );
		$admin->add_cap( 'wiw_edit_assigned_profiles' );
		$user = (int) self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		get_userdata( $user )->add_cap( 'wiw_edit_assigned_profiles' );
		$migration = require dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations/2026112105_owner_accounts.php';
		$this->assertSame( '2026112105', $migration->version() );
		$migration->up();
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'wiw_edit_assigned_profiles' ) );
		$this->assertFalse( user_can( $user, 'wiw_edit_assigned_profiles' ) );
		$this->assertNotNull( get_role( OwnerRole::ROLE ) );
		$migration->up();
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'wiw_edit_assigned_profiles' ), 'safe to run again' );
	}

	public function test_REQ_274_privacy_export_and_erasure_cover_owner_accounts_only(): void {
		$email    = 'privacy' . wp_generate_password( 5, false ) . '@example.test';
		$owner    = $this->owner( $email );
		$id       = $this->profile( $owner, 'Privacy Owner Bank' );
		$staff    = 'privstaff' . wp_generate_password( 5, false ) . '@example.test';
		$staff_id = (int) self::factory()->user->create(
			array(
				'role'       => 'wiw_editor',
				'user_email' => $staff,
			)
		);
		$this->assertArrayHasKey( 'wiw-owner-accounts', apply_filters( 'wp_privacy_personal_data_exporters', array() ) );
		$this->assertArrayHasKey( 'wiw-owner-accounts', apply_filters( 'wp_privacy_personal_data_erasers', array() ) );

		$handler = new OwnerPrivacy();
		$export  = $handler->export( $email );
		$this->assertCount( 1, $export );
		$this->assertStringContainsString( 'Privacy Owner Bank', (string) wp_json_encode( $export ) );
		$this->assertSame( array(), $handler->export( $staff ) );

		$this->assertSame( 0, $handler->erase( $staff ), 'staff accounts are never erased here' );
		$this->assertInstanceOf( \WP_User::class, get_userdata( $staff_id ) );
		$this->assertSame( 1, $handler->erase( $email ) );
		$this->assertFalse( get_userdata( $owner ), 'the owner account is deleted' );
		$this->assertNull( ( new ProfileManager() )->assigned( $id ), 'the profile loses its owner' );
		$this->assertNotFalse( get_post( $id ), 'the profile itself stays' );
	}

	public function test_REQ_274_account_pages_are_noindex_never_cached_and_not_in_the_sitemap(): void {
		set_query_var( AccountRoute::QUERY_VAR, 'home' );
		$robots = AccountRoute::robots( array( 'max-image-preview' => 'large' ) );
		set_query_var( AccountRoute::QUERY_VAR, '' );
		$this->assertTrue( $robots['noindex'] );
		$this->assertTrue( $robots['nofollow'] );
		$policy = new PageCachePolicy();
		foreach ( array( '/account/', '/account/details/', '/account/lost-password/' ) as $path ) {
			$this->assertFalse( $policy->decide( array( 'path' => $path ) )['cache'], $path );
		}
		$this->assertFalse( apply_filters( 'wp_sitemaps_enabled', true ), 'core sitemaps are off, so no account address is listed' );
	}
}
