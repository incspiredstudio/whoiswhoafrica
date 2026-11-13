<?php
/**
 * Claim intake (REQ-251, REQ-226, REQ-122, REQ-081, REQ-058). Needs the WordPress test suite and the real profile and claim tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Compliance\Forms\TurnstileChallenge;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Forms\FormGate;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\NullChallenge;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Trust\ClaimIntake;
use WhoIsWho\Core\Trust\ClaimRepository;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class ClaimIntakeTest extends TestCase {

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

	private int $now = 1800000000;

	/**
	 * Queue.
	 *
	 * @var MemoryQueue
	 */
	private MemoryQueue $queue;

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
		$wpdb->query( 'TRUNCATE TABLE ' . ClaimRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		delete_option( ClaimRepository::SEQUENCE_OPTION );
		$this->sent = array();
		wp_set_current_user( 0 );
		ClaimIntake::register();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		parent::tearDown();
	}

	/**
	 * Create a profile in a state.
	 *
	 * @param string $name  Name.
	 * @param string $state published_reviewed or published_pending.
	 * @param bool   $claimed Whether it is already claimed.
	 */
	private function profile( string $name, string $state, bool $claimed = false ): string {
		global $wpdb;
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		if ( $claimed ) {
			$wpdb->update( StateRepository::table(), array( 'claimed' => 1 ), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return (string) get_post_field( 'post_name', $id );
	}

	/**
	 * The intake, with a throttle of two per IP.
	 */
	private function intake(): ClaimIntake {
		$queue            = new MemoryQueue();
		$this->queue      = $queue;
		$options          = new \ArrayObject();
		$runner           = new JobRunner(
			$queue,
			static fn (): int => 1800000000,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$queue->runner    = $runner;
		$this->runner     = $runner;
		$log              = new class() implements EmailLogStore {
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
		$notifier         = new Notifier(
			$runner,
			$log,
			static fn (): array => array( 'claims@example.org' ),
			function ( string $to, string $subject, string $text, string $html ): bool {
				unset( $html );
				$this->sent[] = compact( 'to', 'subject', 'text' );
				return true;
			},
			'hash-key'
		);
		$clock            = fn (): int => $this->now;
		$limiter          = new RateLimiter(
			new MemoryStore(),
			'secret',
			array(
				'default' => array(
					'ip'      => array( 2, 600 ),
					'subject' => array( 5, 600 ),
				),
			),
			$clock
		);
		$submission       = new PublicSubmission( new TurnstileChallenge( static fn ( string $response ): bool => 'good' === $response ), new FormGate( new FormToken( 'secret', $clock ), $limiter ) );
		$settings_service = new \WhoIsWho\Core\Platform\Settings\SettingsService( \WhoIsWho\Core\Platform\Settings\SettingsService::default_registry() );
		return new ClaimIntake( new ClaimRepository(), $notifier, $submission, new ConsentRecorder( null, $settings_service ) );
	}

	/**
	 * Form fields with a fresh token.
	 *
	 * @param array<string, string> $over Overrides.
	 * @return array<string, string>
	 */
	private function fields( array $over = array() ): array {
		$token      = ( new FormToken( 'secret', fn (): int => $this->now ) )->issue( 'claim' );
		$this->now += 10;
		return array_merge(
			array(
				'relationship'                     => 'self',
				'name'                             => 'Ada Obi',
				'role'                             => 'Founder',
				'work_email'                       => 'ada@example.test',
				'phone'                            => '',
				'evidence'                         => 'I am listed as founder on the company registry.',
				'consent'                          => 'on',
				NullChallenge::TOKEN_FIELD         => $token,
				TurnstileChallenge::RESPONSE_FIELD => 'good',
			),
			$over
		);
	}

	private function claim_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ClaimRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
	}

	public function test_REQ_251_claim_form_creates_claim_without_account(): void {
		$slug   = $this->profile( 'Claimable Org Qwv', PubState::PUBLISHED_REVIEWED );
		$result = $this->intake()->submit( $slug, new FormInput( $this->fields() ), '203.0.113.5' );
		$this->assertSame( PublicSubmission::RECEIVED, $result['status'] );
		$this->assertSame( 'CLM-', substr( $result['reference'], 0, 4 ) );
		$claim = ( new ClaimRepository() )->find_by_reference( $result['reference'] );
		$this->assertNotNull( $claim );
		$this->assertSame( 'Ada Obi', $claim->name );
		$this->assertSame( 'ada@example.test', $claim->work_email );
		$this->assertSame( 'self', $claim->relationship );
		$this->assertNotNull( $claim->consent_at );
		$this->assertSame( gmdate( 'Y-m-d', strtotime( $claim->received_at . ' UTC' ) + 5 * 86400 ), substr( $claim->decide_by_at, 0, 10 ), 'decide by five days after receipt' );
		$this->assertTrue( ( new ConsentRecorder( null, new \WhoIsWho\Core\Platform\Settings\SettingsService( \WhoIsWho\Core\Platform\Settings\SettingsService::default_registry() ) ) )->has_consent( 'ada@example.test', ConsentRecorder::CLAIM ), 'consent stored with the policy version' );
		$this->assertSame( 0, get_current_user_id(), 'no account was involved' );
	}

	public function test_REQ_251_claim_rejected_without_required_fields(): void {
		$slug   = $this->profile( 'Claimable Org Rej', PubState::PUBLISHED_REVIEWED );
		$intake = $this->intake();
		foreach ( array(
			'relationship' => '',
			'name'         => '',
			'work_email'   => 'not-an-email',
			'evidence'     => '',
			'consent'      => '',
		) as $field => $value ) {
			$result = $intake->submit( $slug, new FormInput( $this->fields( array( $field => $value ) ) ), '203.0.113.6' );
			$this->assertSame( ClaimIntake::INVALID, $result['status'], $field );
			$this->assertArrayHasKey( $field, $result['errors'], $field );
		}
		$this->assertSame( ClaimIntake::INVALID, $intake->submit( $slug, new FormInput( $this->fields( array( 'relationship' => 'stranger' ) ) ), '203.0.113.6' )['status'] );
		$this->assertSame( 0, $this->claim_count() );
	}

	public function test_REQ_226_pending_profile_cannot_be_claimed(): void {
		$slug   = $this->profile( 'Pending Org Qwv', PubState::PUBLISHED_PENDING );
		$result = $this->intake()->submit( $slug, new FormInput( $this->fields() ), '203.0.113.7' );
		$this->assertSame( ClaimIntake::REVIEW_FIRST, $result['status'] );
		$this->assertStringContainsString( 'reviewed', $result['message'] );
		$this->assertSame( 0, $this->claim_count() );
		$profile = PublicProfile::find( $slug );
		$this->assertNotNull( $profile );
		$this->assertFalse( $profile->claimable );
	}

	public function test_REQ_226_a_claimed_profile_returns_already_claimed_and_creates_no_claim(): void {
		$slug   = $this->profile( 'Claimed Org Qwv', PubState::PUBLISHED_REVIEWED, true );
		$result = $this->intake()->submit( $slug, new FormInput( $this->fields() ), '203.0.113.8' );
		$this->assertSame( ClaimIntake::ALREADY_CLAIMED, $result['status'] );
		$this->assertSame( 0, $this->claim_count() );
		$profile = PublicProfile::find( $slug );
		$this->assertTrue( $profile->claimed );
		$this->assertFalse( $profile->claimable );
	}

	public function test_REQ_226_a_reviewed_unclaimed_profile_is_claimable(): void {
		$slug = $this->profile( 'Open Org Qwv', PubState::PUBLISHED_REVIEWED );
		$this->assertTrue( PublicProfile::find( $slug )->claimable );
	}

	public function test_REQ_251_claimant_receives_acknowledgement(): void {
		$slug   = $this->profile( 'Ack Org Qwv', PubState::PUBLISHED_REVIEWED );
		$result = $this->intake()->submit( $slug, new FormInput( $this->fields() ), '203.0.113.9' );
		foreach ( $this->queue->jobs as $job ) {
			$this->assertStringNotContainsString( 'ada@example.test', (string) wp_json_encode( $job['args'] ), 'The address is not in the job.' );
		}
		$this->runner->run_due( 60 );
		$to = array_column( $this->sent, 'to' );
		$this->assertContains( 'ada@example.test', $to, 'the claimant is told' );
		$this->assertContains( 'claims@example.org', $to, 'the claim reviewers are told' );
		$ack = $this->sent[ array_search( 'ada@example.test', $to, true ) ];
		$this->assertStringContainsString( $result['reference'], $ack['text'] );
	}

	public function test_REQ_122_claim_form_is_rate_limited(): void {
		$slug   = $this->profile( 'Limited Org Qwv', PubState::PUBLISHED_REVIEWED );
		$intake = $this->intake();
		$intake->submit( $slug, new FormInput( $this->fields( array( 'work_email' => 'a1@example.test' ) ) ), '203.0.113.10' );
		$intake->submit( $slug, new FormInput( $this->fields( array( 'work_email' => 'a2@example.test' ) ) ), '203.0.113.10' );
		$third = $intake->submit( $slug, new FormInput( $this->fields( array( 'work_email' => 'a3@example.test' ) ) ), '203.0.113.10' );
		$this->assertSame( PublicSubmission::TRY_LATER, $third['status'] );
		$this->assertSame( 2, $this->claim_count() );
	}

	public function test_REQ_081_a_missing_form_token_creates_no_claim(): void {
		$slug   = $this->profile( 'Token Org Qwv', PubState::PUBLISHED_REVIEWED );
		$result = $this->intake()->submit( $slug, new FormInput( $this->fields( array( NullChallenge::TOKEN_FIELD => '' ) ) ), '203.0.113.11' );
		$this->assertSame( PublicSubmission::EXPIRED, $result['status'] );
		$this->assertSame( 0, $this->claim_count() );
	}
}
