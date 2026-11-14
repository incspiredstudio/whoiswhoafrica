<?php
/**
 * A reminder on every admin page while a required plugin is not active (REQ-056): Administrators only, with a link to Settings >
 * Required plugins. Each Administrator can hide it for seven days; it comes back after that while a plugin is still missing.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Plugins;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Missing plugins notice.
 */
final class MissingPluginsNotice {

	public const ACTION    = 'wiw_plugins_notice_dismiss';
	public const META      = 'wiw_plugins_notice_until';
	public const DAYS      = 7;
	public const NOTICE_ID = 'wiw-required-plugins-notice';

	/**
	 * Attach hooks.
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
		add_action(
			'init',
			static function (): void {
				if ( is_admin() ) {
					ActionGuard::register( self::ACTION, RequiredPluginsScreen::CAP, array( self::class, 'handle_dismiss' ) );
				}
			},
			1
		);
	}

	/**
	 * Print the notice where it applies.
	 */
	public static function render(): void {
		echo self::html( get_current_user_id(), Request::from_globals()->string( 'page' ), new RequiredPlugins(), time() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
	}

	/**
	 * The notice markup for a user, or empty when it does not apply.
	 *
	 * @param int             $user_id User.
	 * @param string          $page    The admin page being shown (the screen itself shows no notice).
	 * @param RequiredPlugins $plugins Status reader.
	 * @param int             $now     Current time.
	 */
	public static function html( int $user_id, string $page, RequiredPlugins $plugins, int $now ): string {
		if ( $user_id < 1 || ! user_can( $user_id, RequiredPluginsScreen::CAP ) || RequiredPluginsScreen::SCREEN === $page ) {
			return '';
		}
		if ( (int) get_user_meta( $user_id, self::META, true ) > $now ) {
			return '';
		}
		$missing = $plugins->missing_required();
		if ( array() === $missing ) {
			return '';
		}
		$names = array();
		foreach ( $missing as $slug ) {
			$names[] = RequiredPlugins::catalogue()[ $slug ]['name'];
		}
		$text = sprintf(
			/* translators: %s: plugin names, comma separated. */
			_n( 'The site needs a free plugin that is not active yet: %s.', 'The site needs free plugins that are not active yet: %s.', count( $names ), 'whoiswho' ),
			implode( ', ', $names )
		);
		return '<div class="notice notice-warning" id="' . esc_attr( self::NOTICE_ID ) . '"><p>' . esc_html( $text ) . ' '
			. '<a class="button button-primary" href="' . esc_url( RequiredPluginsScreen::url() ) . '">' . esc_html__( 'Set up the plugins', 'whoiswho' ) . '</a> '
			. '<form method="post" style="display:inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />'
			. '<button type="submit" class="button-link">' . esc_html__( 'Remind me in 7 days', 'whoiswho' ) . '</button></form></p></div>';
	}

	/**
	 * Hide the notice for this user for seven days.
	 *
	 * @param int $user_id User.
	 * @param int $now     Current time.
	 */
	public static function dismiss( int $user_id, int $now ): void {
		update_user_meta( $user_id, self::META, (string) ( $now + self::DAYS * DAY_IN_SECONDS ) );
	}

	/**
	 * The dismiss button (admin-post; capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_dismiss( Request $request ): void {
		unset( $request );
		self::dismiss( get_current_user_id(), time() );
		$back = wp_get_referer();
		wp_safe_redirect( false !== $back ? $back : admin_url() );
		exit;
	}
}
