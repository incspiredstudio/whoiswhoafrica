<?php
/**
 * Enqueues the base tokens, on the site and in the block editor. The child theme loads its brand tokens after this.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style( 'wiw-tokens-base', get_template_directory_uri() . '/assets/css/tokens.base.css', array(), WIW_THEME_VERSION );
	}
);

add_action(
	'after_setup_theme',
	static function (): void {
		add_editor_style( 'assets/css/tokens.base.css' );
	}
);
