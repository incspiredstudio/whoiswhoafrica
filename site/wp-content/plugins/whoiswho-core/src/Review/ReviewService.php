<?php
/**
 * The review actions: approve, reject, unpublish one profile and restore it (REQ-222, REQ-148, REQ-209, REQ-217). Screens and
 * handlers call this service; it checks the named capability for the acting user, applies the importer rule and hands the move to
 * the state machine, which writes state, post status and the audit row together and announces the change so the purge queues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Import\Scan\ScanBlock;

/**
 * Review service.
 */
final class ReviewService {

	/**
	 * State machine.
	 *
	 * @var StateMachine
	 */
	private StateMachine $machine;

	/**
	 * State rows.
	 *
	 * @var StateRepository
	 */
	private StateRepository $states;

	/**
	 * Constructor.
	 *
	 * @param StateMachine|null    $machine State machine.
	 * @param StateRepository|null $states  State rows.
	 */
	public function __construct( ?StateMachine $machine = null, ?StateRepository $states = null ) {
		$this->machine = $machine ?? new StateMachine();
		$this->states  = $states ?? new StateRepository();
	}

	/**
	 * Approve: one flow that sets reviewed with date and reviewer. The noindex, sitemap, schema and purge follow from the new
	 * state and the state-changed event in the same step.
	 *
	 * @param int $profile_id Profile.
	 * @param int $actor_id   Reviewer.
	 * @throws StateException When refused.
	 */
	public function approve( int $profile_id, int $actor_id ): void {
		$this->require_cap( $actor_id, 'wiw_review_records' );
		$row = $this->row( $profile_id );
		if ( (int) ( $row['importer_id'] ?? 0 ) > 0 && (int) $row['importer_id'] === $actor_id ) {
			throw StateException::refuse( 'The person who imported a record cannot approve it.' );
		}
		if ( ScanBlock::is_blocked( $profile_id ) ) {
			throw StateException::refuse( 'The free-text scan flagged this record. Clear the block before approving.' );
		}
		$this->machine->transition( $profile_id, PubState::PUBLISHED_REVIEWED, '', $actor_id );
	}

	/**
	 * Reject with a reason. A profile that was live (pending review) goes to Unpublished so it answers 410; one that was never
	 * public goes to Rejected.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $reason     Why; required.
	 * @param int    $actor_id   Reviewer.
	 * @throws StateException When refused.
	 */
	public function reject( int $profile_id, string $reason, int $actor_id ): void {
		$this->require_cap( $actor_id, 'wiw_review_records' );
		$row = $this->row( $profile_id );
		if ( PubState::PUBLISHED_REVIEWED === $row['pub_state'] ) {
			throw StateException::refuse( 'A reviewed profile is unpublished, not rejected.' );
		}
		$to = PubState::is_public( (string) $row['pub_state'] ) ? PubState::UNPUBLISHED : PubState::REJECTED;
		$this->machine->transition( $profile_id, $to, $reason, $actor_id );
	}

	/**
	 * Unpublish one profile with a reason (410, noindex). An Editor holds wiw_unpublish_profiles; a Reviewer reaches it through
	 * wiw_bulk_unpublish for emergencies.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $reason     Why; required.
	 * @param int    $actor_id   Acting user.
	 * @throws StateException When refused.
	 */
	public function unpublish( int $profile_id, string $reason, int $actor_id ): void {
		if ( ! user_can( $actor_id, 'wiw_unpublish_profiles' ) && ! user_can( $actor_id, 'wiw_bulk_unpublish' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw StateException::refuse( 'Not allowed.' );
		}
		$row = $this->row( $profile_id );
		if ( ! PubState::is_public( (string) $row['pub_state'] ) ) {
			throw StateException::refuse( 'Only a published profile can be unpublished.' );
		}
		$this->machine->transition( $profile_id, PubState::UNPUBLISHED, $reason, $actor_id );
	}

	/**
	 * Restore an unpublished profile to the state it had: reviewed if a person had reviewed it, otherwise pending.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $reason     Why.
	 * @param int    $actor_id   Acting user.
	 * @throws StateException When refused.
	 */
	public function restore( int $profile_id, string $reason, int $actor_id ): void {
		if ( ! user_can( $actor_id, 'wiw_unpublish_profiles' ) && ! user_can( $actor_id, 'wiw_bulk_unpublish' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw StateException::refuse( 'Not allowed.' );
		}
		$row = $this->row( $profile_id );
		if ( PubState::UNPUBLISHED !== $row['pub_state'] ) {
			throw StateException::refuse( 'Only an unpublished profile can be restored.' );
		}
		$to = ! empty( $row['reviewed_at'] ) && ! empty( $row['reviewer_id'] ) ? PubState::PUBLISHED_REVIEWED : PubState::PUBLISHED_PENDING;
		$this->machine->transition( $profile_id, $to, $reason, null );
	}

	/**
	 * The state row, or a refusal.
	 *
	 * @param int $profile_id Profile.
	 * @return array<string, mixed>
	 * @throws StateException When there is no row.
	 */
	private function row( int $profile_id ): array {
		$row = $this->states->get( $profile_id );
		if ( null === $row ) {
			throw StateException::refuse( 'The profile has no state.' );
		}
		return $row;
	}

	/**
	 * Check a capability for the acting user.
	 *
	 * @param int    $actor_id Actor.
	 * @param string $cap      Capability.
	 * @throws StateException When missing.
	 */
	private function require_cap( int $actor_id, string $cap ): void {
		if ( ! user_can( $actor_id, $cap ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- named capabilities from config/capabilities.php.
			throw StateException::refuse( 'Not allowed.' );
		}
	}
}
