<?php
/**
 * Deadline tracking for support items (REQ-158, REQ-174). A daily job looks at every open item and
 * (1) sends the holders of the relevant capability one reminder in the day before an item is due and one a day while it is
 * overdue; (2) routes an unresolved correction or removal that has passed its due date to the Administrator once.
 * The timing (one day before, then daily after) is the proposal in the issue; the rules do not state it. The breach flag shown
 * in the Due column of the lists is computed here from the stored dates, never stored. Reminders carry the reference and kind
 * only: no names, addresses or message text. Reminder state is kept in one option (item id to what was sent), pruned of closed items.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Deadlines;

use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Deadline tracker.
 */
final class DeadlineTracker {

	public const JOB    = 'compliance.deadlines';
	public const OPTION = 'wiw_deadline_reminders';
	public const BREACH = 'breach';
	public const SOON   = 'soon';
	public const NONE   = '';

	/**
	 * Items.
	 *
	 * @var SupportItems
	 */
	private SupportItems $items;

	/**
	 * Sends one event: fn( string $event, array $context ): void.
	 *
	 * @var callable
	 */
	private $notify;

	/**
	 * Storage: get( key, default ) and set( key, value ).
	 *
	 * @var array{get: callable, set: callable}
	 */
	private array $store;

	/**
	 * Constructor.
	 *
	 * @param SupportItems                             $items  Items.
	 * @param callable                                 $notify Sends an event.
	 * @param array{get: callable, set: callable}|null $store  Option storage; WordPress options by default.
	 */
	public function __construct( SupportItems $items, callable $notify, ?array $store = null ) {
		$this->items  = $items;
		$this->notify = $notify;
		$this->store  = $store ?? array(
			'get' => static fn ( string $key, $fallback ) => get_option( $key, $fallback ),
			'set' => static function ( string $key, $value ): void {
				update_option( $key, $value, false );
			},
		);
	}

	/**
	 * Tracker for this site.
	 */
	public static function for_site(): self {
		return new self(
			new SupportItems(),
			static function ( string $event, array $context ): void {
				Notifier::for_site()->send( $event, '', $context );
			}
		);
	}

	/**
	 * Register the daily job.
	 */
	public static function register(): void {
		JobRunner::for_site()->recurring(
			self::JOB,
			86400,
			static function (): void {
				self::for_site()->run();
			}
		);
	}

	/**
	 * The flag for the Due column: breach, soon (due within a day) or none.
	 *
	 * @param array<string, mixed>    $item Row.
	 * @param \DateTimeImmutable|null $now  Now.
	 */
	public function flag( array $item, ?\DateTimeImmutable $now = null ): string {
		$now = $now ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		if ( $this->items->is_breach( $item, $now ) ) {
			return self::BREACH;
		}
		if ( ItemStatus::is_closed( (string) $item['status'] ) ) {
			return self::NONE;
		}
		$target = self::target( $item );
		return null !== $target && $now >= $target->modify( '-1 day' ) ? self::SOON : self::NONE;
	}

	/**
	 * Run once: send the reminders and escalations that are due. Returns what was sent, for the job log and tests.
	 *
	 * @param \DateTimeImmutable|null $now Now.
	 * @return array{reminders: int, escalations: int}
	 */
	public function run( ?\DateTimeImmutable $now = null ): array {
		$now   = $now ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$today = $now->format( 'Y-m-d' );
		$state = (array) ( $this->store['get'] )( self::OPTION, array() );
		$kept  = array();
		$out   = array(
			'reminders'   => 0,
			'escalations' => 0,
		);
		foreach ( $this->items->list_open() as $item ) {
			$id    = (int) $item['id'];
			$mine  = (array) ( $state[ $id ] ?? array() );
			$flag  = $this->flag( $item, $now );
			$reply = array(
				'reference' => (string) $item['reference'],
				'item_id'   => $id,
			);
			if ( self::NONE !== $flag && $this->due_for_reminder( $flag, $mine, $today ) ) {
				$reply['lead'] = self::BREACH === $flag
					? 'Item ' . $item['reference'] . ' is past its response target.'
					: 'Item ' . $item['reference'] . ' is due within a day.';
				( $this->notify )( self::event_for( (string) $item['item_type'] ), $reply );
				if ( self::BREACH === $flag ) {
					$mine['overdue_on'] = $today;
				} else {
					$mine['soon'] = true;
				}
				++$out['reminders'];
			}
			if ( empty( $mine['escalated'] ) && self::is_dispute( (string) $item['item_type'] ) && null !== $item['due_at'] && $now > new \DateTimeImmutable( (string) $item['due_at'], new \DateTimeZone( 'UTC' ) ) ) {
				$reply['lead'] = 'Dispute ' . $item['reference'] . ' is unresolved past its due date.';
				( $this->notify )( Events::DISPUTE_ESCALATED, $reply );
				$mine['escalated'] = true;
				++$out['escalations'];
			}
			if ( array() !== $mine ) {
				$kept[ $id ] = $mine;
			}
		}//end foreach
		( $this->store['set'] )( self::OPTION, $kept );
		return $out;
	}

	/**
	 * Whether a reminder is owed: one in the last day before the target, then one a day once past it.
	 *
	 * @param string               $flag  breach or soon.
	 * @param array<string, mixed> $state What was sent for the item.
	 * @param string               $today Y-m-d.
	 */
	private function due_for_reminder( string $flag, array $state, string $today ): bool {
		if ( self::BREACH === $flag ) {
			return ( $state['overdue_on'] ?? '' ) !== $today;
		}
		return empty( $state['soon'] );
	}

	/**
	 * The date the item is judged against: its resolve date, or for an enquiry the acknowledgement date while it is new.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	private static function target( array $item ): ?\DateTimeImmutable {
		$utc = new \DateTimeZone( 'UTC' );
		if ( null !== $item['due_at'] ) {
			return new \DateTimeImmutable( (string) $item['due_at'], $utc );
		}
		return ItemStatus::NEW === $item['status'] ? new \DateTimeImmutable( (string) $item['ack_due_at'], $utc ) : null;
	}

	/**
	 * The email event for an item type, by who works it.
	 *
	 * @param string $type Item type.
	 */
	private static function event_for( string $type ): string {
		switch ( ItemType::screen( $type ) ) {
			case 'corrections':
				return Events::DEADLINE_DISPUTES;
			case 'requests':
				return Events::DEADLINE_REQUESTS;
			default:
				return Events::DEADLINE_ENQUIRIES;
		}
	}

	/**
	 * Corrections and removals are the disputes REQ-174 sends to the Administrator when unresolved.
	 *
	 * @param string $type Item type.
	 */
	private static function is_dispute( string $type ): bool {
		return ItemType::CORRECTION === $type || ItemType::REMOVAL === $type;
	}
}
