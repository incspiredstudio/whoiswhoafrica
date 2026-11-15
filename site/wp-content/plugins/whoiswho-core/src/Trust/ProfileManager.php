<?php
/**
 * The owner of a claimed profile (REQ-274, REQ-253, REQ-150): one Profile owner account per profile, set by a person who may review
 * claims (on approval with "Approve and create account", or later on the profile screen to transfer or remove it) and recorded in
 * the audit log. Only a claimed profile has an owner, only a Profile owner account can be picked, and one account may own several
 * profiles. The owner is never shown on a public surface.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Trust\Account\OwnerRole;

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
	 * Profile owner accounts as id => display name, for the owner picker.
	 *
	 * @return array<int, string>
	 */
	public function owner_choices(): array {
		$out   = array();
		$users = get_users(
			array(
				'role'    => OwnerRole::ROLE,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name' ),
			)
		);
		foreach ( $users as $user ) {
			$out[ (int) $user->ID ] = (string) $user->display_name;
		}
		return $out;
	}

	/**
	 * The profiles a user owns, by id.
	 *
	 * @param int $user_id User.
	 * @return int[]
	 */
	public function owned( int $user_id ): array {
		global $wpdb;
		if ( $user_id < 1 ) {
			return array();
		}
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT profile_id FROM ' . StateRepository::table() . ' WHERE assigned_user_id = %d ORDER BY profile_id ASC', $user_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * Assign, transfer or remove (user id 0) the owner of a claimed profile.
	 *
	 * @param int $profile_id Profile id.
	 * @param int $user_id    Profile owner account, or 0 to remove the owner.
	 * @param int $actor_id   Person doing it; must hold wiw_review_claims.
	 * @throws \DomainException When not allowed, the profile is not claimed, or the user is not a Profile owner account.
	 */
	public function assign( int $profile_id, int $user_id, int $actor_id ): void {
		if ( $actor_id < 1 || ! user_can( $actor_id, ClaimDecision::CAPABILITY ) ) {
			throw new \DomainException( 'You are not allowed to change a profile owner.' );
		}
		$state = $this->states->get( $profile_id );
		if ( null === $state || empty( $state['claimed'] ) ) {
			throw new \DomainException( 'Only a claimed profile has an owner.' );
		}
		$before = $this->assigned( $profile_id );
		$after  = $user_id > 0 ? $user_id : null;
		if ( $before === $after ) {
			return;
		}
		if ( null !== $after && ! OwnerRole::is_owner( $after ) ) {
			throw new \DomainException( 'The owner must be a Profile owner account.' );
		}
		$this->states->update( $profile_id, array( 'assigned_user_id' => $after ) );
		$this->audit->log(
			'profile.assign_owner',
			array(
				'type' => 'profile',
				'id'   => $profile_id,
			),
			array( 'assigned_user_id' => $before ),
			array( 'assigned_user_id' => $after ),
			null === $after ? 'Owner removed' : ( null === $before ? 'Owner assigned' : 'Ownership transferred' )
		);
	}
}
