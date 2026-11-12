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
