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
	 * @var array<int, array{subject_type: string, subject_id: int, url_count: int, result: string, reason: string, created_at: string}>
	 */
	private array $rows = array();

	/**
	 * Record an attempt.
	 *
	 * @param string $subject_type Type.
	 * @param int    $subject_id   Id.
	 * @param int    $url_count    Count.
	 * @param string $result       Result.
	 * @param string $reason       Reason.
	 * @param string $at           Time (tests may set it).
	 */
	public function add( string $subject_type, int $subject_id, int $url_count, string $result, string $reason = '', string $at = '' ): void {
		$this->rows[] = array(
			'subject_type' => $subject_type,
			'subject_id'   => $subject_id,
			'url_count'    => $url_count,
			'result'       => $result,
			'reason'       => $reason,
			'created_at'   => '' === $at ? gmdate( 'Y-m-d H:i:s' ) : $at,
		);
	}

	/**
	 * Newest first.
	 *
	 * @param int                                                $limit   Limit.
	 * @param int                                                $offset  Offset.
	 * @param array{status?: string, from?: string, to?: string} $filters Filters.
	 * @return array<int, array{subject_type: string, subject_id: int, url_count: int, result: string, reason: string, created_at: string}>
	 */
	public function rows( int $limit = 50, int $offset = 0, array $filters = array() ): array {
		$rows = array_filter(
			$this->rows,
			static function ( array $row ) use ( $filters ): bool {
				$day = substr( $row['created_at'], 0, 10 );
				return ( empty( $filters['status'] ) || $row['result'] === $filters['status'] )
					&& ( empty( $filters['from'] ) || $day >= $filters['from'] )
					&& ( empty( $filters['to'] ) || $day <= $filters['to'] );
			}
		);
		return array_slice( array_reverse( array_values( $rows ) ), $offset, $limit );
	}
}
