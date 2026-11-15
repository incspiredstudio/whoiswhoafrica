<?php
/**
 * Loads the Platform screens' small stylesheet (assets/admin/platform/screens.css, REQ-275) on the screens it serves, after the
 * staff UI kit: the Audit, Email and Purge logs, Required plugins and Sprint thresholds.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Admin;

use WhoIsWho\Core\Platform\Admin\Ui\StaffAssets;

/**
 * Platform screen styles.
 */
final class PlatformScreenStyles {

	/**
	 * The screens, by page slug.
	 */
	public const SCREENS = array( 'wiw-audit-log', 'wiw-email-log', 'wiw-purge-log', 'wiw-required-plugins', 'wiw-sprint-thresholds' );

	/**
	 * Attach hooks.
	 */
	public static function hooks(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ), 20 );
	}

	/**
	 * Enqueue on the listed screens.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function enqueue( $hook ): void {
		if ( ! StaffAssets::is_staff_screen( (string) $hook ) || ! in_array( Request::from_globals()->string( 'page' ), self::SCREENS, true ) ) {
			return;
		}
		wp_enqueue_style( 'wiw-platform-screens', plugins_url( 'assets/admin/platform/screens.css', WIW_CORE_FILE ), array( 'wiw-staff-ui' ), WIW_CORE_VERSION );
	}
}
