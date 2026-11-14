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

		// The built-in theme and plugin code editors are off for everyone: the effect of DISALLOW_FILE_EDIT without editing
		// wp-config (owner decision 2026-11-14, issue 813).
		add_filter( 'map_meta_cap', array( self::class, 'deny_code_editors' ), 10, 2 );

		add_action( 'admin_notices', array( self::class, 'config_notice' ) );
	}

	/**
	 * Capabilities that open the built-in code editors.
	 */
	public const CODE_EDITOR_CAPS = array( 'edit_themes', 'edit_plugins', 'edit_files' );

	/**
	 * Name of the advice in WordPress's own per-user list of dismissed notices (user meta dismissed_wp_pointers, written by
	 * core's dismiss-wp-pointer request). The guard registers no action of its own.
	 */
	public const DISMISS_ID = 'wiw_guard_uploads_advice';

	/**
	 * Remove the code editors whatever the role or grant says.
	 *
	 * @param string[] $caps Primitive capabilities.
	 * @param string   $cap  Meta capability being checked.
	 * @return string[]
	 */
	public static function deny_code_editors( array $caps, string $cap ): array {
		if ( in_array( $cap, self::CODE_EDITOR_CAPS, true ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
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
	 * Whether to show the plain-language uploads advice. The code editors are always off (deny_code_editors), so only one thing
	 * is worth saying: on a live site where plugin and theme uploads are allowed, keep Administrator accounts few and protected.
	 * A site that sets DISALLOW_FILE_MODS has nothing to advise.
	 *
	 * @param bool      $production      Production mode.
	 * @param bool|null $file_mods_value Value of DISALLOW_FILE_MODS, or null when not defined.
	 */
	public static function wants_uploads_advice( bool $production, ?bool $file_mods_value ): bool {
		return $production && true !== $file_mods_value;
	}

	/**
	 * Advice for Administrators, on the Dashboard only, until they dismiss it.
	 */
	public static function config_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || 'dashboard' !== $screen->id ) {
			return;
		}
		if ( self::is_dismissed( (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) ) {
			return;
		}
		$advise = self::wants_uploads_advice(
			'production' === wp_get_environment_type(),
			defined( 'DISALLOW_FILE_MODS' ) ? (bool) constant( 'DISALLOW_FILE_MODS' ) : null
		);
		if ( ! $advise ) {
			return;
		}
		echo '<div class="notice notice-info" id="' . esc_attr( self::DISMISS_ID ) . '"><p>'
			. esc_html__( 'Good to know: you can install and update plugins and themes from wp-admin, and the built-in code editor is switched off for safety. Keep the number of Administrator accounts small and ask each one to turn on two-factor sign-in.', 'whoiswho' )
			. ' <button type="button" class="button-link" data-wiw-dismiss="' . esc_attr( self::DISMISS_ID ) . '">' . esc_html__( 'Got it, hide this', 'whoiswho' ) . '</button></p></div>';
		$script = 'document.addEventListener("click",function(e){var b=e.target.closest("[data-wiw-dismiss]");if(!b||!window.jQuery||!window.ajaxurl){return;}'
			. 'jQuery.post(window.ajaxurl,{action:"dismiss-wp-pointer",pointer:b.getAttribute("data-wiw-dismiss")});'
			. 'var n=document.getElementById(b.getAttribute("data-wiw-dismiss"));if(n){n.remove();}});';
		wp_print_inline_script_tag( $script );
	}

	/**
	 * Whether the advice is in a person's dismissed list (core's comma-separated dismissed_wp_pointers).
	 *
	 * @param string $dismissed Stored list.
	 */
	public static function is_dismissed( string $dismissed ): bool {
		return in_array( self::DISMISS_ID, array_map( 'trim', explode( ',', $dismissed ) ), true );
	}
}
