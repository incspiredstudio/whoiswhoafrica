<?php
/**
 * Base templates render server-side with one h1, landmarks and no JavaScript dependence (REQ-044, REQ-043). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class BaseTemplatesTest extends TestCase {

	private function theme_dir(): string {
		return dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho';
	}

	public function test_REQ_044_templates_render_without_javascript(): void {
		foreach ( array( 'index', 'front-page', 'page', 'single', 'archive', 'header', 'footer' ) as $name ) {
			$this->assertFileExists( $this->theme_dir() . "/$name.php" );
		}
		foreach ( array( 'site-header', 'site-footer', 'page-band' ) as $part ) {
			$this->assertFileExists( $this->theme_dir() . "/template-parts/$part.php" );
		}
	}

	public function test_REQ_044_page_band_renders_one_h1_with_escaped_title(): void {
		$this->requireWordPress();
		ob_start();
		wiw_page_band(
			array(
				'eyebrow' => 'Eyebrow',
				'title'   => 'A <b>title</b>',
				'lede'    => 'Lede',
			)
		);
		$out = (string) ob_get_clean();
		$this->assertSame( 1, substr_count( $out, '<h1>' ) );
		$this->assertStringContainsString( 'A &lt;b&gt;title&lt;/b&gt;', $out );
	}

	public function test_REQ_043_templates_never_read_profile_data_directly(): void {
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->theme_dir(), \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			$this->assertDoesNotMatchRegularExpression( '/get_post_meta|new\s+WP_Query/', $code, $file->getFilename() );
		}
	}
}
