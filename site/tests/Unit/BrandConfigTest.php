<?php
/**
 * Brand strings live in one config and templates read them through wiw_brand() (REQ-052, REQ-190).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Tests\TestCase;

final class BrandConfigTest extends TestCase {

	private function child(): string {
		return dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho-child';
	}

	public function test_REQ_052_brand_strings_come_from_brand_config(): void {
		require_once $this->child() . '/inc/brand.php';
		$this->assertSame( 'WHOISWHO', wiw_brand( 'name' ) );
		$this->assertSame( 'Who Is Who In Africa', wiw_brand( 'long_name' ) );
		$this->assertSame( 'WHOISWHO in Africa: The Maiden Edition', wiw_brand( 'edition' ) );
		$this->assertSame( 'whoiswhoafrica', wiw_brand( 'legal_name' ) );
		$this->assertSame( 'Influence. Achievement. Legacy.', wiw_brand( 'tagline' ) );
		$this->assertSame( 'wiwi.africa', wiw_brand( 'domain' ) );
		$this->assertSame( '', wiw_brand( 'nope' ) );
	}

	public function test_REQ_052_no_literal_brand_string_in_templates(): void {
		$themes = dirname( __DIR__, 2 ) . '/wp-content/themes';
		foreach ( array( 'whoiswho', 'whoiswho-child' ) as $theme ) {
			foreach ( array( 'templates', 'template-parts', 'patterns', 'emails', 'inc' ) as $dir ) {
				if ( ! is_dir( "$themes/$theme/$dir" ) ) {
					continue;
				}
				$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( "$themes/$theme/$dir", \FilesystemIterator::SKIP_DOTS ) );
				foreach ( $files as $file ) {
					if ( ! in_array( $file->getExtension(), array( 'php', 'html' ), true ) || 'brand.php' === $file->getFilename() ) {
						continue;
					}
					// Comments are documentation, not output.
					$code = (string) preg_replace( '#/\*.*?\*/|^\s*//.*$#sm', '', (string) file_get_contents( $file->getPathname() ) );
					$this->assertDoesNotMatchRegularExpression( '/WHOISWHO|Who Is Who|whoiswhoafrica|wiwi\.africa|Influence\. Achievement/', $code, $file->getPathname() . ' hard-codes a brand string' );
				}
			}
		}
	}
}
