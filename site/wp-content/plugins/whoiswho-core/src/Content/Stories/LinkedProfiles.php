<?php
/**
 * Profiles a story links to. The picker offers public profiles only (REQ-028). The Directory module supplies the public
 * profiles through the filter, from its PublicProfile read model, so this module never queries the profile type itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

/**
 * Linked profiles.
 */
final class LinkedProfiles {

	public const META   = 'wiw_linked_profiles';
	public const FILTER = 'wiw_public_profile_choices';

	/**
	 * The public profiles that can be linked, as id => display name. Empty until the Directory module supplies them.
	 *
	 * @return array<int, string>
	 */
	public static function choices(): array {
		$choices = apply_filters( self::FILTER, array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the constant holds a wiw_ name.
		if ( ! is_array( $choices ) ) {
			return array();
		}
		$out = array();
		foreach ( $choices as $id => $name ) {
			if ( is_int( $id ) && is_string( $name ) ) {
				$out[ $id ] = $name;
			}
		}
		return $out;
	}

	/**
	 * Keep only ids that are public profiles, without duplicates.
	 *
	 * @param array<int|string, mixed> $ids Submitted ids.
	 * @return array<int, int>
	 */
	public static function sanitise( array $ids ): array {
		$allowed = self::choices();
		$out     = array();
		foreach ( $ids as $id ) {
			if ( is_scalar( $id ) && ctype_digit( (string) $id ) && isset( $allowed[ (int) $id ] ) ) {
				$out[ (int) $id ] = (int) $id;
			}
		}
		return array_values( $out );
	}
}
