<?php
/**
 * How CSS and JS reach the page: hashed files from the build manifest, tokens then base then components, scripts deferred,
 * no jQuery on the public site, and the inline header-state script that runs before first paint (REQ-041, REQ-042, REQ-043, REQ-046).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_manifest' ) ) {
	/**
	 * The build manifest: logical name => hashed file in dist/. Empty when nothing has been built.
	 *
	 * @return array<string, string>
	 */
	function wiw_manifest(): array {
		static $cache = null;
		if ( null === $cache ) {
			$cache = array();
			$file  = WP_CONTENT_DIR . '/../dist/manifest.json';
			if ( is_readable( $file ) ) {
				$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( is_array( $data ) ) {
					$cache = $data;
				}
			}
		}
		/**
		 * Filters the manifest (tests, previews).
		 *
		 * @param array<string, string> $manifest Manifest.
		 */
		$manifest = apply_filters( 'wiw_manifest', $cache );
		return $manifest;
	}
}//end if

if ( ! function_exists( 'wiw_asset_url' ) ) {
	/**
	 * URL of a built asset, or null when the manifest has no such entry.
	 *
	 * @param string $name Logical name, for example base.css.
	 */
	function wiw_asset_url( string $name ): ?string {
		$manifest = wiw_manifest();
		if ( empty( $manifest[ $name ] ) ) {
			return null;
		}
		return trailingslashit( dirname( content_url() ) ) . 'dist/' . rawurlencode( (string) $manifest[ $name ] );
	}
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		// Tokens (child) come first through the 'wiw-tokens-base' dependency, then base, then components.
		$deps = array( 'wiw-tokens-base' );
		foreach ( array( 'base', 'components', 'forms', 'a11y' ) as $layer ) {
			$url = wiw_asset_url( $layer . '.css' );
			if ( null === $url ) {
				continue;
			}
			wp_enqueue_style( 'wiw-' . $layer, $url, $deps, WIW_THEME_VERSION );
			$deps = array( 'wiw-' . $layer );
		}

		/**
		 * Per-template assets: map of template key => logical names. Only the current template's files load.
		 */
		$key = is_front_page() ? 'home' : ( is_singular( 'post' ) ? 'story' : '' );
		if ( '' !== $key ) {
			$css = wiw_asset_url( $key . '.css' );
			if ( null !== $css ) {
				wp_enqueue_style( 'wiw-' . $key, $css, $deps, WIW_THEME_VERSION );
			}
			$js = wiw_asset_url( $key . '.js' );
			if ( null !== $js ) {
				wp_enqueue_script( 'wiw-' . $key, $js, array(), WIW_THEME_VERSION, array( 'strategy' => 'defer' ) );
			}
		}
		$main = wiw_asset_url( 'main.js' );
		if ( null !== $main ) {
			wp_enqueue_script( 'wiw-main', $main, array(), WIW_THEME_VERSION, array( 'strategy' => 'defer' ) );
		}
	},
	30
);

// No jQuery on the public site: the theme adds JavaScript only where a page needs it.
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( is_admin() || is_customize_preview() ) {
			return;
		}
		wp_dequeue_script( 'jquery' );
		wp_deregister_script( 'jquery' );
		wp_deregister_script( 'jquery-core' );
		wp_deregister_script( 'jquery-migrate' );
	},
	100
);

if ( ! function_exists( 'wiw_print_header_state_script' ) ) {
	/**
	 * Prints the header-state script inline, with the CSP nonce, so the header is right on first paint.
	 */
	function wiw_print_header_state_script(): void {
		$file = get_template_directory() . '/assets/js/header-state.js';
		if ( ! is_readable( $file ) ) {
			return;
		}
		$code  = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$nonce = function_exists( 'wiw_csp_nonce' ) ? wiw_csp_nonce() : '';
		printf(
			'<script nonce="%1$s" data-home="%2$s">%3$s</script>' . "\n",
			esc_attr( $nonce ),
			is_front_page() ? '1' : '0',
			$code // phpcs:ignore WordPress.Security.EscapeOutput -- theme-owned file, not user input.
		);
	}
}
add_action( 'wp_head', 'wiw_print_header_state_script', 2 );
