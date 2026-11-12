<?php
/**
 * The profile manager of a claimed profile (REQ-253, REQ-150): one ordinary staff user per profile, set by a person who may review
 * claims and recorded in the audit log. Only a claimed profile has a manager, and only staff accounts can be picked, never a public
 * account. The manager is a staff-side note and is never shown on a public surface.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Profile manager service.
 */
final class ProfileManager {

	public const STAFF_CAPABILITY = 'read_private_wiw_profiles';

	/**
	 * Profile state rows.
	 *
	 * @var StateRepository
	 */
	private StateRepository $states;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param StateRepository|null $states State rows.
	 * @param AuditLogger|null     $audit  Audit logger.
	 */
	public function __construct( ?StateRepository $states = null, ?AuditLogger $audit = null ) {
		$this->states = $states ?? new StateRepository();
		$this->audit  = $audit ?? AuditLogger::for_site();
	}

	/**
	 * The assigned user, or null.
	 *
	 * @param int $profile_id Profile id.
	 */
	public function assigned( int $profile_id ): ?int {
		$state = $this->states->get( $profile_id );
		$id    = null === $state ? 0 : (int) ( $state['assigned_user_id'] ?? 0 );
		return $id > 0 ? $id : null;
	}

	/**
	 * Ordinary staff users as id => display name: accounts that can see private profiles (so no public account appears).
	 *
	 * @return array<int, string>
	 */
	public function staff_choices(): array {
		$out   = array();
		$users = get_users(
			array(
				'role__in' => array( 'administrator', 'wiw_editor', 'wiw_reviewer' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'fields'   => array( 'ID', 'display_name' ),
			)
		);
		foreach ( $users as $user ) {
			if ( user_can( (int) $user->ID, self::STAFF_CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
				$out[ (int) $user->ID ] = (string) $user->display_name;
			}
		}
		return $out;
	}

	/**
	 * Assign, change or clear (user id 0) the manager of a claimed profile.
	 *
	 * @param int $profile_id Profile id.
	 * @param int $user_id    Staff user, or 0 to unassign.
	 * @param int $actor_id   Person doing it; must hold wiw_review_claims.
	 * @throws \DomainException When not allowed, the profile is not claimed, or the user is not staff.
	 */
	public function assign( int $profile_id, int $user_id, int $actor_id ): void {
		if ( $actor_id < 1 || ! user_can( $actor_id, ClaimDecision::CAPABILITY ) ) {
			throw new \DomainException( 'You are not allowed to assign a profile manager.' );
		}
		$state = $this->states->get( $profile_id );
		if ( null === $state || empty( $state['claimed'] ) ) {
			throw new \DomainException( 'Only a claimed profile has a manager.' );
		}
		if ( $user_id > 0 && ! array_key_exists( $user_id, $this->staff_choices() ) ) {
			throw new \DomainException( 'The manager must be a staff user.' );
		}
		$before = $this->assigned( $profile_id );
		$after  = $user_id > 0 ? $user_id : null;
		if ( $before === $after ) {
			return;
		}
		$this->states->update( $profile_id, array( 'assigned_user_id' => $after ) );
		$this->audit->log(
			'profile.assign_manager',
			array(
				'type' => 'profile',
				'id'   => $profile_id,
			),
			array( 'assigned_user_id' => $before ),
			array( 'assigned_user_id' => $after ),
			null === $after ? 'Manager removed' : 'Manager assigned'
		);
	}
}
