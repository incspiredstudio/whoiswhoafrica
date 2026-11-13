<?php
/**
 * The page-cache bridge inside WordPress (REQ-121, REQ-209). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Cache\NullClient;
use WhoIsWho\Core\Platform\Cache\PageCache\PageCache;
use WhoIsWho\Core\Platform\Cache\PageCache\PageCachePolicy;
use WhoIsWho\Tests\WpTestCase;

/**
 * Page cache bridge.
 */
final class PageCacheBridgeTest extends WpTestCase {

	/**
	 * Request URI before the test.
	 *
	 * @var string|null
	 */
	private ?string $uri = null;

	protected function setUp(): void {
		parent::setUp();
		$this->uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null;
	}

	protected function tearDown(): void {
		unset( $_COOKIE['wordpress_logged_in_test'] );
		if ( null === $this->uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->uri;
		}
		parent::tearDown();
	}

	public function test_REQ_209_bridge_is_registered_before_the_purge_service_picks_a_client(): void {
		$this->assertNotFalse( has_filter( 'wiw_purge_client' ) );
		$this->assertNotFalse( has_filter( 'do_createsupercache' ) );
	}

	public function test_REQ_209_without_the_cache_plugin_the_existing_client_is_kept(): void {
		$bridge = new PageCache( new PageCachePolicy() );
		$null   = new NullClient();
		$this->assertSame( $null, $bridge->purge_client( $null ) );
	}

	public function test_REQ_121_the_plugin_is_not_allowed_to_store_a_staff_request(): void {
		$bridge                 = new PageCache( new PageCachePolicy() );
		$_SERVER['REQUEST_URI'] = '/profile/ada-obi/';
		$this->assertTrue( $bridge->allow_store( true ), 'an anonymous public page may be stored' );
		$this->assertFalse( $bridge->allow_store( false ), 'the plugin saying no stays no' );

		$_COOKIE['wordpress_logged_in_test'] = 'x';
		$this->assertFalse( $bridge->allow_store( true ), 'a signed-in cookie is never stored' );
	}

	public function test_REQ_121_a_request_with_a_query_string_is_not_stored(): void {
		$bridge                 = new PageCache( new PageCachePolicy() );
		$_SERVER['REQUEST_URI'] = '/profile/ada-obi/?preview=true';
		$this->assertFalse( $bridge->allow_store( true ) );
	}
}
