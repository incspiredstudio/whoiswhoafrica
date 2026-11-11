<?php
/**
 * Image sizes for cards and posters; WordPress adds modern-format sources and lazy loading by default (REQ-042).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

/**
 * Register the theme's image sizes.
 */
function wiw_register_image_sizes(): void {
	add_image_size( 'wiw-card', 640, 480, true );
	add_image_size( 'wiw-poster', 600, 800, true );
}
add_action( 'after_setup_theme', 'wiw_register_image_sizes' );
