<?php
/**
 * Loads the staff UI kit's stylesheet and script (REQ-275) on WHOISWHO admin screens only: the screens registered through
 * ScreenRegistry, whose hook suffix ends in _page_wiw or _page_wiw-{slug}. The kit uses WordPress admin colours and neutrals only
 * (the admin colour scheme's --wp-admin-theme-color); no brand values live in the plugin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Admin\Ui;

/**
 * Staff UI assets.
 */
final class StaffAssets {

	/**
	 * Attach hooks.
	 */
	public static function hooks(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
	}

	/**
	 * Whether an admin hook suffix is a WHOISWHO screen.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function is_staff_screen( string $hook ): bool {
		return 1 === preg_match( '/_page_wiw(-[a-z0-9-]+)?$/', $hook );
	}

	/**
	 * Enqueue the kit on WHOISWHO screens.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function enqueue( $hook ): void {
		if ( ! self::is_staff_screen( (string) $hook ) ) {
			return;
		}
		wp_enqueue_style( 'wiw-staff-ui', plugins_url( 'assets/admin/ui/staff.css', WIW_CORE_FILE ), array( 'common', 'forms', 'list-tables' ), WIW_CORE_VERSION );
		wp_enqueue_script( 'wiw-staff-ui', plugins_url( 'assets/admin/ui/staff.js', WIW_CORE_FILE ), array(), WIW_CORE_VERSION, true );
		wp_enqueue_script( 'wiw-char-count', plugins_url( 'assets/admin/directory/char-count.js', WIW_CORE_FILE ), array(), WIW_CORE_VERSION, true );
	}

	/**
	 * Mark WHOISWHO screens on the body so the kit's styles can reach the page frame.
	 *
	 * @param string $classes Body classes.
	 */
	public static function body_class( $classes ): string {
		global $hook_suffix;
		return is_string( $hook_suffix ) && self::is_staff_screen( $hook_suffix ) ? trim( (string) $classes . ' wiw-staff-screen' ) : (string) $classes;
	}
}
