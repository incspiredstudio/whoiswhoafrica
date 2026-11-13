<?php
/**
 * The install bundle script builds the zips a plain WordPress needs and leaves out dev files and secrets (REQ-046, REQ-056).
 * Skipped where bash, the zip extension, the third-party plugins (composer install) or built assets (npm run build) are absent.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PackageReleaseTest extends TestCase {

	private string $out = '';

	protected function setUp(): void {
		parent::setUp();
		$site = dirname( __DIR__, 2 );
		if ( ! class_exists( \ZipArchive::class ) ) {
			$this->markTestSkipped( 'The zip extension is not available.' );
		}
		if ( ! is_file( $site . '/dist/manifest.json' ) || ! is_dir( $site . '/wp-content/plugins/two-factor' ) || ! is_dir( $site . '/wp-content/plugins/user-role-editor' ) || ! is_dir( $site . '/wp-content/plugins/action-scheduler' ) ) {
			$this->markTestSkipped( 'Run npm run build and composer install first.' );
		}
		$probe = array();
		$code  = 0;
		exec( 'bash -c "exit 0"', $probe, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		if ( 0 !== $code ) {
			$this->markTestSkipped( 'bash is not available.' );
		}
		$this->out = sys_get_temp_dir() . '/wiw-package-' . uniqid();
	}

	protected function tearDown(): void {
		if ( '' !== $this->out && is_dir( $this->out ) ) {
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->out, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $it as $item ) {
				$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
			rmdir( $this->out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		parent::tearDown();
	}

	/**
	 * Entry names of a zip.
	 *
	 * @param string $file Zip path.
	 * @return string[]
	 */
	private function names( string $file ): array {
		$zip = new \ZipArchive();
		$this->assertTrue( true === $zip->open( $file ) );
		$names = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$names[] = (string) $zip->getNameIndex( $i );
		}
		$zip->close();
		return $names;
	}

	public function test_REQ_046_bundle_has_the_four_zips_with_the_right_top_level_folders(): void {
		$site = dirname( __DIR__, 2 );
		exec( 'bash ' . escapeshellarg( $site . '/bin/package-release.sh' ) . ' --skip-assets --out ' . escapeshellarg( $this->out ) . ' 2>&1', $log, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		$this->assertSame( 0, $code, implode( "\n", $log ) );
		$bundle = $this->out . '/whoiswho-install-bundle';
		foreach ( array( 'whoiswho.zip', 'whoiswho-child.zip', 'whoiswho-core.zip', 'whoiswho-guard-mu-plugin.zip', 'INSTALL.md', 'wiw.php', 'SHA256SUMS.txt' ) as $file ) {
			$this->assertFileExists( $bundle . '/' . $file );
		}
		$this->assertContains( 'whoiswho/style.css', $this->names( $bundle . '/whoiswho.zip' ) );
		$this->assertContains( 'whoiswho/dist/manifest.json', $this->names( $bundle . '/whoiswho.zip' ), 'the theme carries its built assets' );
		$this->assertContains( 'whoiswho-child/style.css', $this->names( $bundle . '/whoiswho-child.zip' ) );
		$this->assertContains( 'whoiswho-guard.php', $this->names( $bundle . '/whoiswho-guard-mu-plugin.zip' ) );

		$core = $this->names( $bundle . '/whoiswho-core.zip' );
		$this->assertContains( 'whoiswho-core/whoiswho-core.php', $core );
		foreach ( array( 'two-factor/two-factor.php', 'user-role-editor/user-role-editor.php', 'action-scheduler/action-scheduler.php' ) as $bundled ) {
			$this->assertContains( 'whoiswho-core/bundled/' . $bundled, $core );
		}
		foreach ( $core as $name ) {
			$this->assertDoesNotMatchRegularExpression( '#(^|/)(tests|node_modules|\.git)(/|$)|(^|/)\.env|wp-config\.php|\.log$#', $name, 'no dev files or secrets in the plugin zip: ' . $name );
		}
		$this->assertStringNotContainsString( '@VERSION@', (string) file_get_contents( $bundle . '/INSTALL.md' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
