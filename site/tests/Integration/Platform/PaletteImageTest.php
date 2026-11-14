<?php
/**
 * A palette (indexed-colour) PNG gets WebP sizes without the GD warning "Palette image not supported by webp": the image editor
 * turns it into true colour as it loads (REQ-042, #810). Needs the WordPress test suite and GD with WebP.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Media\TrueColourGdEditor;
use WhoIsWho\Tests\WpTestCase;

final class PaletteImageTest extends WpTestCase {

	private string $file = '';

	protected function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'imagewebp' ) || ! function_exists( 'imagecreate' ) ) {
			$this->markTestSkipped( 'GD with WebP is not available.' );
		}
		$this->file = get_temp_dir() . 'wiw-palette-' . uniqid() . '.png';
		$img        = imagecreate( 400, 300 );
		imagecolorallocate( $img, 200, 30, 30 );
		imagefilledrectangle( $img, 50, 50, 200, 200, (int) imagecolorallocate( $img, 20, 120, 220 ) );
		imagepng( $img, $this->file );
		imagedestroy( $img );
		$probe = imagecreatefrompng( $this->file );
		$this->assertFalse( imageistruecolor( $probe ), 'the fixture is a palette PNG' );
		imagedestroy( $probe );
	}

	protected function tearDown(): void {
		foreach ( (array) glob( preg_replace( '/\.png$/', '', $this->file ) . '*' ) as $made ) {
			if ( is_string( $made ) && is_file( $made ) ) {
				unlink( $made ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		parent::tearDown();
	}

	public function test_REQ_042_palette_png_makes_webp_sizes_without_a_warning(): void {
		$to_webp = static fn ( array $formats ): array => array_merge( $formats, array( 'image/png' => 'image/webp' ) );
		$gd_only = static fn (): array => array( TrueColourGdEditor::class, 'WP_Image_Editor_GD' );
		add_filter( 'image_editor_output_format', $to_webp );
		add_filter( 'wp_image_editors', $gd_only, 100 );

		$warnings = array();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- the test records PHP warnings.
		set_error_handler(
			static function ( int $no, string $text ) use ( &$warnings ): bool {
				$warnings[] = $text;
				return true;
			}
		);
		$editor = wp_get_image_editor( $this->file );
		$sizes  = is_wp_error( $editor ) ? array() : $editor->multi_resize(
			array(
				'small' => array(
					'width'  => 200,
					'height' => 150,
					'crop'   => true,
				),
			)
		);
		restore_error_handler();
		remove_filter( 'image_editor_output_format', $to_webp );
		remove_filter( 'wp_image_editors', $gd_only, 100 );

		$this->assertInstanceOf( TrueColourGdEditor::class, $editor );
		$this->assertSame( array(), $warnings, 'no GD warning' );
		$this->assertArrayHasKey( 'small', $sizes );
		$this->assertSame( 'image/webp', $sizes['small']['mime-type'] );
		$made = dirname( $this->file ) . '/' . $sizes['small']['file'];
		$this->assertFileExists( $made );
		$this->assertGreaterThan( 0, filesize( $made ), 'the WebP was written' );
	}

	public function test_REQ_042_the_plugin_puts_its_editor_first(): void {
		$editors = apply_filters( 'wp_image_editors', array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' ) );
		$this->assertContains( TrueColourGdEditor::class, $editors );
		$this->assertLessThan( array_search( 'WP_Image_Editor_GD', $editors, true ), array_search( TrueColourGdEditor::class, $editors, true ) );
	}
}
