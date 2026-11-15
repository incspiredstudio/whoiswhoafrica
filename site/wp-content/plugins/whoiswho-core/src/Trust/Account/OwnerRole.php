<?php
/**
 * The Profile owner account (REQ-274, REQ-253, REQ-261): a customer-style account on the public site for a person whose claim was
 * approved. The role holds only read and wiw_own_profiles (config/capabilities.php), so it has no wp-admin capability; OwnerAccess
 * keeps it out of wp-admin altogether. Accounts are created only by staff (public registration stays closed).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust\Account;

/**
 * Profile owner role names and checks.
 */
final class OwnerRole {

	public const ROLE       = 'wiw_profile_owner';
	public const CAPABILITY = 'wiw_own_profiles';

	/**
	 * Whether a user holds the Profile owner role.
	 *
	 * @param int $user_id User.
	 */
	public static function is_owner( int $user_id ): bool {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		return $user instanceof \WP_User && in_array( self::ROLE, (array) $user->roles, true );
	}

	/**
	 * Whether the Profile owner role is the user's only role: such a user never reaches wp-admin.
	 *
	 * @param int $user_id User.
	 */
	public static function is_owner_only( int $user_id ): bool {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		return $user instanceof \WP_User && array( self::ROLE ) === array_values( (array) $user->roles );
	}

	/**
	 * The public account address, with an optional path below it.
	 *
	 * @param string $path Path below /account/, for example "details/".
	 */
	public static function url( string $path = '' ): string {
		return home_url( '/account/' . ltrim( $path, '/' ) );
	}
}
