<?php
/**
 * Deal steps 3 and 4 (REQ-263, REQ-269, REQ-182, REQ-188): choose the story, then schedule the slot. They need wiw_set_sponsored
 * (Editors and Administrators). A story may be a draft when chosen, but a slot needs it published. An event cannot be chosen.
 * Two slots may not overlap on the same page and dates. An unpaid deal can be scheduled; it then shows "Payment not recorded"
 * (see DealStatus). Every step writes an audit row of ids, slot and dates only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Placement service.
 */
final class PlacementService {

	public const CAP = 'wiw_set_sponsored';

	/**
	 * Deals.
	 *
	 * @var ArrangementRepository
	 */
	private ArrangementRepository $deals;

	/**
	 * Placements.
	 *
	 * @var PlacementRepository
	 */
	private PlacementRepository $placements;

	/**
	 * Customers, for the sponsor name.
	 *
	 * @var CustomerRepository
	 */
	private CustomerRepository $customers;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Clock.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param ArrangementRepository $deals      Deals.
	 * @param PlacementRepository   $placements Placements.
	 * @param CustomerRepository    $customers  Customers.
	 * @param AuditLogger           $audit      Audit logger.
	 * @param callable|null         $clock      fn(): \DateTimeImmutable.
	 */
	public function __construct( ArrangementRepository $deals, PlacementRepository $placements, CustomerRepository $customers, AuditLogger $audit, ?callable $clock = null ) {
		$this->deals      = $deals;
		$this->placements = $placements;
		$this->customers  = $customers;
		$this->audit      = $audit;
		$this->clock      = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Step 3: choose a story for the deal. A draft may be chosen; an event or any other post type may not.
	 *
	 * @param int $deal_id  Deal.
	 * @param int $story_id Story post id.
	 * @throws \DomainException When the user may not, the deal is cancelled, or the post is not a story.
	 */
	public function choose_story( int $deal_id, int $story_id ): void {
		$this->require_cap();
		$deal = $this->open_deal( $deal_id );
		if ( 'post' !== get_post_type( $story_id ) || ! in_array( get_post_status( $story_id ), array( 'publish', 'draft', 'pending', 'future', 'private' ), true ) ) {
			throw new \DomainException( 'Only a story can be chosen for a sponsored placement.' );
		}
		$this->deals->update( $deal_id, array( 'story_id' => $story_id ), ( $this->clock )() );
		$this->audit->log(
			'deal.story',
			$this->subject( $deal_id ),
			array( 'story_id' => empty( $deal['story_id'] ) ? null : (int) $deal['story_id'] ),
			array( 'story_id' => $story_id )
		);
	}

	/**
	 * Step 4: schedule the slot. Use again after a slot has ended to schedule a new one.
	 *
	 * @param int    $deal_id Deal.
	 * @param string $slot    home, stories or both.
	 * @param string $starts  Start date, Y-m-d.
	 * @param string $ends    End date, Y-m-d.
	 * @return array{placement_id: int, warnings: array<int, string>} Warnings: payment_not_recorded.
	 * @throws \DomainException When the user may not, there is no published story, or the slot overlaps another.
	 */
	public function schedule( int $deal_id, string $slot, string $starts, string $ends ): array {
		$this->require_cap();
		$deal  = $this->open_deal( $deal_id );
		$story = (int) ( $deal['story_id'] ?? 0 );
		if ( $story < 1 || 'post' !== get_post_type( $story ) || 'publish' !== get_post_status( $story ) ) {
			throw new \DomainException( 'The story must be published before it can have a slot.' );
		}
		if ( ! in_array( $slot, PlacementRepository::SLOTS, true ) ) {
			throw new \DomainException( 'A slot is Home, Stories or Both.' );
		}
		if ( $this->placements->overlapping_count( $slot, $starts, $ends ) > 0 ) {
			throw new \DomainException( 'Another sponsored slot already uses that page on those dates.' );
		}
		$customer = $this->customers->get( (int) $deal['customer_id'] );
		$id       = $this->placements->insert(
			array(
				'arrangement_id' => $deal_id,
				'story_id'       => $story,
				'sponsor_name'   => null === $customer ? '' : (string) $customer['name'],
				'slot'           => $slot,
				'starts_on'      => $starts,
				'ends_on'        => $ends,
			),
			( $this->clock )()
		);
		$this->audit->log(
			'deal.placement',
			$this->subject( $deal_id ),
			null,
			array(
				'placement_id' => $id,
				'slot'         => $slot,
				'starts_on'    => $starts,
				'ends_on'      => $ends,
			)
		);
		return array(
			'placement_id' => $id,
			'warnings'     => DealStatus::payment_complete( $deal ) ? array() : array( 'payment_not_recorded' ),
		);
	}

	/**
	 * End a placement now. Nothing is deleted.
	 *
	 * @param int $placement_id Placement.
	 * @throws \DomainException When the user may not or the placement is unknown.
	 */
	public function end_now( int $placement_id ): void {
		$this->require_cap();
		$placement = $this->placements->get( $placement_id );
		if ( null === $placement ) {
			throw new \DomainException( 'Unknown placement.' );
		}
		$this->placements->mark_ended( $placement_id, ( $this->clock )() );
		$this->audit->log( 'deal.placement_end', $this->subject( (int) $placement['arrangement_id'] ), null, array( 'placement_id' => $placement_id ) );
	}

	/**
	 * The deal, refused when unknown or cancelled.
	 *
	 * @param int $deal_id Deal.
	 * @return array<string, mixed>
	 * @throws \DomainException When unknown or cancelled.
	 */
	private function open_deal( int $deal_id ): array {
		$deal = $this->deals->get( $deal_id );
		if ( null === $deal || ! empty( $deal['cancelled'] ) ) {
			throw new \DomainException( 'The deal is unknown or cancelled.' );
		}
		return $deal;
	}

	/**
	 * Audit subject.
	 *
	 * @param int $deal_id Deal.
	 * @return array{type: string, id: int}
	 */
	private function subject( int $deal_id ): array {
		return array(
			'type' => 'deal',
			'id'   => $deal_id,
		);
	}

	/**
	 * Refuse without the capability.
	 *
	 * @throws \DomainException When the user lacks it.
	 */
	private function require_cap(): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to place sponsored stories.' );
		}
	}
}
