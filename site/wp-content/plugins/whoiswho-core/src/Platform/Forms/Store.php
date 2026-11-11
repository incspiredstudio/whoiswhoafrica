<?php
/**
 * Where the rate limiter keeps its timestamps. Keys are already hashed: the store never sees a raw IP or email.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Key-value store with expiry.
 */
interface Store {

	/**
	 * Read a list of timestamps.
	 *
	 * @param string $key Hashed key.
	 * @return int[]
	 */
	public function get( string $key ): array;

	/**
	 * Save a list of timestamps.
	 *
	 * @param string $key     Hashed key.
	 * @param int[]  $values  Timestamps.
	 * @param int    $ttl     Seconds to keep.
	 */
	public function set( string $key, array $values, int $ttl ): void;
}
