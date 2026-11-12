<?php
/**
 * Enquiries about a profile (REQ-018, REQ-122, REQ-081). Needs the WordPress test suite and the real profile, support and audit tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Compliance\Forms\TurnstileChallenge;
use WhoIsWho\Core\Compliance\Support\EnquiryIntake;
use WhoIsWho\Core\Compliance\Support\EnquirySubmission;
use WhoIsWho\Core\Compliance\Support\ProfileEnquirySwitch;
use WhoIsWho\Core\Compliance\Support\SupportItems;
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
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class ProfileEnquiryTest extends TestCase {

	private const OWNER_EMAIL = 'owner-secret@example.test';
	private const OWNER_PHONE = '+234 811 222 3333';

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
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	private int $now = 1800000000;

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
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		delete_option( 'wiw_enquiry_sequence' );
		$this->sent = array();
		wp_set_current_user( 0 );
		EnquiryIntake::register();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		wp_set_current_user( 0 );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->users = array();
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		parent::tearDown();
	}

	/**
	 * A reviewed profile that has owner contact details stored on it.
	 *
	 * @param string $name Name.
	 * @return array{0: string, 1: int} Slug and id.
	 */
	private function profile( string $name ): array {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'publish',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		update_post_meta( $id, '_wiw_owner_email', self::OWNER_EMAIL );
		update_post_meta( $id, '_wiw_owner_phone', self::OWNER_PHONE );
		return array( (string) get_post_field( 'post_name', $id ), $id );
	}

	private function handler(): EnquirySubmission {
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
			static fn (): array => array( 'inbox@example.org' ),
			function ( string $to, string $subject, string $text, string $html ): bool {
				unset( $html );
				$this->sent[] = compact( 'to', 'subject', 'text' );
				return true;
			},
			'hash-key'
		);
		$clock         = fn (): int => $this->now;
		$limiter       = new RateLimiter(
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
		$submission    = new PublicSubmission( new TurnstileChallenge( static fn ( string $response ): bool => 'good' === $response ), new FormGate( new FormToken( 'secret', $clock ), $limiter ) );
		$settings      = new SettingsService( SettingsService::default_registry() );
		return new EnquirySubmission( new EnquiryIntake( new SupportItems( null, 'test-key' ), $notifier ), $submission, new ConsentRecorder( null, $settings ) );
	}

	/**
	 * Fields with a fresh token.
	 *
	 * @param array<string, string> $over Overrides.
	 * @return array<string, string>
	 */
	private function fields( array $over = array() ): array {
		$token      = ( new FormToken( 'secret', fn (): int => $this->now ) )->issue( 'enquiry' );
		$this->now += 10;
		return array_merge(
			array(
				'name'                             => 'Ada Obi',
				'email'                            => 'ada@example.test',
				'phone'                            => '',
				'message'                          => 'I would like to reach the team at this organisation.',
				NullChallenge::TOKEN_FIELD         => $token,
				TurnstileChallenge::RESPONSE_FIELD => 'good',
			),
			$over
		);
	}

	private function staff(): void {
		$id            = (int) wp_insert_user(
			array(
				'user_login' => 'enq-staff-' . uniqid(),
				'user_pass'  => wp_generate_password(),
				'user_email' => uniqid() . '@example.test',
			)
		);
		$this->users[] = $id;
		$user          = new \WP_User( $id );
		$user->add_cap( ProfileEnquirySwitch::CAPABILITY );
		wp_set_current_user( $id );
	}

	public function test_REQ_018_profile_enquiry_never_exposes_owner_contact(): void {
		list( $slug, $id ) = $this->profile( 'Enquiry Org Qwv' );
		$result            = $this->handler()->profile( $slug, new FormInput( $this->fields() ), '203.0.113.5' );
		$this->assertSame( PublicSubmission::RECEIVED, $result['status'] );
		$stored = ( new SupportItems() )->find_by_reference( $result['reference'] );
		$this->assertNotNull( $stored );
		$this->assertSame( $id, (int) $stored['profile_id'], 'linked to the profile' );
		$this->assertSame( 'profile', $stored['source'] );
		$this->runner->run_due( 60 );
		$this->assertNotEmpty( $this->sent );
		$everything = wp_json_encode( array( $result, $this->sent ) );
		$this->assertStringNotContainsString( self::OWNER_EMAIL, (string) $everything );
		$this->assertStringNotContainsString( self::OWNER_PHONE, (string) $everything );
		$this->assertContains( 'ada@example.test', array_column( $this->sent, 'to' ), 'the sender gets an acknowledgement' );
		$this->assertNotContains( self::OWNER_EMAIL, array_column( $this->sent, 'to' ), 'the owner is never mailed' );
		$profile = PublicProfile::find( $slug );
		$this->assertNotNull( $profile );
		$this->assertStringNotContainsString( self::OWNER_EMAIL, (string) wp_json_encode( get_object_vars( $profile ) ) );
	}

	public function test_REQ_018_profile_enquiry_rate_limited(): void {
		list( $slug ) = $this->profile( 'Limited Org Qwv' );
		$handler      = $this->handler();
		$statuses     = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$statuses[] = $handler->profile( $slug, new FormInput( $this->fields() ), '203.0.113.6' )['status'];
		}
		$this->assertSame( array( PublicSubmission::RECEIVED, PublicSubmission::RECEIVED, PublicSubmission::TRY_LATER ), $statuses );
	}

	public function test_REQ_018_profile_enquiry_needs_the_challenge_and_valid_fields(): void {
		list( $slug ) = $this->profile( 'Strict Org Qwv' );
		$handler      = $this->handler();
		$bad          = $handler->profile( $slug, new FormInput( $this->fields( array( 'message' => '' ) ) ), '203.0.113.7' );
		$this->assertSame( EnquirySubmission::INVALID, $bad['status'] );
		$no_challenge = $handler->profile( $slug, new FormInput( $this->fields( array( TurnstileChallenge::RESPONSE_FIELD => '' ) ) ), '203.0.113.7' );
		$this->assertSame( PublicSubmission::EXPIRED, $no_challenge['status'] );
		$missing = $handler->profile( 'no-such-profile-qwv', new FormInput( $this->fields() ), '203.0.113.7' );
		$this->assertSame( EnquirySubmission::NOT_FOUND, $missing['status'] );
		$this->assertSame( 0, (int) $this->count_items() );
	}

	public function test_REQ_018_enquiries_off_switch_refuses_and_is_audited(): void {
		list( $slug, $id ) = $this->profile( 'Switched Org Qwv' );
		$switch            = new ProfileEnquirySwitch();
		$this->staff();
		$switch->set_open( $id, false, 'Owner asked by email' );
		wp_set_current_user( 0 );

		$profile = PublicProfile::find( $slug );
		$this->assertNotNull( $profile );
		$this->assertFalse( $profile->enquiries_open );
		$result = $this->handler()->profile( $slug, new FormInput( $this->fields() ), '203.0.113.8' );
		$this->assertSame( EnquirySubmission::ENQUIRIES_OFF, $result['status'] );
		$this->assertSame( 0, (int) $this->count_items() );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND subject_id = %s ORDER BY id DESC LIMIT 1", 'profile.enquiries_switch', (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$this->assertIsArray( $row, 'the change is audited' );
		$this->assertSame( 'Owner asked by email', $row['reason'] );
		$this->assertStringNotContainsString( 'example.test', (string) wp_json_encode( $row ) );

		$this->staff();
		$switch->set_open( $id, true, 'Owner asked to switch back on' );
		wp_set_current_user( 0 );
		$this->assertTrue( PublicProfile::find( $slug )->enquiries_open );
	}

	public function test_REQ_018_only_staff_with_the_capability_can_switch_enquiries(): void {
		list( , $id ) = $this->profile( 'Guarded Org Qwv' );
		$this->expectException( \RuntimeException::class );
		( new ProfileEnquirySwitch() )->set_open( $id, false, 'Trying without a role' );
	}

	private function count_items(): string {
		global $wpdb;
		return (string) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
	}
}
