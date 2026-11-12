<?php
/**
 * Hardening that cannot be switched off from wp-admin or the database: registration, XML-RPC, application passwords, automatic
 * updates and file modification. The wp-config template sets the constants; this class forces the rest on every request.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Hardening;

/**
 * Hardening hooks.
 */
final class Hardening {

	/**
	 * Attach hooks.
	 */
	public static function init(): void {
		// Registration closed and no default role, whatever the database says (REQ-229).
		add_filter( 'pre_option_users_can_register', '__return_zero' );
		add_filter( 'pre_option_default_role', '__return_empty_string' );

		// XML-RPC and pingbacks off.
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', array( self::class, 'strip_xmlrpc_methods' ) );
		add_filter( 'pings_open', '__return_false', 99 );
		add_filter( 'wp_headers', array( self::class, 'remove_pingback_header' ) );
		add_action( 'plugins_loaded', array( self::class, 'refuse_xmlrpc' ), 0 );

		// Application passwords off.
		add_filter( 'wp_is_application_passwords_available', '__return_false' );

		// Automatic updates off for core, plugins and themes, and translations.
		add_filter( 'automatic_updater_disabled', '__return_true' );
		add_filter( 'auto_update_core', '__return_false' );
		add_filter( 'auto_update_plugin', '__return_false' );
		add_filter( 'auto_update_theme', '__return_false' );
		add_filter( 'auto_update_translation', '__return_false' );
		add_filter( 'allow_major_auto_core_updates', '__return_false' );
		add_filter( 'allow_minor_auto_core_updates', '__return_false' );

		// No sign-in or sign-up link for visitors. wp-login.php stays reachable for staff.
		add_filter( 'loginout', '__return_empty_string' );
		add_filter( 'register', '__return_empty_string' );

		// Do not advertise the PHP version (X-Powered-By).
		self::strip_powered_by();
		add_action( 'send_headers', array( self::class, 'strip_powered_by' ), 99 );
		add_action( 'admin_init', array( self::class, 'strip_powered_by' ), 99 );
		add_action( 'login_init', array( self::class, 'strip_powered_by' ), 99 );

		add_action( 'admin_notices', array( self::class, 'config_notice' ) );
	}

	/**
	 * Remove every XML-RPC method.
	 *
	 * @param array<string, mixed> $methods Methods.
	 * @return array<string, mixed>
	 */
	public static function strip_xmlrpc_methods( array $methods ): array {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return array();
	}

	/**
	 * Drop the X-Pingback header.
	 *
	 * @param array<string, string> $headers Headers.
	 * @return array<string, string>
	 */
	public static function remove_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Remove the X-Powered-By header PHP adds, unless output has already started.
	 */
	public static function strip_powered_by(): void {
		if ( ! headers_sent() ) {
			header_remove( 'X-Powered-By' );
		}
	}

	/**
	 * Answer 403 to any XML-RPC request before it is processed.
	 */
	public static function refuse_xmlrpc(): void {
		if ( defined( 'XMLRPC_REQUEST' ) ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			exit( 'Forbidden' );
		}
	}

	/**
	 * Whether the configuration disagrees with production hardening.
	 *
	 * @param bool      $production        Production mode.
	 * @param bool|null $file_mods_value   Value of DISALLOW_FILE_MODS, or null when not defined.
	 * @param bool|null $file_edit_value   Value of DISALLOW_FILE_EDIT, or null when not defined.
	 * @return string[] Plain descriptions of what is wrong; empty when all is well.
	 */
	public static function config_problems( bool $production, ?bool $file_mods_value, ?bool $file_edit_value ): array {
		$problems = array();
		if ( $production && true !== $file_mods_value ) {
			$problems[] = 'DISALLOW_FILE_MODS is not true.';
		}
		if ( true !== $file_edit_value ) {
			$problems[] = 'DISALLOW_FILE_EDIT is not true.';
		}
		return $problems;
	}

	/**
	 * Staff notice when the configuration disagrees.
	 */
	public static function config_notice(): void {
		$problems = self::config_problems(
			'production' === wp_get_environment_type(),
			defined( 'DISALLOW_FILE_MODS' ) ? (bool) constant( 'DISALLOW_FILE_MODS' ) : null,
			defined( 'DISALLOW_FILE_EDIT' ) ? (bool) constant( 'DISALLOW_FILE_EDIT' ) : null
		);
		if ( array() === $problems || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Security configuration problem:', 'whoiswho' ) . ' ' . esc_html( implode( ' ', $problems ) ) . '</p></div>';
	}
}
