<?php
/**
 * Image sizes for cards and posters; WordPress adds modern-format sources and lazy loading by default (REQ-042). Portraits and
 * logos are served in three widths of the same crop (so the browser gets a srcset), in WebP when the server can make it, with
 * width and height set. There is no cover or banner size: a profile has one portrait or logo and nothing else (REQ-247).
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
	// Same 3:4 crop at two more widths, so WordPress offers all three in the srcset of a portrait or logo.
	add_image_size( 'wiw-poster-sm', 300, 400, true );
	add_image_size( 'wiw-poster-lg', 900, 1200, true );
}
add_action( 'after_setup_theme', 'wiw_register_image_sizes' );

/**
 * Make WebP copies of uploaded JPEG and PNG images when the image library supports it (REQ-042).
 *
 * @param array<string, string> $formats Source mime type => output mime type.
 * @return array<string, string>
 */
function wiw_modern_image_formats( array $formats ): array {
	if ( function_exists( 'wp_image_editor_supports' ) && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
	}
	return $formats;
}
add_filter( 'image_editor_output_format', 'wiw_modern_image_formats' );

/**
 * The sizes attribute for a poster image: the card grid is two across on phones, three or four on wide screens.
 *
 * @param string $context card or head.
 */
function wiw_poster_sizes( string $context = 'card' ): string {
	if ( 'head' === $context ) {
		return '(min-width: 48rem) 9rem, 6.5rem';
	}
	return '(min-width: 64rem) 20vw, (min-width: 48rem) 30vw, 46vw';
}

/**
 * Attributes for a portrait or logo image: a size hint, lazy loading except above the fold, decoding hint and a text alternative.
 *
 * @param string $alt     Alternative text ("" for a decorative image beside the name).
 * @param string $context card or head.
 * @return array<string, string>
 */
function wiw_poster_image_attrs( string $alt = '', string $context = 'card' ): array {
	$attrs = array(
		'alt'      => $alt,
		'sizes'    => wiw_poster_sizes( $context ),
		'loading'  => 'head' === $context ? 'eager' : 'lazy',
		'decoding' => 'async',
	);
	if ( 'head' === $context ) {
		// The portrait or logo in the profile header is the largest paint on a profile page: fetch it first and never lazily.
		$attrs['fetchpriority'] = 'high';
	}
	return $attrs;
}
