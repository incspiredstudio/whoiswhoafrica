<?php
/**
 * Profile image sizes, modern formats and the placeholder (REQ-042, REQ-247). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class ProfileImagesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		wiw_register_image_sizes();
	}

	public function test_REQ_247_no_cover_or_banner_image_size_exists(): void {
		foreach ( array_keys( wp_get_additional_image_sizes() ) as $name ) {
			$this->assertDoesNotMatchRegularExpression( '/cover|banner|hero/i', (string) $name, $name );
		}
	}

	public function test_REQ_042_poster_sizes_share_one_crop_so_a_srcset_exists(): void {
		$sizes = wp_get_additional_image_sizes();
		foreach ( array( 'wiw-poster-sm', 'wiw-poster', 'wiw-poster-lg' ) as $name ) {
			$this->assertArrayHasKey( $name, $sizes );
			$this->assertTrue( $sizes[ $name ]['crop'] );
			$this->assertEqualsWithDelta( 0.75, $sizes[ $name ]['width'] / $sizes[ $name ]['height'], 0.001 );
		}
		$this->assertLessThan( $sizes['wiw-poster']['width'], $sizes['wiw-poster-sm']['width'] );
		$this->assertGreaterThan( $sizes['wiw-poster']['width'], $sizes['wiw-poster-lg']['width'] );
	}

	public function test_REQ_042_jpeg_and_png_are_converted_to_webp_when_the_library_can(): void {
		$out = wiw_modern_image_formats( array() );
		if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->assertSame( 'image/webp', $out['image/jpeg'] );
			$this->assertSame( 'image/webp', $out['image/png'] );
		} else {
			$this->assertSame( array(), $out, 'nothing changes when WebP is not available' );
		}
	}

	public function test_REQ_042_lcp_image_not_lazy_loaded(): void {
		$head = wiw_poster_image_attrs( 'Portrait of A', 'head' );
		$this->assertSame( 'eager', $head['loading'] );
		$this->assertSame( 'high', $head['fetchpriority'], 'the profile header portrait is the largest paint' );
		$card = wiw_poster_image_attrs( '', 'card' );
		$this->assertSame( 'lazy', $card['loading'] );
		$this->assertArrayNotHasKey( 'fetchpriority', $card );
		$template = (string) file_get_contents( get_template_directory() . '/template-parts/profile-head.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertStringContainsString( 'wiw_poster_image_attrs(', $template );
		$this->assertStringContainsString( "'head'", $template, 'the profile header asks for the head attributes' );
	}

	public function test_REQ_042_images_have_srcset_and_modern_format_and_REQ_080_the_original_is_kept_after_resize(): void {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$dir  = wp_upload_dir();
		$file = $dir['path'] . '/wiw-test-portrait-' . bin2hex( random_bytes( 3 ) ) . '.jpg';
		$im   = imagecreatetruecolor( 1200, 1600 );
		imagefilledrectangle( $im, 0, 0, 1200, 1600, 0x336699 );
		imagejpeg( $im, $file, 85 );
		$original = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$id       = (int) wp_insert_attachment(
			array(
				'post_title'     => 'Portrait',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			),
			$file
		);
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		$meta = wp_get_attachment_metadata( $id );
		foreach ( array( 'wiw-poster-sm', 'wiw-poster', 'wiw-poster-lg' ) as $size ) {
			$this->assertArrayHasKey( $size, $meta['sizes'], $size );
		}
		$this->assertFileExists( $file, 'the original file is kept in the media library' );
		$this->assertSame( $original, (string) file_get_contents( $file ), 'the original is not changed by the resize' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->assertSame( 'image/webp', $meta['sizes']['wiw-poster']['mime-type'], 'the served sizes are WebP' );
		}
		$html = wp_get_attachment_image( $id, 'wiw-poster', false, wiw_poster_image_attrs( 'Portrait of A', 'head' ) );
		$this->assertStringContainsString( 'srcset="', $html );
		preg_match( '/srcset="([^"]+)"/', $html, $found );
		$this->assertGreaterThanOrEqual( 3, count( explode( ',', $found[1] ) ), 'the srcset offers at least the three portrait widths' );
		$this->assertMatchesRegularExpression( '/\swidth="\d+"/', $html );
		$this->assertMatchesRegularExpression( '/\sheight="\d+"/', $html );
		$this->assertStringContainsString( 'loading="eager"', $html );
		$this->assertStringContainsString( 'fetchpriority="high"', $html );
		$this->assertStringContainsString( 'sizes="', $html );
		wp_delete_attachment( $id, true );
		@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	}

	public function test_REQ_042_attributes_carry_sizes_lazy_loading_and_the_alt_text(): void {
		$card = wiw_poster_image_attrs( '', 'card' );
		$this->assertSame( 'lazy', $card['loading'] );
		$this->assertSame( '', $card['alt'] );
		$this->assertNotSame( '', $card['sizes'] );
		$head = wiw_poster_image_attrs( 'Portrait of A', 'head' );
		$this->assertSame( 'eager', $head['loading'], 'the page header image is above the fold' );
		$this->assertSame( 'Portrait of A', $head['alt'] );
	}
}
