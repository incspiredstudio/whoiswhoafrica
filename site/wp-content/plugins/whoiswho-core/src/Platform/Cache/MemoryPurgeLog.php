<?php
/**
 * In-memory purge log for tests.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache;

/**
 * Memory purge log.
 */
final class MemoryPurgeLog implements PurgeLog {

	/**
	 * Rows, oldest first.
	 *
	 * @var array<int, array{subject_type: string, subject_id: int, url_count: int, result: string, created_at: string}>
	 */
	private array $rows = array();

	/**
	 * Record an attempt.
	 *
	 * @param string $subject_type Type.
	 * @param int    $subject_id   Id.
	 * @param int    $url_count    Count.
	 * @param string $result       Result.
	 */
	public function add( string $subject_type, int $subject_id, int $url_count, string $result ): void {
		$this->rows[] = array(
			'subject_type' => $subject_type,
			'subject_id'   => $subject_id,
			'url_count'    => $url_count,
			'result'       => $result,
			'created_at'   => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Newest first.
	 *
	 * @param int $limit  Limit.
	 * @param int $offset Offset.
	 * @return array<int, array{subject_type: string, subject_id: int, url_count: int, result: string, created_at: string}>
	 */
	public function rows( int $limit = 50, int $offset = 0 ): array {
		return array_slice( array_reverse( $this->rows ), $offset, $limit );
	}
}
