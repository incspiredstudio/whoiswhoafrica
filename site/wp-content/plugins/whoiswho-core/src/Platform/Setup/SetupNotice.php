<?php
/**
 * A plain-language admin notice when something prevents the plugin from setting itself up (REQ-056): an old PHP, a missing PHP
 * extension, a database without trigger support such as SQLite, or a first-run attempt that stopped. It says what is wrong and how
 * to fix it, and points to the Setup screen. Shown to people who can manage plugins, on every admin screen except the Setup screen
 * itself (which lists the same problems in its checklist).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Setup notice.
 */
final class SetupNotice {

	/**
	 * Attach the hook.
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'print_notice' ) );
	}

	/**
	 * Print the notice.
	 */
	public static function print_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) || SetupScreen::SCREEN === Request::from_globals()->string( 'page' ) ) {
			return;
		}
		echo self::html( SystemCheck::problems(), (string) get_option( SchemaState::OPTION_ERROR, '' ), current_user_can( SetupScreen::CAP ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html().
	}

	/**
	 * The notice markup, or an empty string when nothing is wrong.
	 *
	 * @param array<int, array{id: string, message: string, fix: string}> $problems     Problems from the system check.
	 * @param string                                                      $stored_error The reason the last first-run attempt stopped.
	 * @param bool                                                        $can_setup    Whether the user may open the Setup screen.
	 */
	public static function html( array $problems, string $stored_error, bool $can_setup ): string {
		if ( array() === $problems && '' === $stored_error ) {
			return '';
		}
		$out = '<div class="notice notice-error wiw-setup-notice"><p><strong>' . esc_html__( 'The site could not finish setting itself up.', 'whoiswho' ) . '</strong></p><ul>';
		foreach ( $problems as $problem ) {
			$out .= '<li>' . esc_html( $problem['message'] ) . ' ' . esc_html( $problem['fix'] ) . '</li>';
		}
		if ( array() === $problems ) {
			$out .= '<li>' . esc_html( $stored_error ) . '</li>';
		}
		$out .= '</ul>';
		if ( $can_setup ) {
			$out .= '<p><a href="' . esc_url( SetupScreen::url() ) . '">' . esc_html__( 'Open the Setup screen', 'whoiswho' ) . '</a></p>';
		}
		return $out . '</div>';
	}
}
