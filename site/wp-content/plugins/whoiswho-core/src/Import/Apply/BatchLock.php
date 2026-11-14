<?php
/**
 * One worker per batch at a time (REQ-237). The Import screen drives a batch in steps while the background job may also run it,
 * so each chunk takes this lock first: an option added only when absent, which the database makes atomic. A lock older than the
 * stale time (a worker that died) is taken over.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Apply;

/**
 * Batch lock.
 */
final class BatchLock {

	public const PREFIX = 'wiw_import_lock_';

	/**
	 * Seconds after which a lock is treated as left behind.
	 */
	public const STALE = 90;

	/**
	 * Take the lock of a batch.
	 *
	 * @param string $batch_id Batch.
	 * @return bool Whether this worker now holds it.
	 */
	public static function acquire( string $batch_id ): bool {
		$key = self::key( $batch_id );
		if ( add_option( $key, (string) time(), '', false ) ) {
			return true;
		}
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$since = (int) get_option( $key, '0' );
		if ( $since > 0 && time() - $since < self::STALE ) {
			return false;
		}
		delete_option( $key );
		return add_option( $key, (string) time(), '', false );
	}

	/**
	 * Release the lock of a batch.
	 *
	 * @param string $batch_id Batch.
	 */
	public static function release( string $batch_id ): void {
		delete_option( self::key( $batch_id ) );
	}

	/**
	 * The option name.
	 *
	 * @param string $batch_id Batch.
	 */
	private static function key( string $batch_id ): string {
		return self::PREFIX . substr( md5( $batch_id ), 0, 20 );
	}
}
