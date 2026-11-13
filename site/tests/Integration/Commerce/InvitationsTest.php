<?php
/**
 * Customer invitations and the profile intake behind them (REQ-013, REQ-014, REQ-233, REQ-141, REQ-122). Needs the WordPress test
 * suite and the real tables; links live in memory and the audit log is a probe table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\Invitations\ImageCheck;
use WhoIsWho\Core\Commerce\Invitations\InvitationRoute;
use WhoIsWho\Core\Commerce\Invitations\Invitations;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Forms\BotChallenge;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Review\Admin\ReviewQueue;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class InvitationsTest extends TestCase {

	private const PROBE = 'wiw_invitations_audit_probe';

	/**
	 * Messages sent.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $sent = array();

	/**
	 * Profiles to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Files to remove.
	 *
	 * @var string[]
	 */
	private array $files = array();

	/**
	 * Runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $runner;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		$wpdb->query( 'TRUNCATE TABLE ' . CustomerRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$this->sent = array();
		wp_set_current_user( 0 );
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		$this->customer_profiles(); // Profiles left by an earlier run.
		$this->remove_profiles();
	}

	/**
	 * Delete the profiles made so far.
	 */
	private function remove_profiles(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
	}

	protected function tearDown(): void {
		global $wpdb;
		$this->customer_profiles(); // Track every profile an invitation created, then remove it.
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- a temporary test file.
			}
		}
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * The service, run as an Administrator.
	 */
	private function invitations(): Invitations {
		$admin = wp_insert_user(
			array(
				'user_login' => 'inv_' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'administrator',
			)
		);
		$this->assertIsInt( $admin );
		wp_set_current_user( $admin );
		$queue         = new MemoryQueue();
		$options       = new \ArrayObject();
		$this->runner  = new JobRunner(
			$queue,
			static fn (): int => time(),
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$queue->runner = $this->runner;
		$log           = new class() implements EmailLogStore {
			/**
			 * Ignore.
			 *
			 * @param string $message_id    Id.
			 * @param string $event         Event.
			 * @param string $recipient_ref Ref.
			 * @param string $type          Type.
			 * @param string $result        Result.
			 */
			public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void {
				unset( $message_id, $event, $recipient_ref, $type, $result );
			}
		};
		$links         = new SignedLinks( new MemoryLinkStore() );
		$notifier      = new Notifier(
			$this->runner,
			$log,
			static fn (): array => array( 'inbox@example.org' ),
			function ( string $to, string $subject, string $text, string $html ): bool {
				unset( $html );
				$this->sent[] = compact( 'to', 'subject', 'text' );
				return true;
			},
			'hash-key',
			$links
		);
		$audit         = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-13 10:00:00', static fn (): int => $admin );
		return new Invitations( $links, $notifier, $audit );
	}

	/**
	 * A customer and an issued link: the token, and the service.
	 *
	 * @return array{0: Invitations, 1: string, 2: int}
	 */
	private function issued(): array {
		$service  = $this->invitations();
		$customer = ( new CustomerRepository() )->insert( array( 'name' => 'Obi Holdings' ), null, new \DateTimeImmutable( '2026-11-12 10:00:00', new \DateTimeZone( 'UTC' ) ) );
		$url      = $service->issue( $customer, 'ada@example.test' );
		return array( $service, basename( $url ), $customer );
	}

	/**
	 * Good form values.
	 *
	 * @param array<string, string> $over Overrides.
	 * @return array<string, string>
	 */
	private function values( array $over = array() ): array {
		return array_merge(
			array(
				'name'    => 'Obi Holdings',
				'type'    => 'organisation',
				'summary' => 'A regional logistics company serving West Africa.',
				'website' => 'https://obi.example.org',
			),
			$over
		);
	}

	/**
	 * Profiles created by invitations (origin customer).
	 *
	 * @return int[]
	 */
	private function customer_profiles(): array {
		global $wpdb;
		$ids         = array_map( 'intval', (array) $wpdb->get_col( 'SELECT profile_id FROM ' . StateRepository::table() . " WHERE origin = 'customer' AND source = 'invitation'" ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		$this->posts = array_merge( $this->posts, $ids );
		return $ids;
	}

	/**
	 * A small image file.
	 *
	 * @param string $name Name to give it.
	 * @return array{name: string, tmp_name: string, size: int, error: int}
	 */
	private function png( string $name = 'logo.png' ): array {
		$path = (string) tempnam( sys_get_temp_dir(), 'wiwimg' );
		// A 1x1 PNG.
		file_put_contents( $path, (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- a tiny test image.
		$this->files[] = $path;
		return array(
			'name'     => $name,
			'tmp_name' => $path,
			'size'     => (int) filesize( $path ),
			'error'    => UPLOAD_ERR_OK,
		);
	}

	/**
	 * A route with a stub bot challenge that answers the given verdict.
	 *
	 * @param Invitations $service Invitations.
	 * @param string      $verdict Challenge verdict.
	 */
	private function route( Invitations $service, string $verdict = BotChallenge::OK ): InvitationRoute {
		$challenge = new class( $verdict ) implements BotChallenge {
			/**
			 * Verdict.
			 *
			 * @var string
			 */
			private string $verdict;

			/**
			 * Constructor.
			 *
			 * @param string $verdict Verdict.
			 */
			public function __construct( string $verdict ) {
				$this->verdict = $verdict;
			}

			/**
			 * Verify.
			 *
			 * @param FormInput $input Input.
			 * @param string    $form  Form.
			 */
			public function verify( FormInput $input, string $form ): string {
				return 'invitation' === $form ? $this->verdict : 'wrong_form';
			}
		};
		return new InvitationRoute( $service, new RateLimiter( new MemoryStore(), 'secret', InvitationRoute::LIMITS + RateLimiter::LIMITS ), $challenge );
	}

	public function test_REQ_081_a_post_without_a_passing_challenge_is_refused_and_does_not_reveal_the_link(): void {
		list( $service, $token ) = $this->issued();
		$route                   = $this->route( $service, 'honeypot' );
		$post                    = new Request(
			array(
				'submit'  => 'send',
				'name'    => 'Bot Ltd',
				'type'    => 'organisation',
				'summary' => 'x',
			)
		);
		$good                    = $route->respond( 'POST', $token, $post, '203.0.113.5', new FormInput( array() ) );
		$unused                  = $route->respond( 'POST', 'abcdefghijklmnopqrstuvwxyz', $post, '203.0.113.5', new FormInput( array() ) );
		$this->assertSame( 'form', $good['state'] );
		$this->assertSame( array( 'form' => 'challenge' ), $good['errors'] );
		$this->assertSame( $good, $unused, 'A valid link and an unknown link give the same refusal.' );
		$this->assertSame( array(), $this->customer_profiles(), 'Nothing is created.' );
		$this->assertSame( array(), $service->draft( $token ), 'No draft is saved either.' );
		$this->assertTrue( $service->usable( $token ), 'The link is not used up by a refused post.' );
	}

	public function test_REQ_081_a_passing_challenge_lets_the_post_through(): void {
		list( $service, $token ) = $this->issued();
		$saved                   = $this->route( $service )->respond(
			'POST',
			$token,
			new Request(
				array(
					'submit' => 'draft',
					'name'   => 'Ok Ltd',
				)
			),
			'203.0.113.5',
			new FormInput( array() )
		);
		$this->assertSame( 'saved', $saved['state'] );
	}

	public function test_REQ_233_invitation_link_second_use_shows_expired(): void {
		list( $service, $token ) = $this->issued();
		$this->assertTrue( $service->usable( $token ) );
		$first = $service->submit( $token, $this->values() );
		$this->assertSame( 'done', $first['state'] );
		$this->assertCount( 1, $this->customer_profiles() );

		$second = $service->submit( $token, $this->values( array( 'name' => 'Second Try Ltd' ) ) );
		$this->assertSame( 'expired', $second['state'], 'A second use shows Link expired or already used.' );
		$this->assertCount( 1, $this->customer_profiles(), 'A second use creates nothing.' );

		$route = $this->route( $service );
		$this->assertSame( 'expired', $route->respond( 'GET', $token, new Request( array() ), '203.0.113.5', new FormInput( array() ) )['state'] );
		$this->assertSame( 'expired', $route->respond( 'GET', 'abcdefghijklmnopqrstuvwxyz', new Request( array() ), '203.0.113.5', new FormInput( array() ) )['state'], 'An unknown link looks the same.' );
	}

	public function test_REQ_233_issuing_emails_the_link_and_audits_without_personal_data(): void {
		global $wpdb;
		list( $service, $token, $customer ) = $this->issued();
		$this->runner->run_due( 5 );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'ada@example.test', $this->sent[0]['to'] );
		$this->assertStringContainsString( '/invite/' . $token, $this->sent[0]['text'] );

		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE, ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->assertCount( 1, $rows );
		$this->assertSame( 'invitation.issue', $rows[0]['action'] );
		$this->assertSame( (string) $customer, $rows[0]['subject_id'] );
		$dump = (string) wp_json_encode( $rows );
		foreach ( array( 'ada@example.test', 'Obi Holdings', $token ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $dump, 'The audit row holds ids only.' );
		}

		wp_set_current_user( 0 );
		$this->expectException( \DomainException::class );
		$service->issue( $customer, 'ada@example.test' );
	}

	public function test_REQ_141_intake_blocks_contact_details_age_and_sensitive_words(): void {
		list( $service, $token ) = $this->issued();
		$cases                   = array(
			'phone'     => 'Call us on +234 803 555 0142 for details.',
			'email'     => 'Write to ada@example.test for details.',
			'age'       => 'Our founder is 34 years old.',
			'sensitive' => 'The founder was diagnosed with cancer last year.',
		);
		foreach ( $cases as $label => $text ) {
			$result = $service->submit( $token, $this->values( array( 'summary' => $text ) ) );
			$this->assertSame( 'invalid', $result['state'], $label . ' is blocked.' );
			$this->assertArrayHasKey( 'summary', $result['errors'], $label );
		}
		$this->assertSame( 'invalid', $service->submit( $token, $this->values( array( 'name' => 'Ada, aged 34' ) ) )['state'], 'The name is scanned too.' );
		$this->assertSame( array(), $this->customer_profiles(), 'Nothing was created.' );
		$this->assertTrue( $service->usable( $token ), 'A blocked submission does not use the link up.' );
		$this->assertSame( 'done', $service->submit( $token, $this->values() )['state'] );
	}

	public function test_REQ_014_customer_profile_not_public_until_approved(): void {
		list( $service, $token ) = $this->issued();
		$this->assertSame( 'done', $service->submit( $token, $this->values() )['state'] );
		$ids = $this->customer_profiles();
		$this->assertCount( 1, $ids );
		$state = ( new StateRepository() )->get( $ids[0] );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, $state['pub_state'] );
		$this->assertSame( 'customer', $state['origin'] );
		$this->assertNull( PublicProfile::find_by_id( $ids[0] ), 'Nothing is public before a reviewer approves it.' );
		$this->assertNotSame( 'publish', get_post_status( $ids[0] ) );
		$this->assertSame( 'A regional logistics company serving West Africa.', get_post_meta( $ids[0], 'wiw_summary', true ) );

		wp_set_current_user(
			(int) wp_insert_user(
				array(
					'user_login' => 'rev_' . wp_generate_password( 6, false ),
					'user_pass'  => wp_generate_password(),
					'role'       => 'wiw_reviewer',
				)
			)
		);
		$kinds = array();
		foreach ( ( new ReviewQueue() )->items( 1 )['items'] as $item ) {
			$kinds[ $item['profile_id'] ] = $item['kind'];
		}
		$this->assertSame( ReviewQueue::KIND_SUBMISSION, $kinds[ $ids[0] ] ?? '', 'The submission waits in the review queue.' );
	}

	public function test_REQ_013_intake_enforces_image_type_and_size(): void {
		$good = $this->png();
		$this->assertNull( ImageCheck::check( $good ) );
		$this->assertSame( 'too_big', ImageCheck::check( $good, 10 ), 'Over the size limit.' );
		$this->assertSame( 'wrong_type', ImageCheck::check( $this->png( 'logo.gif' ) ), 'A name that does not match the content.' );

		$text = (string) tempnam( sys_get_temp_dir(), 'wiwtxt' );
		file_put_contents( $text, '<?php echo 1;' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a tiny test file.
		$this->files[] = $text;
		$this->assertSame(
			'wrong_type',
			ImageCheck::check(
				array(
					'name'     => 'logo.png',
					'tmp_name' => $text,
					'size'     => (int) filesize( $text ),
					'error'    => UPLOAD_ERR_OK,
				)
			),
			'A file that is not an image is refused whatever its name says.'
		);
		$this->assertSame(
			'too_big',
			ImageCheck::check(
				array(
					'name'     => 'logo.png',
					'tmp_name' => '',
					'size'     => 0,
					'error'    => UPLOAD_ERR_INI_SIZE,
				)
			)
		);

		list( $service, $token ) = $this->issued();
		$result                  = $service->submit( $token, $this->values(), $this->png( 'logo.exe' ) );
		$this->assertSame( 'invalid', $result['state'] );
		$this->assertSame( 'wrong_type', $result['errors']['image'] ?? '' );
		$this->assertTrue( $service->usable( $token ), 'A refused picture does not use the link up.' );
	}

	public function test_REQ_013_intake_saves_draft_against_link(): void {
		list( $service, $token ) = $this->issued();
		$this->assertSame( array(), $service->draft( $token ) );
		$this->assertTrue(
			$service->save_draft(
				$token,
				array(
					'name'    => 'Draft Ltd',
					'type'    => 'organisation',
					'summary' => 'Half done',
				)
			)
		);
		$this->assertSame( 'Draft Ltd', $service->draft( $token )['name'] );

		$route = $this->route( $service );
		$form  = $route->respond( 'GET', $token, new Request( array() ), '203.0.113.5', new FormInput( array() ) );
		$this->assertSame( 'form', $form['state'] );
		$this->assertSame( 'Draft Ltd', $form['values']['name'], 'Opening the link again shows the draft.' );

		$saved = $route->respond(
			'POST',
			$token,
			new Request(
				array(
					'submit' => 'draft',
					'name'   => 'Changed Ltd',
				)
			),
			'203.0.113.5',
			new FormInput( array() )
		);
		$this->assertSame( 'saved', $saved['state'] );
		$this->assertSame( 'Changed Ltd', $service->draft( $token )['name'] );
		$this->assertSame( array(), $this->customer_profiles(), 'A draft creates no profile.' );

		$this->assertFalse( $service->save_draft( 'abcdefghijklmnopqrstuvwxyz', array( 'name' => 'X' ) ), 'No draft for an unusable link.' );
		$service->submit( $token, $this->values() );
		$this->assertSame( array(), $service->draft( $token ), 'The draft is removed once the profile is sent.' );
	}

	public function test_REQ_013_a_checked_picture_becomes_the_profile_image(): void {
		list( $service, $token ) = $this->issued();
		$this->assertSame( 'done', $service->submit( $token, $this->values(), $this->png() )['state'] );
		$ids = $this->customer_profiles();
		$this->assertCount( 1, $ids );
		$this->assertGreaterThan( 0, (int) get_post_thumbnail_id( $ids[0] ), 'The picture is kept in the media library as the profile image.' );
	}
}
