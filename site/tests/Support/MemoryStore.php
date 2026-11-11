<?php
/**
 * In-memory store for rate limiter tests.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Support;

use WhoIsWho\Core\Platform\Forms\Store;

/**
 * In-memory store that also lets the test read every key it was given.
 */
final class MemoryStore implements Store {

	/**
	 * Data.
	 *
	 * @var array<string, int[]>
	 */
	public array $data = array();

	/**
	 * Read.
	 *
	 * @param string $key Key.
	 * @return int[]
	 */
	public function get( string $key ): array {
		return $this->data[ $key ] ?? array();
	}

	/**
	 * Write.
	 *
	 * @param string $key    Key.
	 * @param int[]  $values Values.
	 * @param int    $ttl    TTL.
	 */
	public function set( string $key, array $values, int $ttl ): void {
		$this->data[ $key ] = $values;
	}
}
