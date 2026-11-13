<?php
/**
 * The deployed page-cache settings and installer (REQ-041, REQ-054, REQ-217, REQ-208). No WordPress needed.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WhoIsWho\Core\Platform\Cache\PageCache\PageCachePolicy;

final class PageCacheConfigTest extends TestCase {

	private string $tmp = '';

	protected function tearDown(): void {
		if ( '' !== $this->tmp && is_dir( $this->tmp ) ) {
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->tmp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $item ) {
				$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
			}
			rmdir( $this->tmp );
		}
		parent::tearDown();
	}

	/**
	 * The settings the template sets, by loading it with the constants it expects.
	 *
	 * @return array<string, mixed>
	 */
	private function settings(): array {
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', '/plugins' );
		}
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', '/content' );
		}
		return ( static function (): array {
			require dirname( __DIR__, 2 ) . '/deploy/cache/wp-cache-config.php';
			return get_defined_vars();
		} )();
	}

	public function test_REQ_121_lifetimes_do_not_exceed_120_seconds_and_lists_60(): void {
		$settings = $this->settings();
		$this->assertLessThanOrEqual( 60, $settings['cache_max_time'], 'one value for every page: the lower list lifetime' );
		$this->assertGreaterThan( 0, $settings['cache_max_time'] );
		$this->assertSame( 1, $settings['wp_cache_shutdown_gc'], 'expired files are removed on shutdown' );
		$this->assertLessThanOrEqual( PageCachePolicy::MAX_LIFETIME, $settings['cache_max_time'] );
	}

	public function test_REQ_054_staff_and_commenters_are_never_served_from_the_cache(): void {
		$settings = $this->settings();
		$this->assertSame( 2, $settings['wp_cache_not_logged_in'], 'no stored page for any login or commenter cookie' );
		$this->assertSame( 1, $settings['wp_cache_no_cache_for_get'], 'a query string is never cached (previews, search)' );
		$this->assertFalse( $settings['super_cache_enabled'], 'simple mode: PHP serves, so the cookie checks always run' );
		$this->assertSame( 0, $settings['wp_cache_mod_rewrite'] );
	}

	public function test_REQ_217_form_pages_signed_links_and_admin_are_rejected_by_address(): void {
		$rejected = implode( '|', $this->settings()['cache_rejected_uri'] );
		foreach ( array( 'wp-admin', 'wp-json', 'preview', 'contact', 'get-listed', 'claim', 'nominate', 'unsubscribe', 'confirm/', 'link/' ) as $needle ) {
			$this->assertStringContainsString( $needle, $rejected, $needle );
		}
		// Everything the policy refuses by path is also refused by the cache's own address list.
		foreach ( PageCachePolicy::NEVER_PATHS as $path ) {
			$token = trim( $path, '/' );
			if ( 'wp-' === $token ) {
				continue;
			}
			$this->assertStringContainsString( $token, $rejected, "the policy's never-path $path is also in the cache's reject list" );
		}
	}

	public function test_REQ_041_the_cache_is_off_unless_the_secrets_file_turns_it_on(): void {
		$config = (string) file_get_contents( dirname( __DIR__, 2 ) . '/wp-config.php' );
		$this->assertStringContainsString( "if ( 'true' === wiw_secret( 'WIW_PAGE_CACHE', 'false' ) ) {", $config );
		$this->assertStringContainsString( "define( 'WP_CACHE', true );", $config );
		$this->assertStringContainsString( 'WIW_PAGE_CACHE=', (string) file_get_contents( dirname( __DIR__, 2 ) . '/.env.example' ) );
	}

	public function test_REQ_041_the_installer_copies_the_files_and_a_second_run_changes_nothing(): void {
		$this->tmp = sys_get_temp_dir() . '/wiw-cache-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->tmp . '/wp-content/plugins/wp-super-cache', 0777, true );
		file_put_contents( $this->tmp . '/wp-content/plugins/wp-super-cache/advanced-cache.php', "<?php // drop-in\n" );
		$script = dirname( __DIR__, 2 ) . '/deploy/cache/install.php';

		$first = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' --site ' . escapeshellarg( $this->tmp ) . ' 2>&1' );
		$this->assertStringContainsString( 'wrote advanced-cache.php', (string) $first );
		$this->assertStringContainsString( 'wrote wp-cache-config.php', (string) $first );
		$this->assertFileExists( $this->tmp . '/wp-content/advanced-cache.php' );
		$this->assertFileExists( $this->tmp . '/wp-content/wp-cache-config.php' );
		$this->assertDirectoryExists( $this->tmp . '/wp-content/cache' );

		$second = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' --site ' . escapeshellarg( $this->tmp ) . ' 2>&1' );
		$this->assertStringContainsString( 'unchanged advanced-cache.php', (string) $second );
		$this->assertStringContainsString( 'unchanged wp-cache-config.php', (string) $second );
	}

	public function test_REQ_041_the_installer_fails_when_the_plugin_is_missing(): void {
		$this->tmp = sys_get_temp_dir() . '/wiw-cache-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->tmp . '/wp-content', 0777, true );
		$script = dirname( __DIR__, 2 ) . '/deploy/cache/install.php';
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' --site ' . escapeshellarg( $this->tmp ) . ' 2>&1', $output, $code );
		$this->assertSame( 1, $code );
		$this->assertStringContainsString( 'Missing:', implode( "\n", $output ) );
	}
}
