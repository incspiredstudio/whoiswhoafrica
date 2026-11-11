<?php
/**
 * Theme supports and text domain.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'whoiswho', get_template_directory() . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
		// Responsive embeds are deliberately not enabled (REQ-044).
	}
);
