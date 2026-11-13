<?php
/**
 * Activation and deactivation service. Later issues extend it (migrations, roles). Deactivation removes no data.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform;

/**
 * Runs on activation and deactivation.
 */
final class Activation {

	/**
	 * On activation: roles, then the first-run setup (tables, permalinks, legal pages, the redirect to the Setup screen).
	 */
	public static function activate(): void {
		update_option( 'wiw_core_version', WIW_CORE_VERSION, false );
		( new Capabilities\RoleSync( new Capabilities\CapabilityCatalogue() ) )->activate();
		// A plain WordPress has no deploy script and its owner may not code: a first install creates its tables here, in any
		// environment; an existing production schema is changed only by the deploy script. The filter lets tests switch it off.
		if ( apply_filters( 'wiw_migrate_on_activation', true ) ) {
			Setup\FirstRun::activate();
		}
	}

	/**
	 * On deactivation: deliberately removes nothing (REQ-056).
	 */
	public static function deactivate(): void {
	}
}
