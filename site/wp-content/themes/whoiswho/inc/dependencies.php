<?php
/**
 * Required plugins (REQ-046, REQ-056). The theme needs the core plugin (whoiswho-core) and the guard must-use plugin (whoiswho-guard).
 * Like a commercial theme, it says so in wp-admin and offers a one-click Activate link; the public site shows a safe holding page
 * (see safe-mode.php) instead of a fatal error while the core plugin is missing. This file has no dependency on the plugin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WIW_CORE_PLUGIN_BASENAME' ) ) {
	define( 'WIW_CORE_PLUGIN_BASENAME', 'whoiswho-core/whoiswho-core.php' );
}

if ( ! function_exists( 'wiw_core_state' ) ) {
	/**
	 * State of the core plugin: active (loaded and started), inactive (installed, not active), missing (not installed) or
	 * broken (switched on but did not start, for example because PHP or WordPress is too old).
	 */
	function wiw_core_state(): string {
		if ( defined( 'WIW_CORE_VERSION' ) && class_exists( '\WhoIsWho\Core\Platform\Plugin' ) ) {
			return 'active';
		}
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . WIW_CORE_PLUGIN_BASENAME ) ) {
			return 'missing';
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		return in_array( WIW_CORE_PLUGIN_BASENAME, $active, true ) ? 'broken' : 'inactive';
	}
}

if ( ! function_exists( 'wiw_core_ready' ) ) {
	/**
	 * Whether the core plugin is loaded and started. The theme's own files load only when it is.
	 */
	function wiw_core_ready(): bool {
		return 'active' === wiw_core_state();
	}
}

if ( ! function_exists( 'wiw_guard_present' ) ) {
	/**
	 * Whether the guard must-use plugin is loaded.
	 */
	function wiw_guard_present(): bool {
		return defined( 'WIW_GUARD_VERSION' );
	}
}

if ( ! function_exists( 'wiw_activate_core_url' ) ) {
	/**
	 * The WordPress activation link for the core plugin. WordPress itself checks the nonce and the capability when it is followed.
	 */
	function wiw_activate_core_url(): string {
		return wp_nonce_url(
			self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( WIW_CORE_PLUGIN_BASENAME ) ),
			'activate-plugin_' . WIW_CORE_PLUGIN_BASENAME
		);
	}
}

if ( ! function_exists( 'wiw_dependency_items' ) ) {
	/**
	 * What is missing, as plain data (text is not yet escaped).
	 *
	 * @param string $core_state    Result of wiw_core_state().
	 * @param bool   $guard_present Whether the guard is loaded.
	 * @param bool   $can_activate  Whether the viewer may activate plugins.
	 * @param bool   $can_install   Whether the viewer may install plugins.
	 * @return array<int, array{key: string, text: string, url: string, label: string}>
	 */
	function wiw_dependency_items( string $core_state, bool $guard_present, bool $can_activate, bool $can_install ): array {
		$items = array();
		if ( 'active' !== $core_state ) {
			$item = array(
				'key'   => 'core',
				'text'  => '',
				'url'   => '',
				'label' => '',
			);
			if ( 'inactive' === $core_state ) {
				$item['text'] = __( 'This theme needs the whoiswho-core plugin, which is installed but not active. Until you activate it the public site shows a holding page.', 'whoiswho' );
				if ( $can_activate ) {
					$item['url']   = wiw_activate_core_url();
					$item['label'] = __( 'Activate the core plugin', 'whoiswho' );
				}
			} elseif ( 'broken' === $core_state ) {
				$item['text'] = __( 'The whoiswho-core plugin is switched on but did not start. It needs PHP 8.3 and WordPress 6.5 or newer. Until it starts the public site shows a holding page.', 'whoiswho' );
			} else {
				$item['text'] = __( 'This theme needs the whoiswho-core plugin, which is not installed. Upload whoiswho-core.zip from the install bundle, then activate it. Until then the public site shows a holding page.', 'whoiswho' );
				if ( $can_install ) {
					$item['url']   = self_admin_url( 'plugin-install.php?tab=upload' );
					$item['label'] = __( 'Upload the core plugin', 'whoiswho' );
				}
			}
			$items[] = $item;
		}//end if
		if ( ! $guard_present ) {
			$items[] = array(
				'key'   => 'guard',
				'text'  => __( 'The whoiswho-guard must-use plugin is missing. It keeps unreviewed records out of public pages. Copy the whoiswho-guard folder and whoiswho-guard.php from the install bundle into wp-content/mu-plugins (must-use plugins are not uploaded through wp-admin), then reload this page.', 'whoiswho' ),
				'url'   => '',
				'label' => '',
			);
		}
		return $items;
	}
}//end if

if ( ! function_exists( 'wiw_dependency_notice_html' ) ) {
	/**
	 * The notice markup for the items, every value escaped. Empty when there is nothing to say.
	 *
	 * @param array<int, array{key: string, text: string, url: string, label: string}> $items Items.
	 */
	function wiw_dependency_notice_html( array $items ): string {
		$html = '';
		foreach ( $items as $item ) {
			$html .= '<div class="notice notice-error wiw-dependency-notice wiw-dependency-' . esc_attr( $item['key'] ) . '"><p>' . esc_html( $item['text'] );
			if ( '' !== $item['url'] ) {
				$html .= ' <a class="button button-primary" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
			}
			$html .= '</p></div>';
		}
		return $html;
	}
}

if ( ! function_exists( 'wiw_print_dependency_notice' ) ) {
	/**
	 * Print the notice on the Dashboard, Plugins and Themes screens, to people who can act on it.
	 */
	function wiw_print_dependency_notice(): void {
		$can_activate = current_user_can( 'activate_plugins' );
		$can_install  = current_user_can( 'install_plugins' );
		if ( ! $can_activate && ! $can_install && ! current_user_can( 'switch_themes' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'themes', 'plugin-install' ), true ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped item by item in wiw_dependency_notice_html().
		echo wiw_dependency_notice_html( wiw_dependency_items( wiw_core_state(), wiw_guard_present(), $can_activate, $can_install ) );
	}
}

add_action( 'admin_notices', 'wiw_print_dependency_notice' );
add_action( 'network_admin_notices', 'wiw_print_dependency_notice' );
