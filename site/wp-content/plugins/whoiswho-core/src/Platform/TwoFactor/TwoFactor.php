<?php
/**
 * Two-factor setup (REQ-047, REQ-235). The Two Factor plugin from wordpress.org is pinned through composer and loaded by this
 * plugin (as Action Scheduler is), so it cannot be deactivated from wp-admin and its settings are applied by code, not by hand:
 * authenticator app, email code and recovery codes are on; the debug-only Dummy method is always off.
 *
 * Two-factor is available and recommended for staff, not enforced (owner decision 2026-11-14, D-070): nobody is redirected or
 * blocked for not having it. A staff member who switches it on is asked for the second step at every sign-in, by the plugin.
 * The Setup screen lists which staff have it on (StaffStatus), and staff without it get a reminder (Reminder).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\TwoFactor;

/**
 * Two-factor setup.
 */
final class TwoFactor {

	public const OPTION_APPLIED = 'wiw_two_factor_settings';
	public const SETTINGS_REV   = '1';

	/**
	 * Providers staff may use: an authenticator app, an email code and recovery codes.
	 */
	public const PROVIDERS = array( 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' );

	/**
	 * Load the plugin from the plugins folder. Called from the core plugin file, before plugins_loaded.
	 *
	 * @param string $plugins_dir Plugins folder.
	 */
	public static function load( string $plugins_dir ): void {
		$file = rtrim( $plugins_dir, '/\\' ) . '/two-factor/two-factor.php';
		if ( ! class_exists( '\Two_Factor_Core', false ) && is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Attach hooks.
	 */
	public static function register(): void {
		add_filter( 'two_factor_providers', array( self::class, 'filter_providers' ) );
		add_action( 'init', array( self::class, 'apply_settings' ) );
		TestMode::register();
		Reminder::register();
	}

	/**
	 * Keep only the approved providers (this removes the debug-only Dummy method).
	 *
	 * @param mixed $providers Provider class name to file path.
	 * @return array<string,string>
	 */
	public static function filter_providers( $providers ): array {
		$providers = is_array( $providers ) ? $providers : array();
		return array_intersect_key( $providers, array_flip( self::PROVIDERS ) );
	}

	/**
	 * Write the plugin's settings once per revision.
	 */
	public static function apply_settings(): void {
		if ( ! class_exists( '\Two_Factor_Core' ) || self::SETTINGS_REV === get_option( self::OPTION_APPLIED ) ) {
			return;
		}
		update_option( \Two_Factor_Core::ENABLED_PROVIDERS_OPTION_KEY, self::PROVIDERS );
		update_option( self::OPTION_APPLIED, self::SETTINGS_REV, false );
	}
}
