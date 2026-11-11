<?php
/**
 * Resolves a staff audience to email addresses: holders of a named capability, or Administrators. Users marked deactivated
 * (user meta wiw_deactivated_at, set by the staff deactivation feature) are excluded.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail;

/**
 * Staff audience.
 */
final class StaffAudience {

	public const DEACTIVATED_META = 'wiw_deactivated_at';

	/**
	 * Addresses for an audience.
	 *
	 * @param string $audience "capability:<name>" or "administrators".
	 * @return array<int, string>
	 */
	public static function addresses( string $audience ): array {
		if ( 'administrators' === $audience ) {
			$args = array( 'role' => 'administrator' );
		} elseif ( 0 === strpos( $audience, 'capability:' ) ) {
			$args = array( 'capability' => substr( $audience, 11 ) );
		} else {
			return array();
		}
		$args['fields'] = array( 'ID', 'user_email' );
		$out            = array();
		foreach ( get_users( $args ) as $user ) {
			if ( '' !== (string) get_user_meta( (int) $user->ID, self::DEACTIVATED_META, true ) ) {
				continue;
			}
			$out[] = (string) $user->user_email;
		}
		return $out;
	}
}
