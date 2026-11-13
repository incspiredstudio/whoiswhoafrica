<?php
/**
 * The visibility matrix with the page cache on (REQ-208, REQ-209, REQ-217, REQ-054). For every publication state of a profile
 * (the documented table in docs/theme/seo-and-sharing.md, written out here), the cache policy may store a response only when it
 * is the public, reviewed, 200 page. A pending-review page (200 with noindex), a 410 and a 404 are never stored, so the noindex
 * header and the 410 always come from the origin. No WordPress needed: the policy is pure.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility;

use WhoIsWho\Core\Platform\Cache\PageCache\PageCachePolicy;
use WhoIsWho\Tests\TestCase;

final class PageCacheVisibilityTest extends TestCase {

	/**
	 * Documented: state => HTTP status, noindex. Source: docs/theme/seo-and-sharing.md, "Visibility by state".
	 */
	private const STATES = array(
		'published_reviewed'  => array( 200, false ),
		'published_pending'   => array( 200, true ),
		'unpublished'         => array( 410, true ),
		'removed_suppressed'  => array( 410, true ),
		'rejected'            => array( 404, true ),
		'imported_not_public' => array( 404, true ),
	);

	public function test_REQ_208_visibility_matrix_with_cache_plugin(): void {
		$policy = new PageCachePolicy();
		foreach ( self::STATES as $state => $facts ) {
			$decision = $policy->decide(
				array(
					'path'    => '/profile/' . $state . '/',
					'status'  => $facts[0],
					'noindex' => $facts[1],
				)
			);
			$this->assertSame( 'published_reviewed' === $state, $decision['cache'], "$state: only the public reviewed page may be stored" );
		}
	}

	public function test_REQ_217_noindex_header_survives_cache(): void {
		$decision = ( new PageCachePolicy() )->decide(
			array(
				'path'    => '/profile/pending/',
				'status'  => 200,
				'noindex' => true,
			)
		);
		$this->assertFalse( $decision['cache'] );
		$this->assertSame( 'noindex', $decision['reason'], 'a pending-review page is answered by the origin every time, with its X-Robots-Tag' );
	}

	public function test_REQ_054_staff_pages_bypass_cache(): void {
		$policy = new PageCachePolicy();
		foreach ( array( '/wp-admin/', '/wp-admin/admin.php', '/wp-login.php', '/wp-json/wp/v2/posts' ) as $path ) {
			$this->assertFalse( $policy->decide( array( 'path' => $path ) )['cache'], $path );
		}
		foreach ( array( '/', '/profile/ada-obi/', '/stories', '/search' ) as $path ) {
			$this->assertFalse(
				$policy->decide(
					array(
						'path'    => $path,
						'cookies' => array( 'wordpress_logged_in_0123' ),
					)
				)['cache'],
				"$path with a staff cookie"
			);
		}
	}

	public function test_REQ_217_forms_on_cached_pages_use_a_time_based_token_not_a_nonce(): void {
		// Form pages are not stored at all (their tokens expire), and the form token is signed with a key, not a WordPress nonce.
		$policy = new PageCachePolicy();
		$this->assertFalse( $policy->decide( array( 'path' => '/contact' ) )['cache'] );
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/src/Platform/Forms/FormToken.php' );
		$this->assertStringNotContainsString( 'wp_create_nonce', $source );
		$this->assertStringContainsString( 'hash_hmac', $source );
	}
}
