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

/**
 * A logo with its empty margins trimmed (REQ-247): many logo files from Wikimedia Commons sit on a wide transparent or plain
 * canvas, so a logo shown whole would be small and off centre. The first time a logo is shown, the drawn area is found on a
 * scaled-down copy (transparent pixels, or pixels matching the corner colour), cropped from the original with the image
 * editor, and saved beside it as <name>-wiwtrim.webp. The result is cached in attachment meta; "none" means the file needs
 * no trim or cannot be read, and the original is used. The trimmed copy is deleted with the attachment.
 *
 * @param int $id Attachment ID.
 * @return array{url: string, width: int, height: int}|null Null when the attachment is not an image.
 */
function wiw_logo_trimmed( int $id ): ?array {
	$full = wp_get_attachment_image_src( $id, 'full' );
	if ( ! is_array( $full ) ) {
		return null;
	}
	$original = array(
		'url'    => (string) $full[0],
		'width'  => (int) $full[1],
		'height' => (int) $full[2],
	);
	$meta = get_post_meta( $id, '_wiw_logo_trim', true );
	if ( 'none' === $meta ) {
		return $original;
	}
	$file = (string) get_attached_file( $id );
	if ( is_array( $meta ) && isset( $meta['file'], $meta['width'], $meta['height'] ) && is_file( dirname( $file ) . '/' . $meta['file'] ) ) {
		return array(
			'url'    => trailingslashit( dirname( (string) $full[0] ) ) . rawurlencode( (string) $meta['file'] ),
			'width'  => (int) $meta['width'],
			'height' => (int) $meta['height'],
		);
	}
	$box = '' !== $file && is_file( $file ) ? wiw_logo_drawn_box( $file ) : null;
	if ( null === $box ) {
		update_post_meta( $id, '_wiw_logo_trim', 'none' );
		return $original;
	}
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) || is_wp_error( $editor->crop( $box[0], $box[1], $box[2], $box[3] ) ) ) {
		update_post_meta( $id, '_wiw_logo_trim', 'none' );
		return $original;
	}
	$name  = pathinfo( $file, PATHINFO_FILENAME ) . '-wiwtrim.webp';
	$saved = $editor->save( dirname( $file ) . '/' . $name, 'image/webp' );
	if ( is_wp_error( $saved ) ) {
		update_post_meta( $id, '_wiw_logo_trim', 'none' );
		return $original;
	}
	update_post_meta(
		$id,
		'_wiw_logo_trim',
		array(
			'file'   => $name,
			'width'  => (int) $saved['width'],
			'height' => (int) $saved['height'],
		)
	);
	return array(
		'url'    => trailingslashit( dirname( (string) $full[0] ) ) . rawurlencode( $name ),
		'width'  => (int) $saved['width'],
		'height' => (int) $saved['height'],
	);
}

/**
 * The drawn area of a logo file as [x, y, width, height] in the file's pixels, with a small margin; null when there is
 * nothing worth trimming (the drawing already fills at least 94% of the canvas) or the file cannot be read.
 *
 * @param string $file Image path.
 * @return array{0: int, 1: int, 2: int, 3: int}|null
 */
function wiw_logo_drawn_box( string $file ): ?array {
	if ( ! function_exists( 'imagecreatefromstring' ) ) {
		return null;
	}
	$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- a local upload, read once.
	$img  = false !== $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors -- unreadable files fall back to the original.
	if ( false === $img ) {
		return null;
	}
	$w     = imagesx( $img );
	$h     = imagesy( $img );
	$scale = min( 1, 240 / max( $w, $h ) );
	$sw    = max( 1, (int) round( $w * $scale ) );
	$sh    = max( 1, (int) round( $h * $scale ) );
	$small = imagecreatetruecolor( $sw, $sh );
	imagealphablending( $small, false );
	imagesavealpha( $small, true );
	imagecopyresampled( $small, $img, 0, 0, 0, 0, $sw, $sh, $w, $h );
	$corner = imagecolorsforindex( $small, imagecolorat( $small, 0, 0 ) );
	$x0     = $sw;
	$y0     = $sh;
	$x1     = -1;
	$y1     = -1;
	for ( $y = 0; $y < $sh; $y++ ) {
		for ( $x = 0; $x < $sw; $x++ ) {
			$c = imagecolorsforindex( $small, imagecolorat( $small, $x, $y ) );
			if ( $c['alpha'] >= 110 ) {
				continue; // Transparent.
			}
			if ( $corner['alpha'] < 110 && abs( $c['red'] - $corner['red'] ) + abs( $c['green'] - $corner['green'] ) + abs( $c['blue'] - $corner['blue'] ) < 24 ) {
				continue; // The plain background colour.
			}
			$x0 = min( $x0, $x );
			$y0 = min( $y0, $y );
			$x1 = max( $x1, $x );
			$y1 = max( $y1, $y );
		}
	}
	if ( $x1 < 0 || ( ( $x1 - $x0 + 1 ) * ( $y1 - $y0 + 1 ) ) >= 0.94 * $sw * $sh ) {
		return null;
	}
	$pad = 0.03 * max( $x1 - $x0 + 1, $y1 - $y0 + 1 );
	$bx  = max( 0, (int) floor( ( $x0 - $pad ) / $scale ) );
	$by  = max( 0, (int) floor( ( $y0 - $pad ) / $scale ) );
	$ex  = min( $w, (int) ceil( ( $x1 + 1 + $pad ) / $scale ) );
	$ey  = min( $h, (int) ceil( ( $y1 + 1 + $pad ) / $scale ) );
	return array( $bx, $by, $ex - $bx, $ey - $by );
}

/**
 * Delete a logo's trimmed copy with its attachment.
 *
 * @param int $id Attachment ID.
 */
function wiw_logo_trim_delete( int $id ): void {
	$meta = get_post_meta( $id, '_wiw_logo_trim', true );
	if ( is_array( $meta ) && isset( $meta['file'] ) ) {
		$path = dirname( (string) get_attached_file( $id ) ) . '/' . basename( (string) $meta['file'] );
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
add_action( 'delete_attachment', 'wiw_logo_trim_delete' );

/**
 * A logo as an <img>, whole and trimmed (REQ-247): shown with object-fit contain on a light tile by the CSS, never cropped.
 *
 * @param int                   $id    Attachment ID.
 * @param array<string, string> $attrs Extra attributes (class, alt, loading, decoding, fetchpriority).
 */
function wiw_logo_img( int $id, array $attrs ): string {
	$logo = wiw_logo_trimmed( $id );
	if ( null === $logo ) {
		return '';
	}
	$out = '<img src="' . esc_url( $logo['url'] ) . '" width="' . (int) $logo['width'] . '" height="' . (int) $logo['height'] . '"';
	foreach ( $attrs as $name => $value ) {
		$out .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
	}
	return $out . ' />';
}
