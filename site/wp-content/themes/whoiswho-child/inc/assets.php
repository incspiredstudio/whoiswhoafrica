<?php
/**
 * Loads the brand tokens after the parent's base tokens, in the site and in the block editor, and preloads the two typefaces
 * the first screen uses. Replacing a brand value means editing assets/css/tokens.css, never a template.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( function_exists( 'wiw_core_ready' ) && ! wiw_core_ready() ) {
			return;
		}
		wp_enqueue_style( 'wiw-tokens-brand', get_stylesheet_directory_uri() . '/assets/css/tokens.css', array( 'wiw-tokens-base' ), WIW_CHILD_VERSION );
		// A url() inside a custom property resolves against the stylesheet that uses it, which is the built bundle, not tokens.css. Absolute addresses keep the brand images working everywhere.
		$brand = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/brand/';
		$css   = ':root{';
		foreach ( array( 'star', 'comb', 'link', 'spiral' ) as $mark ) {
			$css .= '--img-mark-' . $mark . ':url("' . esc_url_raw( $brand . 'mark-' . $mark . '.webp' ) . '");';
		}
		$css .= '--img-pattern-tile:url("' . esc_url_raw( $brand . 'pattern-marks.webp' ) . '");';
		$css .= '--img-logo:url("' . esc_url_raw( trailingslashit( get_stylesheet_directory_uri() ) . 'assets/logo/wiw-logo-mask.webp' ) . '");}';
		wp_add_inline_style( 'wiw-tokens-brand', $css );
	},
	20
);

add_action(
	'after_setup_theme',
	static function (): void {
		add_editor_style( 'assets/css/tokens.css' );
	}
);

add_action(
	'wp_head',
	static function (): void {
		if ( function_exists( 'wiw_core_ready' ) && ! wiw_core_ready() ) {
			return;
		}
		$fonts = array( 'repline.woff2', 'montreal-400.woff2' );
		// A story's head sets its meta line in the 500 weight and its lede in the 300 weight. Fetched late, they swap in after first
		// paint and push the whole article down (the story page's layout shift); fetched with the CSS, they are there for first paint.
		if ( is_singular( 'post' ) ) {
			$fonts[] = 'montreal-300.woff2';
			$fonts[] = 'montreal-500.woff2';
		}
		foreach ( $fonts as $file ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( get_stylesheet_directory_uri() . '/assets/fonts/' . $file )
			);
		}
		// The header wordmark is a CSS mask: fetch it with the fonts so the logo is there on first paint (8.6 KB, cached on every page).
		printf( '<link rel="preload" href="%s" as="image" type="image/webp">' . "\n", esc_url( get_stylesheet_directory_uri() . '/assets/logo/wiw-logo-mask.webp' ) );
	},
	1
);
