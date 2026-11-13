<?php
/**
 * Whether the site has a database schema from this plugin yet (REQ-056). A new install has nothing to protect, so it may create its
 * tables from the browser in any environment; once the schema exists, production changes it only through the deploy script.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Platform\Migrations\Runner;

/**
 * Schema state.
 */
final class SchemaState {

	/**
	 * Set while a first install is between its first and last migration, so a run that stopped halfway can be resumed in production.
	 */
	public const OPTION_STARTED = 'wiw_setup_schema_started';

	/**
	 * A plain-language reason the last first-run attempt stopped, or empty.
	 */
	public const OPTION_ERROR = 'wiw_setup_error';

	/**
	 * Whether the plugin has made nothing here yet: no schema version, no applied list and none of its tables.
	 */
	public static function is_fresh(): bool {
		return '0' === (string) get_option( Runner::OPTION_VERSION, '0' )
			&& false === get_option( Runner::OPTION_APPLIED )
			&& ! self::tables_exist();
	}

	/**
	 * Whether the browser may run the migrations here in any environment: a fresh install, or a first install that stopped halfway.
	 */
	public static function may_self_initialise(): bool {
		return self::is_fresh() || (bool) get_option( self::OPTION_STARTED, false );
	}

	/**
	 * Whether any table with this plugin's prefix exists.
	 */
	public static function tables_exist(): bool {
		global $wpdb;
		$like = $wpdb->esc_like( $wpdb->prefix . 'wiw_' ) . '%';
		return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a one-off schema check.
	}
}
