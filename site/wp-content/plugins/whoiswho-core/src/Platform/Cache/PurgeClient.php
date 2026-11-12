<?php
/**
 * Asks an edge cache to forget URLs. The default does nothing (no CDN). An optional Cloudflare client is NOT built: Cloudflare is
 * optional (ADR-006). When it is added later it implements this interface and is chosen through the wiw_purge_client filter.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache;

/**
 * Purge client.
 */
interface PurgeClient {

	/**
	 * Purge URLs.
	 *
	 * @param string[] $urls Absolute URLs.
	 * @return string ok when the edge accepted the purge, skipped when there is no edge to ask.
	 * @throws \RuntimeException When the edge refused or could not be reached; the job retries.
	 */
	public function purge( array $urls ): string;
}
