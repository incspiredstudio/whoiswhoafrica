<?php
/**
 * Claim decisions (REQ-252, REQ-150, REQ-184, REQ-183, REQ-188). Approving a claim sets Claimed (a staff-only mark) and the
 * claimed date on the profile's state row, records the deciding user and offers the assign step; it sets no trust label and does
 * not touch publication state. Rejecting needs a reason, stores it and leaves the profile claimable. Both decisions email the
 * claimant (the address is looked up when the job runs) and write an audit row. Only a person holding wiw_review_claims can
 * decide; payment and arrangements have no path here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Claim decision service.
 */
final class ClaimDecision {

	public const CAPABILITY = 'wiw_review_claims';

	/**
	 * Claims.
	 *
	 * @var ClaimRepository
	 */
	private ClaimRepository $claims;

	/**
	 * Profile state rows.
	 *
	 * @var StateRepository
	 */
	private StateRepository $states;

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
	 * @param ClaimRepository  $claims   Claim storage.
	 * @param StateRepository  $states   Profile state rows.
	 * @param Notifier         $notifier Notifier.
	 * @param AuditLogger|null $audit    Audit logger.
	 */
	public function __construct( ClaimRepository $claims, StateRepository $states, Notifier $notifier, ?AuditLogger $audit = null ) {
		$this->claims   = $claims;
		$this->states   = $states;
		$this->notifier = $notifier;
		$this->audit    = $audit ?? AuditLogger::for_site();
	}

	/**
	 * Approve a claim: the profile becomes Claimed and nothing else changes on it.
	 *
	 * @param int $claim_id Claim id.
	 * @param int $user_id  Deciding user.
	 * @return array{profile_id: int, offer_assign: bool} The profile, and whether the assign step should be offered.
	 * @throws \DomainException When the user may not decide, the claim is missing or decided, or the profile is gone or already claimed.
	 */
	public function approve( int $claim_id, int $user_id ): array {
		$claim = $this->open_claim( $claim_id, $user_id );
		$state = $this->states->get( $claim->profile_id );
		if ( null === $state ) {
			throw new \DomainException( 'The claimed profile no longer exists.' );
		}
		if ( ! empty( $state['claimed'] ) ) {
			throw new \DomainException( 'This profile is already claimed.' );
		}
		$this->claims->decide( $claim->id, Claim::APPROVED, $user_id );
		$this->states->update(
			$claim->profile_id,
			array(
				'claimed'    => 1,
				'claimed_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$this->audit->log(
			'claim.approve',
			$this->subject( $claim ),
			array( 'status' => Claim::NEW ),
			array(
				'status'     => Claim::APPROVED,
				'profile_id' => $claim->profile_id,
				'decided_by' => $user_id,
			),
			'Claim approved'
		);
		$this->tell( $claim, 'Your claim has been approved. We will be in touch about keeping the profile up to date.', '' );
		return array(
			'profile_id'   => $claim->profile_id,
			'offer_assign' => true,
		);
	}

	/**
	 * Reject a claim with a reason. The profile stays claimable.
	 *
	 * @param int    $claim_id Claim id.
	 * @param int    $user_id  Deciding user.
	 * @param string $reason   Why; required, and sent to the claimant.
	 * @throws \DomainException When the user may not decide, there is no reason, or the claim is missing or decided.
	 */
	public function reject( int $claim_id, int $user_id, string $reason ): void {
		$reason = trim( $reason );
		if ( '' === $reason ) {
			throw new \DomainException( 'A rejection needs a reason.' );
		}
		$claim = $this->open_claim( $claim_id, $user_id );
		$this->claims->decide( $claim->id, Claim::REJECTED, $user_id, $reason );
		$this->audit->log(
			'claim.reject',
			$this->subject( $claim ),
			array( 'status' => Claim::NEW ),
			array(
				'status'     => Claim::REJECTED,
				'profile_id' => $claim->profile_id,
				'decided_by' => $user_id,
			),
			$reason
		);
		$this->tell( $claim, 'We could not approve your claim.', $reason );
	}

	/**
	 * Load a claim that can still be decided by this user.
	 *
	 * @param int $claim_id Claim id.
	 * @param int $user_id  User.
	 * @throws \DomainException When not allowed or not open.
	 */
	private function open_claim( int $claim_id, int $user_id ): Claim {
		if ( $user_id < 1 || ! user_can( $user_id, self::CAPABILITY ) ) {
			throw new \DomainException( 'You are not allowed to decide claims.' );
		}
		$claim = $this->claims->find( $claim_id );
		if ( null === $claim ) {
			throw new \DomainException( 'There is no such claim.' );
		}
		if ( ! $claim->is_open() ) {
			throw new \DomainException( 'Only a new claim can be decided.' );
		}
		return $claim;
	}

	/**
	 * Audit subject.
	 *
	 * @param Claim $claim Claim.
	 * @return array{type: string, id: int}
	 */
	private function subject( Claim $claim ): array {
		return array(
			'type' => 'claim',
			'id'   => $claim->id,
		);
	}

	/**
	 * Email the claimant; the address is not kept in the job.
	 *
	 * @param Claim  $claim  Claim.
	 * @param string $lead   Opening line.
	 * @param string $reason Reason, for a rejection.
	 */
	private function tell( Claim $claim, string $lead, string $reason ): void {
		$this->notifier->send_to_record(
			Events::CLAIM_DECISION,
			ClaimIntake::RECIPIENT_SOURCE,
			$claim->id,
			array(
				'reference' => $claim->reference,
				'lead'      => $lead,
				'reason'    => $reason,
			)
		);
	}
}
