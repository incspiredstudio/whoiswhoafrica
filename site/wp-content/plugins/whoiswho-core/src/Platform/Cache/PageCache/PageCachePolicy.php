<?php
/**
 * What the page-cache plugin may store (REQ-209, REQ-121, REQ-217, ADR-006). Only an anonymous GET of a named public path, with no
 * query string, answered 200, and not marked as not public. Anything else is never stored and never served from the cache: staff
 * and any signed-in or commenter cookie, previews, form and link pages (they carry time-based tokens), the admin, REST, feeds,
 * and pages that are noindex because their profile is pending review. Lifetimes are at most 120 seconds, 60 for lists and search.
 * The list paths are the theme's directory lists (wiw_directory_lists()); a unit test reads them from the theme so the two cannot drift.
 * A reviewed profile stays cacheable here even though ProfileRoute sends no-cache headers: those headers are for browsers and a CDN
 * (never keep a stale state), the page-cache plugin does not read them, and an unpublish purges the stored copy (REQ-208, REQ-217).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache\PageCache;

use WhoIsWho\Core\Platform\Cache\CacheHeaders;

/**
 * Page cache policy.
 */
final class PageCachePolicy {

	/**
	 * Path prefixes that may be cached (exact "/" is the home page). Filter: wiw_page_cache_public_paths.
	 */
	public const PUBLIC_PATHS = array( '/profile/', '/directory', '/people', '/entrepreneurs', '/businesses', '/institutions', '/stories', '/events', '/awards', '/industries', '/search', '/about', '/faq', '/methodology', '/privacy', '/terms', '/cookies' );

	/**
	 * Prefixes whose responses are lists or search results: 60 seconds.
	 */
	public const LIST_PATHS = array( '/directory', '/people', '/entrepreneurs', '/businesses', '/institutions', '/stories', '/events', '/awards', '/industries', '/search' );

	/**
	 * Paths never cached even if a public prefix matched: form pages (time-based tokens), signed links, previews and the like.
	 */
	public const NEVER_PATHS = array( '/contact', '/get-listed', '/claim', '/nominate', '/correct', '/correction', '/removal', '/remove', '/report', '/newsletter', '/unsubscribe', '/confirm/', '/link/', '/wp-', '/feed', '/healthz', '/preview' );

	/**
	 * Cookie name prefixes that mean a person is signed in, commenting or viewing a protected page.
	 */
	public const BYPASS_COOKIES = array( 'wordpress_logged_in_', 'wordpress_sec_', 'wp-postpass_', 'comment_author_', 'wiw_' );

	/**
	 * Hard ceiling in seconds.
	 */
	public const MAX_LIFETIME = 120;

	/**
	 * Decide whether a response may be cached and for how long.
	 *
	 * @param array{method?: string, path?: string, query?: string, cookies?: string[], status?: int, noindex?: bool, preview?: bool, public_paths?: string[]} $request Facts about the request and response; cookies are cookie names.
	 * @return array{cache: bool, ttl: int, reason: string}
	 */
	public function decide( array $request ): array {
		$method = strtoupper( (string) ( $request['method'] ?? 'GET' ) );
		$path   = '/' . ltrim( strtolower( (string) ( $request['path'] ?? '/' ) ), '/' );
		if ( 'GET' !== $method ) {
			return $this->no( 'method' );
		}
		if ( '' !== (string) ( $request['query'] ?? '' ) ) {
			return $this->no( 'query_string' );
		}
		foreach ( (array) ( $request['cookies'] ?? array() ) as $name ) {
			foreach ( self::BYPASS_COOKIES as $prefix ) {
				if ( 0 === strpos( (string) $name, $prefix ) ) {
					return $this->no( 'signed_in_cookie' );
				}
			}
		}
		if ( ! empty( $request['preview'] ) ) {
			return $this->no( 'preview' );
		}
		if ( 200 !== (int) ( $request['status'] ?? 200 ) ) {
			return $this->no( 'status' );
		}
		if ( ! empty( $request['noindex'] ) ) {
			return $this->no( 'noindex' );
		}
		foreach ( self::NEVER_PATHS as $never ) {
			if ( 0 === strpos( $path, $never ) ) {
				return $this->no( 'never_path' );
			}
		}
		if ( ! $this->is_public_path( $path, $request['public_paths'] ?? self::PUBLIC_PATHS ) ) {
			return $this->no( 'not_a_public_path' );
		}
		return array(
			'cache'  => true,
			'ttl'    => $this->is_list( $path ) ? CacheHeaders::LIST_AGE : CacheHeaders::MAX_AGE,
			'reason' => 'public',
		);
	}

	/**
	 * Whether a path is a list or search.
	 *
	 * @param string $path Lower-case path.
	 */
	public function is_list( string $path ): bool {
		foreach ( self::LIST_PATHS as $prefix ) {
			if ( 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a path is on the public list.
	 *
	 * @param string   $path  Lower-case path.
	 * @param string[] $paths Allowed prefixes.
	 */
	private function is_public_path( string $path, array $paths ): bool {
		if ( '/' === $path ) {
			return true;
		}
		foreach ( $paths as $prefix ) {
			if ( 0 === strpos( $path, strtolower( (string) $prefix ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A refusal.
	 *
	 * @param string $reason Reason code.
	 * @return array{cache: bool, ttl: int, reason: string}
	 */
	private function no( string $reason ): array {
		return array(
			'cache'  => false,
			'ttl'    => 0,
			'reason' => $reason,
		);
	}
}
