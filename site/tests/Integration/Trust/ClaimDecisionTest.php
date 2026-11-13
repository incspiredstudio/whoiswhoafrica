<?php
/**
 * Claim approve and reject decisions (REQ-252, REQ-150, REQ-184, REQ-183, REQ-188). Needs the WordPress test suite and the real
 * profile, claim and audit tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Trust\Claim;
use WhoIsWho\Core\Trust\ClaimDecision;
use WhoIsWho\Core\Trust\ClaimIntake;
use WhoIsWho\Core\Trust\ClaimRepository;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class ClaimDecisionTest extends TestCase {

	/**
	 * Messages sent.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $sent = array();

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $runner;

	private int $reviewer = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . ClaimRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		delete_option( ClaimRepository::SEQUENCE_OPTION );
		$this->sent = array();
		ClaimIntake::register();
		$this->reviewer = (int) wp_insert_user(
			array(
				'user_login' => 'claim_reviewer_' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'reviewer' . wp_generate_password( 6, false ) . '@example.org',
			)
		);
		get_userdata( $this->reviewer )->add_cap( ClaimDecision::CAPABILITY );
		wp_set_current_user( $this->reviewer );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		wp_delete_user( $this->reviewer );
		wp_set_current_user( 0 );
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		parent::tearDown();
	}

	private function profile( string $name ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		return $id;
	}

	private function claim( int $profile_id ): Claim {
		return ( new ClaimRepository() )->create(
			array(
				'profile_id'   => $profile_id,
				'relationship' => 'self',
				'name'         => 'Ada Obi',
				'work_email'   => 'ada@example.test',
				'evidence'     => 'Listed on the registry.',
				'consent'      => true,
			)
		);
	}

	private function service(): ClaimDecision {
		$queue         = new MemoryQueue();
		$options       = new \ArrayObject();
		$this->runner  = new JobRunner(
			$queue,
			static fn (): int => 1800000000,
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
		$notifier      = new Notifier(
			$this->runner,
			$log,
			static fn (): array => array(),
			function ( string $to, string $subject, string $text, string $html ): bool {
				unset( $html );
				$this->sent[] = compact( 'to', 'subject', 'text' );
				return true;
			},
			'hash-key'
		);
		return new ClaimDecision( new ClaimRepository(), new StateRepository(), $notifier );
	}

	/**
	 * Audit row for a subject.
	 *
	 * @param string $action Action.
	 * @param int    $id     Claim id.
	 * @return array<string, mixed>|null
	 */
	private function audit_row( string $action, int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND subject_id = %s ORDER BY id DESC LIMIT 1", $action, (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	public function test_REQ_252_approval_sets_claimed_and_hides_public_claim_button(): void {
		$id    = $this->profile( 'Approve Org Zq' );
		$claim = $this->claim( $id );
		$slug  = (string) get_post_field( 'post_name', $id );
		$this->assertTrue( PublicProfile::find( $slug )->claimable );
		$out = $this->service()->approve( $claim->id, $this->reviewer );
		$this->assertSame( $id, $out['profile_id'] );
		$this->assertTrue( $out['offer_assign'] );
		$state = ( new StateRepository() )->get( $id );
		$this->assertSame( 1, (int) $state['claimed'] );
		$this->assertNotEmpty( $state['claimed_at'] );
		$profile = PublicProfile::find( $slug );
		$this->assertFalse( $profile->claimable, 'no Claim button' );
		$stored = ( new ClaimRepository() )->find( $claim->id );
		$this->assertSame( Claim::APPROVED, $stored->status );
		$this->assertSame( $this->reviewer, $stored->decided_by );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->pub_state( $id ), 'publication state untouched' );
	}

	public function test_REQ_252_rejection_requires_reason(): void {
		$claim   = $this->claim( $this->profile( 'Reject Org Zq' ) );
		$service = $this->service();
		try {
			$service->reject( $claim->id, $this->reviewer, '   ' );
			$this->fail( 'A rejection without a reason must be refused.' );
		} catch ( \DomainException $e ) {
			$this->assertStringContainsString( 'reason', $e->getMessage() );
		}
		$this->assertSame( Claim::NEW, ( new ClaimRepository() )->find( $claim->id )->status );
	}

	public function test_REQ_252_rejection_emails_claimant_and_keeps_button(): void {
		$id    = $this->profile( 'Reject Mail Org Zq' );
		$claim = $this->claim( $id );
		$slug  = (string) get_post_field( 'post_name', $id );
		$this->service()->reject( $claim->id, $this->reviewer, 'We could not confirm your role.' );
		$this->runner->run_due( 60 );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'ada@example.test', $this->sent[0]['to'] );
		$this->assertStringContainsString( 'We could not confirm your role.', $this->sent[0]['text'] );
		$this->assertTrue( PublicProfile::find( $slug )->claimable, 'the profile stays claimable' );
		$this->assertSame( 0, (int) ( new StateRepository() )->get( $id )['claimed'] );
		$stored = ( new ClaimRepository() )->find( $claim->id );
		$this->assertSame( Claim::REJECTED, $stored->status );
		$this->assertSame( 'We could not confirm your role.', $stored->reason );
	}

	public function test_REQ_252_approval_emails_claimant(): void {
		$claim = $this->claim( $this->profile( 'Approve Mail Org Zq' ) );
		$this->service()->approve( $claim->id, $this->reviewer );
		$this->runner->run_due( 60 );
		$this->assertSame( array( 'ada@example.test' ), array_column( $this->sent, 'to' ) );
	}

	public function test_REQ_150_claimed_badge_not_in_public_output(): void {
		$id    = $this->profile( 'Badge Org Zq' );
		$claim = $this->claim( $id );
		$this->service()->approve( $claim->id, $this->reviewer );
		$profile = PublicProfile::find( (string) get_post_field( 'post_name', $id ) );
		$this->assertNotNull( $profile );
		$json = strtolower( (string) wp_json_encode( get_object_vars( $profile ) ) );
		$this->assertStringNotContainsString( 'verified', $json );
		$this->assertStringNotContainsString( '"claimed_at"', $json );
		$this->assertFalse( $profile->claimable );
	}

	public function test_REQ_184_payment_never_sets_claimed(): void {
		$id = $this->profile( 'Pay Org Zq' );
		$this->claim( $id );
		$this->assertSame( 0, (int) ( new StateRepository() )->get( $id )['claimed'], 'a claim alone changes nothing' );
		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Trust/ClaimDecision.php' );
		$this->assertStringNotContainsString( 'Commerce', $source );
	}

	public function test_REQ_252_only_a_reviewer_can_decide_and_only_once(): void {
		$claim   = $this->claim( $this->profile( 'Guard Org Zq' ) );
		$service = $this->service();
		$nobody  = (int) wp_insert_user(
			array(
				'user_login' => 'no_cap_' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'nocap' . wp_generate_password( 6, false ) . '@example.org',
			)
		);
		try {
			$service->approve( $claim->id, $nobody );
			$this->fail( 'A user without the capability must be refused.' );
		} catch ( \DomainException $e ) {
			$this->assertSame( Claim::NEW, ( new ClaimRepository() )->find( $claim->id )->status );
		}
		wp_delete_user( $nobody );
		$service->approve( $claim->id, $this->reviewer );
		$this->expectException( \DomainException::class );
		$service->reject( $claim->id, $this->reviewer, 'Too late.' );
	}

	public function test_REQ_188_claim_decision_writes_audit_row(): void {
		$one = $this->claim( $this->profile( 'Audit Org One Zq' ) );
		$two = $this->claim( $this->profile( 'Audit Org Two Zq' ) );
		$svc = $this->service();
		$svc->approve( $one->id, $this->reviewer );
		$svc->reject( $two->id, $this->reviewer, 'No evidence.' );
		$approve = $this->audit_row( 'claim.approve', $one->id );
		$reject  = $this->audit_row( 'claim.reject', $two->id );
		$this->assertNotNull( $approve );
		$this->assertSame( (string) $this->reviewer, (string) $approve['actor_id'] );
		$this->assertNotNull( $reject );
		$this->assertSame( (string) $this->reviewer, (string) $reject['actor_id'] );
		$this->assertSame( 'No evidence.', $reject['reason'] );
	}
}
