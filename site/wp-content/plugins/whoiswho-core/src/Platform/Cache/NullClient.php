<?php
/**
 * The default purge client: no CDN is configured, so there is nothing to purge. The purge is still logged.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache;

/**
 * Null client.
 */
final class NullClient implements PurgeClient {

	/**
	 * Does nothing.
	 *
	 * @param string[] $urls Absolute URLs.
	 */
	public function purge( array $urls ): string {
		unset( $urls );
		return 'skipped';
	}
}
