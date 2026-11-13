<?php
/**
 * Pretty permalinks (REQ-056). The public routes (directory, awards, stories, events) need them, and a new WordPress starts with
 * plain ones, so activation sets "/%postname%/" when the site still has the plain structure. A structure someone chose is never changed.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform;

/**
 * Permalinks.
 */
final class Permalinks {

	public const STRUCTURE = '/%postname%/';

	/**
	 * Set the pretty structure when the site uses the plain one.
	 *
	 * @return bool Whether it was changed.
	 */
	public static function ensure_pretty(): bool {
		global $wp_rewrite;
		if ( '' !== (string) get_option( 'permalink_structure' ) || ! $wp_rewrite instanceof \WP_Rewrite ) {
			return false;
		}
		$wp_rewrite->set_permalink_structure( self::STRUCTURE );
		// The rules are rebuilt, with every plugin's rules, on the next request.
		delete_option( 'rewrite_rules' );
		return true;
	}
}
