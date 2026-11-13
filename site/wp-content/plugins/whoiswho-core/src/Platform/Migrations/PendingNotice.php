<?php
/**
 * Admin notice shown on the Dashboard and Plugins screens while the database schema is behind the plugin (REQ-056): on a plain
 * WordPress the tables are created when the plugin is activated, and this notice says what to do when that did not happen.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Migrations;

/**
 * Pending-migrations notice.
 */
final class PendingNotice {

	/**
	 * Attach the hook.
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'print_notice' ) );
	}

	/**
	 * Print the notice to people who can manage plugins, on the screens where it helps.
	 */
	public static function print_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html().
		echo self::html( Runner::for_site(), wp_get_environment_type() );
	}

	/**
	 * The notice markup, or an empty string when the schema is up to date.
	 *
	 * @param Runner $runner      Runner.
	 * @param string $environment Environment type.
	 */
	public static function html( Runner $runner, string $environment ): string {
		try {
			$pending = $runner->pending();
		} catch ( \Throwable $e ) {
			return '';
		}
		if ( array() === $pending ) {
			return '';
		}
		if ( 'production' === $environment ) {
			$text = __( 'The core plugin has database changes waiting. In production they are applied only by the deploy script. Run the deploy, or follow the install steps for a first install.', 'whoiswho' );
		} else {
			$text = __( 'The core plugin has database changes waiting. Deactivate and activate the plugin again, or run wp wiw migrate (or php bin/wiw.php migrate).', 'whoiswho' );
		}
		return '<div class="notice notice-warning wiw-schema-notice"><p>' . esc_html( $text ) . '</p></div>';
	}
}
