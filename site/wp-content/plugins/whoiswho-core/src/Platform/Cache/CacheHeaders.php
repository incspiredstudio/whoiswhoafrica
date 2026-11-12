<?php
/**
 * Cache headers for public responses (REQ-121, ADR-006). Only an anonymous GET of a public page may be cached, for at most
 * 120 seconds (60 for lists and search). Staff, any other method, and any non-public or non-200 response are never cached.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache;

/**
 * Cache headers helper.
 */
final class CacheHeaders {

	public const MAX_AGE  = 120;
	public const LIST_AGE = 60;
	public const NO_CACHE = 'no-store, no-cache, must-revalidate, max-age=0, s-maxage=0, private';

	/**
	 * Headers for a response.
	 *
	 * @param array{method?: string, staff?: bool, public?: bool, list?: bool, status?: int} $response Facts about the response.
	 * @return array<string, string>
	 */
	public static function for_response( array $response ): array {
		$cacheable = 'GET' === strtoupper( (string) ( $response['method'] ?? 'GET' ) )
			&& empty( $response['staff'] )
			&& ( $response['public'] ?? true )
			&& 200 === (int) ( $response['status'] ?? 200 );
		if ( ! $cacheable ) {
			return array( 'Cache-Control' => self::NO_CACHE );
		}
		$age = ! empty( $response['list'] ) ? self::LIST_AGE : self::MAX_AGE;
		return array( 'Cache-Control' => sprintf( 'public, max-age=%d, s-maxage=%d', $age, $age ) );
	}
}
