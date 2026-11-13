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
	 * On activation: later issues add migrations and roles here.
	 */
	public static function activate(): void {
		update_option( 'wiw_core_version', WIW_CORE_VERSION, false );
		( new Capabilities\RoleSync( new Capabilities\CapabilityCatalogue() ) )->activate();
		// A plain WordPress has no deploy script: create the tables now. Refused in production; the filter lets tests switch it off.
		if ( apply_filters( 'wiw_migrate_on_activation', true ) ) {
			Migrations\ActivationMigrator::run( Migrations\Runner::for_site() );
			Permalinks::ensure_pretty();
		}
	}

	/**
	 * On deactivation: deliberately removes nothing (REQ-056).
	 */
	public static function deactivate(): void {
	}
}
