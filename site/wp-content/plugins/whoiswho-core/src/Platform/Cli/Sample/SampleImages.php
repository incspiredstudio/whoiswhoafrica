<?php
/**
 * Generates the fictional placeholder images of the sample data: an abstract portrait (a head and shoulders shape) for a profile, and an
 * abstract banded cover for a story or event. They are drawn with GD from a hash of the key, so they are invented, licence-free, tiny,
 * stable between runs, and never look like a real person. Each becomes a media library attachment carrying the sample marker, so
 * sample:remove deletes it with the rest (REQ-208, REQ-052).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli\Sample;

/**
 * Sample images.
 */
final class SampleImages {

	/**
	 * Make a portrait attachment for a key, or return the one made before.
	 *
	 * @param string $key   Stable key, for example `profile:ngozi`.
	 * @param string $title Title for the media item.
	 * @return int Attachment id, 0 when GD or the upload folder is unavailable.
	 */
	public static function portrait( string $key, string $title ): int {
		return self::attachment( $key, $title, 'portrait' );
	}

	/**
	 * Make a cover attachment for a key, or return the one made before.
	 *
	 * @param string $key   Stable key, for example `story:soil`.
	 * @param string $title Title for the media item.
	 * @return int Attachment id, 0 when GD or the upload folder is unavailable.
	 */
	public static function cover( string $key, string $title ): int {
		return self::attachment( $key, $title, 'cover' );
	}

	/**
	 * Make (or find) the attachment.
	 *
	 * @param string $key   Stable key.
	 * @param string $title Title.
	 * @param string $kind  portrait or cover.
	 */
	private static function attachment( string $key, string $title, string $kind ): int {
		$existing = SampleMarker::find_post( 'image:' . $key, 'attachment' );
		if ( null !== $existing ) {
			return $existing;
		}
		$png = self::png( $key, $kind );
		if ( '' === $png ) {
			return 0;
		}
		$name   = sanitize_file_name( 'sample-' . $kind . '-' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( $key ) ) . '.png' );
		$upload = wp_upload_bits( $name, null, $png );
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $title,
				'post_status'    => 'inherit',
			),
			(string) $upload['file']
		);
		if ( $id < 1 ) {
			return 0;
		}
		$id = (int) $id;
		SampleMarker::mark_post( $id, 'image:' . $key );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, (string) $upload['file'] ) );
		return $id;
	}

	/**
	 * Draw the PNG bytes, or an empty string without GD.
	 *
	 * @param string $key  Stable key (decides the colours).
	 * @param string $kind portrait or cover.
	 */
	private static function png( string $key, string $kind ): string {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}
		$hash   = (int) sprintf( '%u', crc32( $key ) );
		$hue    = $hash % 360;
		$hue2   = ( $hue + 40 + ( $hash >> 9 ) % 80 ) % 360;
		$wide   = 'cover' === $kind;
		$w      = $wide ? 960 : 600;
		$h      = $wide ? 540 : 800;
		$image  = imagecreatetruecolor( $w, $h );
		$bands  = 24;
		$colour = static function ( $img, int $hue_deg, float $sat, float $light ) {
			list( $r, $g, $b ) = self::hsl( $hue_deg, $sat, $light );
			return imagecolorallocate( $img, $r, $g, $b );
		};
		for ( $i = 0; $i < $bands; $i++ ) {
			$y0 = (int) floor( $i * $h / $bands );
			$y1 = (int) floor( ( $i + 1 ) * $h / $bands );
			imagefilledrectangle( $image, 0, $y0, $w, $y1, $colour( $image, $hue, 0.55, 0.62 - 0.22 * $i / $bands ) );
		}
		if ( $wide ) {
			imagefilledellipse( $image, (int) ( $w * 0.78 ), (int) ( $h * 0.32 ), (int) ( $h * 0.8 ), (int) ( $h * 0.8 ), $colour( $image, $hue2, 0.6, 0.55 ) );
			imagefilledellipse( $image, (int) ( $w * 0.22 ), (int) ( $h * 0.85 ), (int) ( $h * 0.55 ), (int) ( $h * 0.55 ), $colour( $image, $hue2, 0.5, 0.4 ) );
			imagefilledrectangle( $image, 0, (int) ( $h * 0.9 ), $w, $h, $colour( $image, $hue, 0.5, 0.25 ) );
		} else {
			$skin = $colour( $image, $hue2, 0.35, 0.28 );
			imagefilledellipse( $image, (int) ( $w * 0.5 ), (int) ( $h * 0.36 ), (int) ( $w * 0.38 ), (int) ( $w * 0.46 ), $skin );
			imagefilledellipse( $image, (int) ( $w * 0.5 ), (int) ( $h * 0.92 ), (int) ( $w * 0.95 ), (int) ( $h * 0.62 ), $colour( $image, $hue2, 0.45, 0.2 ) );
		}
		ob_start();
		imagepng( $image, null, 9 );
		$bytes = (string) ob_get_clean();
		imagedestroy( $image );
		return $bytes;
	}

	/**
	 * HSL to RGB.
	 *
	 * @param int   $hue   0 to 359.
	 * @param float $sat   0 to 1.
	 * @param float $light 0 to 1.
	 * @return int[] Red, green, blue.
	 */
	private static function hsl( int $hue, float $sat, float $light ): array {
		$c   = ( 1 - abs( 2 * $light - 1 ) ) * $sat;
		$x   = $c * ( 1 - abs( fmod( $hue / 60, 2 ) - 1 ) );
		$m   = $light - $c / 2;
		$p   = (int) floor( $hue / 60 );
		$v   = array(
			array( $c, $x, 0 ),
			array( $x, $c, 0 ),
			array( 0, $c, $x ),
			array( 0, $x, $c ),
			array( $x, 0, $c ),
			array( $c, 0, $x ),
		);
		$rgb = $v[ $p % 6 ];
		return array( (int) round( ( $rgb[0] + $m ) * 255 ), (int) round( ( $rgb[1] + $m ) * 255 ), (int) round( ( $rgb[2] + $m ) * 255 ) );
	}
}
