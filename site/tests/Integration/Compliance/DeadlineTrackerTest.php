<?php
/**
 * Deadline flags, reminders and escalation (REQ-158, REQ-174). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Deadlines\DeadlineTracker;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

/**
 * Plain TestCase on purpose (real tables); the table is emptied in setUp.
 */
final class DeadlineTrackerTest extends TestCase {

	/**
	 * Events sent.
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $sent = array();

	/**
	 * Reminder state.
	 *
	 * @var array<string, mixed>
	 */
	private array $state = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->sent  = array();
		$this->state = array();
	}

	private function items(): SupportItems {
		return new SupportItems( new DueDates( array() ), 'test-key' );
	}

	private function tracker(): DeadlineTracker {
		return new DeadlineTracker(
			$this->items(),
			function ( string $event, array $context ): void {
				$this->sent[] = array( $event, $context );
			},
			array(
				'get' => fn ( string $key, $fallback ) => $this->state[ $key ] ?? $fallback,
				'set' => function ( string $key, $value ): void {
					$this->state[ $key ] = $value;
				},
			)
		);
	}

	private function utc( string $when ): \DateTimeImmutable {
		return new \DateTimeImmutable( $when, new \DateTimeZone( 'UTC' ) );
	}

	private function events(): array {
		return array_column( $this->sent, 0 );
	}

	public function test_REQ_158_overdue_item_shows_breach_flag(): void {
		$row  = $this->items()->create( array( 'type' => ItemType::CORRECTION ), $this->utc( '2026-11-01 09:00:00' ) );
		$this->items()->set_status( $row['id'], ItemStatus::ACKNOWLEDGED, $this->utc( '2026-11-01 10:00:00' ) );
		$item = (array) $this->items()->get( $row['id'] );
		$this->assertSame( DeadlineTracker::NONE, $this->tracker()->flag( $item, $this->utc( '2026-11-03 09:00:00' ) ) );
		$this->assertSame( DeadlineTracker::SOON, $this->tracker()->flag( $item, $this->utc( '2026-11-07 12:00:00' ) ) );
		$this->assertSame( DeadlineTracker::BREACH, $this->tracker()->flag( $item, $this->utc( '2026-11-09 09:00:00' ) ) );
		$this->items()->set_status( $row['id'], ItemStatus::RESOLVED, $this->utc( '2026-11-05 09:00:00' ) );
		$this->assertSame( DeadlineTracker::NONE, $this->tracker()->flag( (array) $this->items()->get( $row['id'] ), $this->utc( '2026-11-09 09:00:00' ) ) );
	}

	public function test_REQ_158_reminder_job_emails_owners(): void {
		$first = $this->items()->create( array( 'type' => ItemType::CORRECTION ), $this->utc( '2026-11-01 09:00:00' ) );
		$this->items()->create( array( 'type' => ItemType::REMOVAL ), $this->utc( '2026-11-06 09:00:00' ) );
		$this->items()->create( array( 'type' => ItemType::CORRECTION ), $this->utc( '2026-11-07 09:00:00' ) );
		$this->items()->set_status( $first['id'], ItemStatus::ACKNOWLEDGED, $this->utc( '2026-11-01 10:00:00' ) );

		// Day before the first correction's due date (2026-11-08 09:00): one reminder to the corrections holders.
		$result = $this->tracker()->run( $this->utc( '2026-11-07 10:00:00' ) );
		$this->assertSame( 1, $result['reminders'] );
		$this->assertSame( array( Events::DEADLINE_DISPUTES ), $this->events() );
		$this->assertStringNotContainsString( '@', wp_json_encode( $this->sent ), 'reminders carry no addresses' );

		// The same day again: nothing new.
		$this->tracker()->run( $this->utc( '2026-11-07 18:00:00' ) );
		$this->assertCount( 1, $this->sent );

		// The removal (due 2026-11-10) is overdue on the 11th: a request reminder, and one a day.
		$this->sent = array();
		$this->tracker()->run( $this->utc( '2026-11-11 08:00:00' ) );
		$this->assertContains( Events::DEADLINE_REQUESTS, $this->events() );
		$count = count( $this->sent );
		$this->tracker()->run( $this->utc( '2026-11-11 20:00:00' ) );
		$this->assertCount( $count, $this->sent, 'one overdue reminder per day' );
		$this->tracker()->run( $this->utc( '2026-11-12 08:00:00' ) );
		$this->assertGreaterThan( $count, count( $this->sent ), 'a new day sends another' );
	}

	public function test_REQ_158_an_enquiry_is_reminded_when_unanswered_and_a_closed_item_is_not(): void {
		$a = $this->items()->create( array( 'type' => ItemType::ENQUIRY ), $this->utc( '2026-11-02 09:00:00' ) );
		$b = $this->items()->create( array( 'type' => ItemType::REMOVAL ), $this->utc( '2026-11-02 09:00:00' ) );
		$this->items()->set_status( $b['id'], ItemStatus::RESOLVED, $this->utc( '2026-11-02 10:00:00' ) );
		$this->tracker()->run( $this->utc( '2026-11-05 09:00:00' ) );
		$this->assertSame( array( Events::DEADLINE_ENQUIRIES ), $this->events() );
		$this->assertSame( $a['reference'], $this->sent[0][1]['reference'] );
	}

	public function test_REQ_174_unresolved_dispute_escalates_to_administrator(): void {
		$this->items()->create( array( 'type' => ItemType::CORRECTION ), $this->utc( '2026-11-01 09:00:00' ) );
		$this->items()->create( array( 'type' => ItemType::REPORT ), $this->utc( '2026-11-01 09:00:00' ) );
		$result = $this->tracker()->run( $this->utc( '2026-11-09 09:00:00' ) );
		$this->assertSame( 1, $result['escalations'], 'a report is not a dispute' );
		$this->assertContains( Events::DISPUTE_ESCALATED, $this->events() );
		$this->assertSame( 'administrators', Events::all()[ Events::DISPUTE_ESCALATED ]['audience'] );

		$again = $this->tracker()->run( $this->utc( '2026-11-10 09:00:00' ) );
		$this->assertSame( 0, $again['escalations'], 'escalated once' );
	}
}
