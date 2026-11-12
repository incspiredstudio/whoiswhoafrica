<?php
/**
 * A purge client for tests: records exactly what it was asked to purge.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache;

/**
 * Fake client.
 */
final class FakeClient implements PurgeClient {

	/**
	 * One entry per call: the URLs passed.
	 *
	 * @var array<int, string[]>
	 */
	public array $calls = array();

	/**
	 * Whether the next calls fail.
	 *
	 * @var bool
	 */
	public bool $failing = false;

	/**
	 * Record the call.
	 *
	 * @param string[] $urls Absolute URLs.
	 * @throws \RuntimeException When set to fail.
	 */
	public function purge( array $urls ): string {
		$this->calls[] = $urls;
		if ( $this->failing ) {
			throw new \RuntimeException( 'The fake edge refused the purge.' );
		}
		return 'ok';
	}
}
