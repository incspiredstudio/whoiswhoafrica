<?php
/**
 * The theme never reads the hero video setting or the attachments behind it (issue 799, REQ-041): it asks
 * wiw_home_hero_overrides(), and only the plugin's HeroVideos service writes the setting, which it audits.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch;

use WhoIsWho\Tests\TestCase;

final class HeroVideosAreReadThroughOneFunctionTest extends TestCase {

	/**
	 * PHP files under a folder.
	 *
	 * @param string $root Folder.
	 * @return string[]
	 */
	private function php_files( string $root ): array {
		$out = array();
		$it  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$path = str_replace( '\\', '/', (string) $file );
			if ( '.php' === substr( $path, -4 ) && false === strpos( $path, '/node_modules/' ) && false === strpos( $path, '/vendor/' ) ) {
				$out[] = $path;
			}
		}
		return $out;
	}

	public function test_REQ_041_no_theme_file_reads_the_hero_option_or_the_service(): void {
		$bad = array();
		foreach ( $this->php_files( dirname( __DIR__, 2 ) . '/wp-content/themes' ) as $path ) {
			if ( 1 === preg_match( '/wiw_home_hero_slots|HeroVideos|wiw_curate_home/', (string) file_get_contents( $path ) ) ) {
				$bad[] = $path;
			}
		}
		$this->assertSame( array(), $bad, 'The theme calls wiw_home_hero_overrides() and nothing else.' );
	}

	public function test_REQ_257_only_the_service_writes_the_hero_option_and_it_audits(): void {
		$writers = array();
		foreach ( $this->php_files( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/src' ) as $path ) {
			$code = (string) file_get_contents( $path );
			if ( 1 === preg_match( '/(update_option|add_option|delete_option)\s*\(\s*(HeroVideos::OPTION|\'wiw_home_hero_slots\')/', $code ) ) {
				$writers[] = basename( $path );
			}
		}
		// Only the service (which writes it through self::OPTION) and nothing else; so no other file names it.
		$this->assertSame( array(), $writers );
		$service = (string) file_get_contents( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/src/Content/Home/HeroVideos.php' );
		$this->assertStringContainsString( 'update_option( self::OPTION', $service );
		$this->assertStringContainsString( 'audit->log(', $service );
		$this->assertStringContainsString( 'current_user_can( self::CAP )', $service );
	}
}
