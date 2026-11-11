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
		wp_enqueue_style( 'wiw-tokens-brand', get_stylesheet_directory_uri() . '/assets/css/tokens.css', array( 'wiw-tokens-base' ), WIW_CHILD_VERSION );
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
		foreach ( array( 'repline.woff2', 'montreal-400.woff2' ) as $file ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( get_stylesheet_directory_uri() . '/assets/fonts/' . $file )
			);
		}
	},
	1
);
