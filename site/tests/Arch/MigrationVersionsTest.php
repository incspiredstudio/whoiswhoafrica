<?php
/**
 * Migration versions are unique and match their file names (CLAUDE.md migrations convention).
 *
 * Two migrations that share a version make the runner skip one of them, so CI must fail first.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch;

use WhoIsWho\Tests\TestCase;

final class MigrationVersionsTest extends TestCase {

	/**
	 * Version declared inside each migration file, keyed by file name.
	 *
	 * @return array<string, string>
	 */
	private function declared_versions(): array {
		$dir      = dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations';
		$versions = array();
		foreach ( (array) glob( $dir . '/*.php' ) as $file ) {
			if ( 'index.php' === basename( (string) $file ) ) {
				continue;
			}
			$code = (string) file_get_contents( (string) $file );
			$this->assertSame( 1, preg_match( '/function\s+version\(\)\s*:\s*string\s*\{\s*return\s+\'(\d{10})\';/', $code, $m ), basename( (string) $file ) . ' must declare a ten-digit version' );
			$versions[ basename( (string) $file ) ] = $m[1];
		}
		return $versions;
	}

	public function test_REQ_046_migrations_exist(): void {
		$this->assertNotEmpty( $this->declared_versions() );
	}

	public function test_REQ_046_no_two_migrations_share_a_version(): void {
		$seen = array();
		foreach ( $this->declared_versions() as $file => $version ) {
			$this->assertArrayNotHasKey( $version, $seen, sprintf( '%s and %s share version %s', $file, $seen[ $version ] ?? '', $version ) );
			$seen[ $version ] = $file;
		}
	}

	public function test_REQ_046_no_two_migration_files_share_a_prefix(): void {
		$seen = array();
		foreach ( array_keys( $this->declared_versions() ) as $file ) {
			$prefix = substr( $file, 0, 10 );
			$this->assertArrayNotHasKey( $prefix, $seen, sprintf( '%s and %s share the file prefix %s', $file, $seen[ $prefix ] ?? '', $prefix ) );
			$seen[ $prefix ] = $file;
		}
	}

	public function test_REQ_046_file_prefix_matches_declared_version(): void {
		foreach ( $this->declared_versions() as $file => $version ) {
			$this->assertSame( $version, substr( $file, 0, 10 ), $file . ' is named for a different version than it declares' );
		}
	}
}
