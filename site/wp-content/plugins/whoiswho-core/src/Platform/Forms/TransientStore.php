<?php
/**
 * WordPress transients as the limiter's store (uses the object cache when one is present).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Transient-backed store.
 */
final class TransientStore implements Store {

	/**
	 * Read timestamps.
	 *
	 * @param string $key Hashed key.
	 * @return int[]
	 */
	public function get( string $key ): array {
		$value = get_transient( 'wiw_rl_' . $key );
		return is_array( $value ) ? array_map( 'intval', $value ) : array();
	}

	/**
	 * Save timestamps.
	 *
	 * @param string $key    Hashed key.
	 * @param int[]  $values Timestamps.
	 * @param int    $ttl    Seconds.
	 */
	public function set( string $key, array $values, int $ttl ): void {
		set_transient( 'wiw_rl_' . $key, $values, $ttl );
	}
}
