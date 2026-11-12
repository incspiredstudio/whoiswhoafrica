<?php
/**
 * Theme settings screen (REQ-272, REQ-188): the Sprint tracking switch, which shows or hides the dashboard sprint card and the
 * Sprint thresholds screen. The value lives in the plugin's settings service (key sprint_tracking, default on), so the plugin reads it
 * without depending on the theme; only an Administrator changes it and every change is written to the audit log by the service.
 * Nothing is deleted when it is off. Brand values are not settings and never appear here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Settings\SettingsService;

const WIW_THEME_SETTINGS_SCREEN = 'wiw-theme-settings';
const WIW_THEME_SETTINGS_ACTION = 'wiw_save_theme_settings';
const WIW_THEME_SETTINGS_CAP    = 'manage_options';

if ( ! function_exists( 'wiw_sprint_tracking_enabled' ) ) {
	/**
	 * Whether sprint tracking is on; true when the plugin is not there to say otherwise.
	 */
	function wiw_sprint_tracking_enabled(): bool {
		return ! class_exists( SettingsService::class ) || SettingsService::for_site()->sprint_tracking_enabled();
	}
}

if ( ! function_exists( 'wiw_theme_settings_html' ) ) {
	/**
	 * The screen markup. The form appears only for a user who may change the setting.
	 *
	 * @param bool   $on        Current value.
	 * @param bool   $can_edit  Whether the user may change it.
	 * @param string $nonce   Nonce for the save action.
	 */
	function wiw_theme_settings_html( bool $on, bool $can_edit, string $nonce ): string {
		$out  = '<div class="wrap"><h1>' . esc_html__( 'Theme settings', 'whoiswho' ) . '</h1>';
		$out .= '<h2>' . esc_html__( 'Sprint tracking', 'whoiswho' ) . '</h2>';
		$out .= '<p>' . esc_html__( 'Shows or hides the sprint card on the Administrator dashboard and the Sprint thresholds screen. Nothing is deleted: the thresholds are kept while it is off.', 'whoiswho' ) . '</p>';
		$out .= '<p>' . esc_html( $on ? __( 'Sprint tracking is on.', 'whoiswho' ) : __( 'Sprint tracking is off.', 'whoiswho' ) ) . '</p>';
		if ( ! $can_edit ) {
			return $out . '<p>' . esc_html__( 'Only an Administrator can change this.', 'whoiswho' ) . '</p></div>';
		}
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( WIW_THEME_SETTINGS_ACTION ) . '" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';
		$out .= '<input type="hidden" name="sprint_tracking" value="' . ( $on ? '0' : '1' ) . '" />';
		$out .= '<p><button type="submit" class="button button-primary">' . esc_html( $on ? __( 'Turn sprint tracking off', 'whoiswho' ) : __( 'Turn sprint tracking on', 'whoiswho' ) ) . '</button></p>';
		return $out . '</form></div>';
	}
}//end if

add_action(
	'init',
	static function (): void {
		if ( ! is_admin() || ! class_exists( ScreenRegistry::class ) ) {
			return;
		}
		$registry = ScreenRegistry::shared();
		$registry->register(
			WIW_THEME_SETTINGS_SCREEN,
			__( 'Theme settings', 'whoiswho' ),
			WIW_THEME_SETTINGS_CAP,
			static function (): void {
				echo wp_kses(
					wiw_theme_settings_html( wiw_sprint_tracking_enabled(), current_user_can( WIW_THEME_SETTINGS_CAP ), ActionGuard::nonce_for( WIW_THEME_SETTINGS_ACTION ) ),
					array(
						'div'    => array( 'class' => true ),
						'h1'     => array(),
						'h2'     => array(),
						'p'      => array(),
						'form'   => array(
							'method' => true,
							'action' => true,
						),
						'input'  => array(
							'type'  => true,
							'name'  => true,
							'value' => true,
						),
						'button' => array(
							'type'  => true,
							'class' => true,
						),
					)
				);
			}
		);
		$registry->hook();
		ActionGuard::register(
			WIW_THEME_SETTINGS_ACTION,
			WIW_THEME_SETTINGS_CAP,
			static function ( Request $request ): void {
				SettingsService::for_site()->set( SettingsService::SPRINT_TRACKING, '1' === $request->string( 'sprint_tracking' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=' . WIW_THEME_SETTINGS_SCREEN ) );
				exit;
			}
		);
	},
	2
);
