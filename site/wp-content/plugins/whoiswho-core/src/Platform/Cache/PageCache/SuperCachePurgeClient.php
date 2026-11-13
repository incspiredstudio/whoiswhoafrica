<?php
/**
 * Purges URLs from the WP Super Cache page cache, so a profile that stops being public is not served from a cached copy
 * (REQ-209, ADR-006). The PurgeService calls it after the unpublish has committed. The deleter is injected so tests need not load
 * the cache plugin; by default it is the plugin's own wpsc_delete_url_cache().
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache\PageCache;

use WhoIsWho\Core\Platform\Cache\PurgeClient;

/**
 * Page-cache purge client.
 */
final class SuperCachePurgeClient implements PurgeClient {

	/**
	 * Deletes one URL from the cache; returns true when something was asked to go.
	 *
	 * @var callable(string): bool
	 */
	private $deleter;

	/**
	 * Whether the plugin's own function is used.
	 *
	 * @var bool
	 */
	private bool $default;

	/**
	 * Constructor.
	 *
	 * @param callable(string): bool|null $deleter Deleter; null uses the plugin's function.
	 */
	public function __construct( ?callable $deleter = null ) {
		$this->default = null === $deleter;
		$this->deleter = $deleter ?? static function ( string $url ): bool {
			return function_exists( 'wpsc_delete_url_cache' ) ? (bool) wpsc_delete_url_cache( trailingslashit( $url ) ) : false;
		};
	}

	/**
	 * Whether the cache plugin is loaded and so a purge means something.
	 */
	public static function available(): bool {
		return function_exists( 'wpsc_delete_url_cache' );
	}

	/**
	 * Purge URLs.
	 *
	 * @param string[] $urls Absolute URLs.
	 * @return string ok when every URL was cleared; skipped when there is no cache to clear.
	 * @throws \RuntimeException When a URL could not be cleared, so the job retries.
	 */
	public function purge( array $urls ): string {
		if ( array() === $urls ) {
			return 'ok';
		}
		if ( $this->default && ! self::available() ) {
			return 'skipped';
		}
		$failed = 0;
		foreach ( $urls as $url ) {
			$url = (string) $url;
			// The cache keys pages by path, so a URL with a query string was never stored; nothing to remove.
			if ( false !== strpos( $url, '?' ) ) {
				continue;
			}
			if ( ! ( $this->deleter )( $url ) ) {
				++$failed;
			}
		}
		if ( $failed > 0 ) {
			throw new \RuntimeException( sprintf( 'The page cache could not clear %d URL(s).', $failed ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- a count, not user input.
		}
		return 'ok';
	}
}
