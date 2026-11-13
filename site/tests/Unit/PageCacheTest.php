<?php
/**
 * The page-cache policy and purge client (REQ-209, REQ-121, REQ-217). No WordPress or cache plugin needed: the cache is simulated.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Cache\CacheHeaders;
use WhoIsWho\Core\Platform\Cache\MemoryPurgeLog;
use WhoIsWho\Core\Platform\Cache\PageCache\PageCachePolicy;
use WhoIsWho\Core\Platform\Cache\PageCache\SuperCachePurgeClient;
use WhoIsWho\Core\Platform\Cache\PurgeService;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class PageCacheTest extends TestCase {

	private function decide( array $request ): array {
		return ( new PageCachePolicy() )->decide( $request );
	}

	public function test_REQ_209_anonymous_get_of_a_public_path_is_cached_within_the_lifetimes(): void {
		$profile = $this->decide( array( 'path' => '/profile/ada-obi/' ) );
		$this->assertTrue( $profile['cache'] );
		$this->assertSame( 120, $profile['ttl'] );
		$this->assertTrue( $this->decide( array( 'path' => '/' ) )['cache'], 'the home page is public' );

		foreach ( array( '/search', '/stories', '/events', '/profiles', '/awards', '/industries' ) as $list ) {
			$this->assertSame( 60, $this->decide( array( 'path' => $list ) )['ttl'], "$list is a list or search: 60 seconds" );
		}
		foreach ( array( '/', '/profile/a/', '/search', '/stories', '/about' ) as $path ) {
			$this->assertLessThanOrEqual( 120, $this->decide( array( 'path' => $path ) )['ttl'] );
		}
		$this->assertSame( CacheHeaders::MAX_AGE, PageCachePolicy::MAX_LIFETIME );
	}

	public function test_REQ_121_staff_responses_bypass_cache(): void {
		foreach ( array( 'wordpress_logged_in_abc123', 'wordpress_sec_abc123', 'wp-postpass_abc', 'comment_author_abc', 'wiw_anything' ) as $cookie ) {
			$decision = $this->decide(
				array(
					'path'    => '/profile/ada-obi/',
					'cookies' => array( 'unrelated', $cookie ),
				)
			);
			$this->assertFalse( $decision['cache'], $cookie );
			$this->assertSame( 'signed_in_cookie', $decision['reason'] );
		}
		$this->assertFalse( $this->decide( array( 'path' => '/wp-admin/index.php' ) )['cache'] );
		$this->assertFalse( $this->decide( array( 'path' => '/wp-json/wp/v2/posts' ) )['cache'] );
		$this->assertFalse(
			$this->decide(
				array(
					'path'    => '/profile/a/',
					'preview' => true,
				)
			)['cache'],
			'previews are never cached'
		);
	}

	public function test_REQ_121_only_get_without_a_query_string_and_with_status_200(): void {
		$this->assertFalse(
			$this->decide(
				array(
					'path'   => '/profile/a/',
					'method' => 'POST',
				)
			)['cache']
		);
		$this->assertFalse(
			$this->decide(
				array(
					'path'  => '/profile/a/',
					'query' => 'utm=1',
				)
			)['cache']
		);
		foreach ( array( 301, 302, 404, 410, 500 ) as $status ) {
			$this->assertFalse(
				$this->decide(
					array(
						'path'   => '/profile/a/',
						'status' => $status,
					)
				)['cache'],
				(string) $status
			);
		}
	}

	public function test_REQ_217_noindex_pages_and_unknown_paths_are_not_cached(): void {
		$this->assertFalse(
			$this->decide(
				array(
					'path'    => '/profile/pending/',
					'noindex' => true,
				)
			)['cache'],
			'a pending-review profile is never stored'
		);
		$this->assertFalse( $this->decide( array( 'path' => '/something-else/' ) )['cache'], 'only named public paths are cached' );
		foreach ( array( '/contact', '/get-listed', '/link/abcdefghijklmnopqrstu', '/confirm/abcdefghijklmnopqrstu', '/unsubscribe', '/nominate' ) as $form ) {
			$this->assertFalse( $this->decide( array( 'path' => $form ) )['cache'], "$form carries a time-based token or signed link" );
		}
	}

	public function test_REQ_209_cache_plugin_does_not_serve_unpublished_url(): void {
		// A simulated page cache: URL => stored page. The purge client removes a URL like wpsc_delete_url_cache does.
		$store         = array(
			'https://example.test/profile/ada-obi/' => '<html>public</html>',
			'https://example.test/profile/other/'   => '<html>other</html>',
		);
		$client        = new SuperCachePurgeClient(
			static function ( string $url ) use ( &$store ): bool {
				unset( $store[ rtrim( $url, '/' ) . '/' ] );
				return true;
			}
		);
		$queue         = new MemoryQueue();
		$options       = new \ArrayObject();
		$runner        = new JobRunner(
			$queue,
			static fn (): int => 1000000,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$queue->runner = $runner;
		$service       = new PurgeService( $runner, $client, new MemoryPurgeLog() );
		$service->register_handler();

		$service->on_unpublished( 'profile', 42, array( 'https://example.test/profile/ada-obi/' ) );
		$runner->run_due( 60 );

		$this->assertArrayNotHasKey( 'https://example.test/profile/ada-obi/', $store, 'the cached page is gone once the unpublish runs' );
		$this->assertArrayHasKey( 'https://example.test/profile/other/', $store, 'other pages are untouched' );
	}

	public function test_REQ_209_a_failed_clear_is_reported_so_the_job_retries(): void {
		$client = new SuperCachePurgeClient( static fn ( string $url ): bool => false );
		$this->expectException( \RuntimeException::class );
		$client->purge( array( 'https://example.test/profile/a/' ) );
	}

	public function test_REQ_209_urls_with_a_query_string_are_skipped_and_an_empty_list_is_ok(): void {
		$seen   = array();
		$client = new SuperCachePurgeClient(
			static function ( string $url ) use ( &$seen ): bool {
				$seen[] = $url;
				return true;
			}
		);
		$this->assertSame( 'ok', $client->purge( array() ) );
		$this->assertSame( 'ok', $client->purge( array( 'https://example.test/search?q=a', 'https://example.test/search' ) ) );
		$this->assertSame( array( 'https://example.test/search' ), $seen );
	}
}
