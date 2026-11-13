<?php
/**
 * Decisions on a removal request (REQ-209, REQ-147, REQ-207, REQ-158). "Valid: hide and suppress" moves the profile to Removed
 * and suppressed through the StateMachine in the same request (410 with noindex, out of the sitemap and search, purge queued by
 * the state change), adds the suppression entry from the profile's name, entity type and external ids, closes the item and tells
 * the requester. "Not valid" declines the item with a reason and leaves the profile alone. Deciding needs wiw_handle_disputes, which
 * the Editor and Administrator hold, so an Editor's removal adds the entry the same way. The audit rows hold ids and states, never
 * names, addresses or the reason text.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Removals;

use WhoIsWho\Core\Compliance\Support\EnquiryIntake;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Review\Duplicates\Matcher;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Removal decisions.
 */
final class RemovalDecisions {

	public const CAP = 'wiw_handle_disputes';

	/**
	 * Items.
	 *
	 * @var SupportItems
	 */
	private SupportItems $items;

	/**
	 * State machine.
	 *
	 * @var StateMachine
	 */
	private StateMachine $machine;

	/**
	 * Suppression list.
	 *
	 * @var Suppression
	 */
	private Suppression $suppression;

	/**
	 * Notifier.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param SupportItems $items       Items.
	 * @param StateMachine $machine     State machine.
	 * @param Suppression  $suppression Suppression list.
	 * @param Notifier     $notifier    Notifier.
	 * @param AuditLogger  $audit       Audit logger.
	 */
	public function __construct( SupportItems $items, StateMachine $machine, Suppression $suppression, Notifier $notifier, AuditLogger $audit ) {
		$this->items       = $items;
		$this->machine     = $machine;
		$this->suppression = $suppression;
		$this->notifier    = $notifier;
		$this->audit       = $audit;
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		$audit = AuditLogger::for_site();
		return new self( new SupportItems(), new StateMachine( null, $audit ), new Suppression( $audit ), Notifier::for_site(), $audit );
	}

	/**
	 * Valid: hide and suppress.
	 *
	 * @param int $item_id Removal item id.
	 * @throws \InvalidArgumentException Without the capability, or for an item that is not an open removal with a profile.
	 */
	public function uphold( int $item_id ): void {
		$item       = $this->open_removal( $item_id );
		$profile_id = (int) $item['profile_id'];
		$state      = ( new StateRepository() )->get( $profile_id );
		if ( null === $state ) {
			throw new \InvalidArgumentException( 'The profile has no state.' );
		}
		if ( PubState::REMOVED_SUPPRESSED !== $state[ StateRepository::STATE_COLUMN ] ) {
			$this->machine->transition( $profile_id, PubState::REMOVED_SUPPRESSED, 'Removal ' . (string) $item['reference'], (int) get_current_user_id() );
		}
		$this->suppression->add_from_removal( (string) get_the_title( $profile_id ), (string) $state['entity_type'], self::external_ids( $profile_id ), $item_id );
		$this->items->set_status( $item_id, ItemStatus::RESOLVED );
		$this->audit->log(
			'removal.upheld',
			array(
				'type' => 'support_item',
				'id'   => $item_id,
			),
			array( 'status' => (string) $item['status'] ),
			array(
				'status'  => ItemStatus::RESOLVED,
				'profile' => $profile_id,
			)
		);
		$this->tell_requester( $item, 'Your removal request was upheld. The profile has been taken down.', '' );
		/**
		 * Fires when a removal request is upheld, after the profile is removed and suppressed.
		 *
		 * @param int $profile_id Profile.
		 * @param int $item_id    Removal item.
		 */
		do_action( 'wiw_removal_upheld', $profile_id, $item_id );
	}

	/**
	 * Not valid: decline with a reason that the requester is sent.
	 *
	 * @param int    $item_id Removal item id.
	 * @param string $reason  Why (short text, sent to the requester).
	 * @throws \InvalidArgumentException Without the capability, or for a missing reason or an item that is not an open removal.
	 */
	public function decline( int $item_id, string $reason ): void {
		$reason = trim( $reason );
		$item   = $this->open_removal( $item_id );
		if ( '' === $reason ) {
			throw new \InvalidArgumentException( 'A reason is required.' );
		}
		$this->items->set_status( $item_id, ItemStatus::DECLINED );
		$this->audit->log(
			'removal.declined',
			array(
				'type' => 'support_item',
				'id'   => $item_id,
			),
			array( 'status' => (string) $item['status'] ),
			array( 'status' => ItemStatus::DECLINED )
		);
		$this->tell_requester( $item, 'We could not take your request forward.', mb_substr( $reason, 0, 500 ) );
	}

	/**
	 * The item, checked.
	 *
	 * @param int $item_id Item id.
	 * @return array<string, mixed>
	 * @throws \DomainException Without the capability.
	 * @throws \InvalidArgumentException When it is not an open removal about a profile.
	 */
	private function open_removal( int $item_id ): array {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to decide removals.' );
		}
		$item = $this->items->get( $item_id );
		if ( null === $item || ItemType::REMOVAL !== $item['item_type'] || null === $item['profile_id'] ) {
			throw new \InvalidArgumentException( 'That is not a removal request.' );
		}
		if ( ItemStatus::is_closed( (string) $item['status'] ) ) {
			throw new \InvalidArgumentException( 'That request is already closed.' );
		}
		return $item;
	}

	/**
	 * Queue the closing email; the address is looked up when the job runs.
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $lead   Opening line.
	 * @param string               $reason Reason, or empty.
	 */
	private function tell_requester( array $item, string $lead, string $reason ): void {
		if ( '' === (string) $item['requester_email'] ) {
			return;
		}
		$this->notifier->send_to_record(
			Events::REQUEST_RESOLVED,
			EnquiryIntake::RECIPIENT_SOURCE,
			(int) $item['id'],
			array(
				'reference' => (string) $item['reference'],
				'id'        => (int) $item['id'],
				'lead'      => $lead,
				'reason'    => $reason,
			)
		);
	}

	/**
	 * The profile's external ids, source => id.
	 *
	 * @param int $profile_id Profile.
	 * @return array<string, string>
	 */
	private static function external_ids( int $profile_id ): array {
		$ids = json_decode( (string) get_post_meta( $profile_id, Matcher::EXTERNAL_IDS_META, true ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- decoding our own stored JSON.
		$out = array();
		foreach ( is_array( $ids ) ? $ids : array() as $source => $value ) {
			if ( is_string( $value ) || is_int( $value ) ) {
				$out[ (string) $source ] = (string) $value;
			}
		}
		return $out;
	}
}
