<?php
/**
 * Whether a profile may be claimed or linked to an arrangement (REQ-226): a profile in review-pending, never-public, rejected,
 * unpublished or removed state may not. Only a human-reviewed, published profile may. The claim flow (M3) calls this.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;

/**
 * Claimability predicate.
 */
final class Claimability {

	/**
	 * State rows.
	 *
	 * @var StateRepository
	 */
	private StateRepository $states;

	/**
	 * Constructor.
	 *
	 * @param StateRepository|null $states State rows.
	 */
	public function __construct( ?StateRepository $states = null ) {
		$this->states = $states ?? new StateRepository();
	}

	/**
	 * Whether the profile may be claimed or linked.
	 *
	 * @param int $profile_id Profile.
	 */
	public function can_claim( int $profile_id ): bool {
		return PubState::PUBLISHED_REVIEWED === $this->states->pub_state( $profile_id );
	}
}
