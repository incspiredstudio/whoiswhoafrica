<?php
/**
 * Claim reminders and dashboard counts (REQ-256, REQ-075). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Platform\Dashboard\TileRegistry;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Mail\StaffAudience;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Trust\ClaimDecision;
use WhoIsWho\Core\Trust\ClaimReminders;
use WhoIsWho\Core\Trust\ClaimRepository;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\WpTestCase;

final class ClaimRemindersTest extends WpTestCase {

	/**
	 * Messages sent.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $sent = array();

	/**
	 * Email log lines.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $logged = array();

	private JobRunner $runner;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::create_table( ClaimRepository::TABLE, ClaimRepository::TABLE_DEFINITION );
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . ClaimRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		delete_option( ClaimRepository::SEQUENCE_OPTION );
		$this->sent   = array();
		$this->logged = array();
	}

	private function reviewer( bool $with_cap = true ): int {
		$id = (int) wp_insert_user(
			array(
				'user_login' => 'rem_' . wp_generate_password( 8, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'rem' . wp_generate_password( 8, false ) . '@example.org',
			)
		);
		if ( $with_cap ) {
			get_userdata( $id )->add_cap( ClaimDecision::CAPABILITY );
		}
		return $id;
	}

	private function reminders(): ClaimReminders {
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
		$log           = new class( $this->logged ) implements EmailLogStore {
			/**
			 * Lines.
			 *
			 * @var array<int, array<string, string>>
			 */
			private array $lines;

			/**
			 * Constructor.
			 *
			 * @param array<int, array<string, string>> $lines Lines, by reference.
			 */
			public function __construct( array &$lines ) {
				$this->lines = &$lines;
			}

			/**
			 * Record.
			 *
			 * @param string $message_id    Id.
			 * @param string $event         Event.
			 * @param string $recipient_ref Ref.
			 * @param string $type          Type.
			 * @param string $result        Result.
			 */
			public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void {
				unset( $message_id, $type );
				$this->lines[] = array(
					'event'  => $event,
					'ref'    => $recipient_ref,
					'result' => $result,
				);
			}
		};
		$notifier      = new Notifier(
			$this->runner,
			$log,
			array( StaffAudience::class, 'addresses' ),
			function ( string $to, string $subject, string $text, string $html ): bool {
				unset( $html );
				$this->sent[] = compact( 'to', 'subject', 'text' );
				return true;
			},
			'hash-key'
		);
		return new ClaimReminders( new ClaimRepository(), $notifier );
	}

	private function claim( string $received ): int {
		return ( new ClaimRepository() )->create(
			array(
				'profile_id'   => 99,
				'relationship' => 'self',
				'name'         => 'Ada Obi',
				'consent'      => true,
			),
			new \DateTimeImmutable( $received, new \DateTimeZone( 'UTC' ) )
		)->id;
	}

	private function utc( string $time ): \DateTimeImmutable {
		return new \DateTimeImmutable( $time, new \DateTimeZone( 'UTC' ) );
	}

	public function test_REQ_256_reminders_sent_day_3_day_4_and_daily_when_overdue(): void {
		$one       = $this->reviewer();
		$two       = $this->reviewer();
		$reminders = $this->reminders();
		$this->claim( '2026-11-01 09:00:00' );

		$this->assertSame( 0, $reminders->run( $this->utc( '2026-11-03 09:00:00' ) ), 'Day 2: nothing yet.' );
		$this->assertSame( 1, $reminders->run( $this->utc( '2026-11-04 10:00:00' ) ), 'Day 3.' );
		$this->assertSame( 0, $reminders->run( $this->utc( '2026-11-04 20:00:00' ) ) );
		$this->assertSame( 1, $reminders->run( $this->utc( '2026-11-05 10:00:00' ) ), 'Day 4.' );
		$this->assertSame( 0, $reminders->run( $this->utc( '2026-11-05 22:00:00' ) ) );
		$this->assertSame( 1, $reminders->run( $this->utc( '2026-11-06 10:00:00' ) ), 'Overdue, first reminder.' );
		$this->assertSame( 0, $reminders->run( $this->utc( '2026-11-06 20:00:00' ) ) );
		$this->assertSame( 1, $reminders->run( $this->utc( '2026-11-07 10:30:00' ) ), 'Overdue, a day later.' );

		$this->runner->run_due( 60 );
		$to = array_column( $this->sent, 'to' );
		$this->assertContains( (string) get_userdata( $one )->user_email, $to );
		$this->assertContains( (string) get_userdata( $two )->user_email, $to );
		$this->assertGreaterThanOrEqual( 8, count( $this->sent ), 'Four reminders to at least two reviewers.' );
	}

	public function test_REQ_256_decision_stops_reminders(): void {
		$this->reviewer();
		$reminders = $this->reminders();
		$id        = $this->claim( '2026-11-01 09:00:00' );
		$this->assertSame( 1, $reminders->run( $this->utc( '2026-11-04 10:00:00' ) ) );
		( new ClaimRepository() )->decide( $id, 'approved', 1, '', $this->utc( '2026-11-04 12:00:00' ) );
		$this->assertSame( 0, $reminders->run( $this->utc( '2026-11-05 10:00:00' ) ) );
		$this->assertSame( 0, $reminders->run( $this->utc( '2026-11-08 10:00:00' ) ) );
	}

	public function test_REQ_256_reminder_job_is_idempotent_within_a_day(): void {
		$this->reviewer();
		$reminders = $this->reminders();
		$this->claim( '2026-11-01 09:00:00' );
		$this->assertSame( 1, $reminders->run( $this->utc( '2026-11-07 10:00:00' ) ) );
		$this->assertSame( 0, $reminders->run( $this->utc( '2026-11-07 10:00:00' ) ) );
		$this->assertSame( 0, $reminders->run( $this->utc( '2026-11-07 18:00:00' ) ) );
		$this->assertSame( 1, $reminders->run( $this->utc( '2026-11-08 10:00:00' ) ) );
	}

	public function test_REQ_256_dashboard_counts_open_due_soon_overdue(): void {
		$reminders = $this->reminders();
		$this->claim( '2026-11-10 09:00:00' );
		$this->claim( '2026-11-07 09:00:00' );
		$this->claim( '2026-11-01 09:00:00' );
		$decided = $this->claim( '2026-11-01 09:30:00' );
		( new ClaimRepository() )->decide( $decided, 'approved', 1, '', $this->utc( '2026-11-02 09:00:00' ) );

		$counts = $reminders->counts( $this->utc( '2026-11-11 09:00:00' ) );
		$this->assertSame(
			array(
				'open'     => 3,
				'due_soon' => 1,
				'overdue'  => 1,
			),
			$counts
		);

		$tiles = new TileRegistry();
		do_action( 'wiw_dashboard_tiles', $tiles );
		foreach ( array( 'claims_open', 'claims_due_soon', 'claims_overdue' ) as $tile ) {
			$this->assertArrayHasKey( $tile, $tiles->all() );
			$this->assertSame( ClaimDecision::CAPABILITY, $tiles->all()[ $tile ]['capability'] );
		}
	}

	public function test_REQ_256_a_user_who_loses_the_capability_stops_receiving_reminders(): void {
		$keep      = $this->reviewer();
		$lose      = $this->reviewer();
		$reminders = $this->reminders();
		$this->claim( '2026-11-01 09:00:00' );
		get_userdata( $lose )->remove_cap( ClaimDecision::CAPABILITY );
		$reminders->run( $this->utc( '2026-11-04 10:00:00' ) );
		$this->runner->run_due( 60 );
		$to = array_column( $this->sent, 'to' );
		$this->assertContains( (string) get_userdata( $keep )->user_email, $to );
		$this->assertNotContains( (string) get_userdata( $lose )->user_email, $to );
	}

	public function test_REQ_075_reminders_written_to_email_log(): void {
		$this->reviewer();
		$reminders = $this->reminders();
		$this->claim( '2026-11-01 09:00:00' );
		$reminders->run( $this->utc( '2026-11-04 10:00:00' ) );
		$this->runner->run_due( 60 );
		$this->assertNotEmpty( $this->logged );
		$this->assertSame( 'claim_reminder', $this->logged[0]['event'] );
		$this->assertStringNotContainsString( '@', $this->logged[0]['ref'], 'The log holds a reference, not an address.' );
	}
}
