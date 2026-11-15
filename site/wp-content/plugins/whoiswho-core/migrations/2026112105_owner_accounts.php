<?php
/**
 * Profile owner accounts (REQ-274, REQ-253, REQ-261): the wp-admin My profiles screen is gone, so its capability
 * wiw_edit_assigned_profiles is taken from every role and from every user it was granted to one by one. The Profile owner role and
 * its wiw_own_profiles capability are created by the capability sync (catalogue version 7). Profiles already assigned to a staff
 * user keep that assignment; the user just no longer has the screen. Forward only and safe to run again. Only counts are logged.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;

return new class() implements MigrationInterface {

	/**
	 * The withdrawn capability.
	 */
	private const OLD = 'wiw_edit_assigned_profiles';

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026112105';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Profile owner accounts: remove the withdrawn My profiles capability and create the Profile owner role';
	}

	/**
	 * Remove the old capability from roles and users, then sync the catalogue so the Profile owner role exists.
	 */
	public function up(): void {
		$roles = 0;
		foreach ( wp_roles()->role_objects as $role ) {
			if ( $role->has_cap( self::OLD ) || isset( $role->capabilities[ self::OLD ] ) ) {
				$role->remove_cap( self::OLD );
				++$roles;
			}
		}
		$users = 0;
		foreach ( get_users( array( 'fields' => 'all' ) ) as $user ) {
			if ( isset( $user->caps[ self::OLD ] ) ) {
				$user->remove_cap( self::OLD );
				++$users;
			}
		}
		( new RoleSync( new CapabilityCatalogue() ) )->maybe_sync();
		Logger::for_site()->log(
			'info',
			'Withdrawn My profiles capability removed',
			array(
				'roles_count' => $roles,
				'users_count' => $users,
			)
		);
	}

	/**
	 * Not a schema contraction.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * No personal-data columns.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
