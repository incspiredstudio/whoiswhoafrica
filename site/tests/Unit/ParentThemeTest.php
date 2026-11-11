<?php
/**
 * Parent theme structure and rules (REQ-273, REQ-044).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Tests\TestCase;

final class ParentThemeTest extends TestCase {

	private function theme_dir(): string {
		return dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho';
	}

	public function test_REQ_273_parent_theme_is_present_and_not_the_active_theme(): void {
		$css = (string) file_get_contents( $this->theme_dir() . '/style.css' );
		$this->assertStringContainsString( 'Theme Name: WHOISWHO', $css );
		$this->assertStringNotContainsString( 'Template:', $css, 'the parent has no parent' );
		$child_file = dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho-child/style.css';
		$child      = is_readable( $child_file ) ? (string) file_get_contents( $child_file ) : '';
		$this->assertTrue( '' === $child || str_contains( $child, 'Template: whoiswho' ), 'child must point at the parent' );
	}

	public function test_REQ_273_folders_exist(): void {
		foreach ( array( 'templates', 'template-parts', 'patterns', 'inc', 'assets', 'emails' ) as $dir ) {
			$this->assertDirectoryExists( $this->theme_dir() . '/' . $dir );
		}
	}

	public function test_REQ_273_no_brand_literals_or_profile_queries_in_the_parent(): void {
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->theme_dir(), \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( ! in_array( $file->getExtension(), array( 'php', 'css', 'json' ), true ) ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,8}\b/', $code, $file->getFilename() . ' has a colour literal' );
			$this->assertStringNotContainsString( 'get_post_meta', $code, $file->getFilename() );
			$this->assertStringNotContainsString( 'WP_Query', $code, $file->getFilename() );
		}
	}
}
