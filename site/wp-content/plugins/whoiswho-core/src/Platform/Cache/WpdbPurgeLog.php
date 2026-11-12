<?php
/**
 * The purge log in the wiw_purge_log table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache;

/**
 * Table-backed purge log.
 */
final class WpdbPurgeLog implements PurgeLog {

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wiw_purge_log';
	}

	/**
	 * Record an attempt.
	 *
	 * @param string $subject_type Type.
	 * @param int    $subject_id   Id.
	 * @param int    $url_count    Count.
	 * @param string $result       Result.
	 * @param string $reason       Reason.
	 */
	public function add( string $subject_type, int $subject_id, int $url_count, string $result, string $reason = '' ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'subject_type' => substr( $subject_type, 0, 30 ),
				'subject_id'   => $subject_id,
				'url_count'    => $url_count,
				'result'       => $result,
				'reason'       => substr( $reason, 0, 120 ),
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			)
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
		global $wpdb;
		$sql  = 'SELECT subject_type, subject_id, url_count, result, reason, created_at FROM ' . self::table() . ' WHERE 1 = 1';
		$args = array();
		if ( ! empty( $filters['status'] ) ) {
			$sql   .= ' AND result = %s';
			$args[] = $filters['status'];
		}
		if ( ! empty( $filters['from'] ) ) {
			$sql   .= ' AND created_at >= %s';
			$args[] = $filters['from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['to'] ) ) {
			$sql   .= ' AND created_at <= %s';
			$args[] = $filters['to'] . ' 23:59:59';
		}
		$sql   .= ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;
		$found  = (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name; values prepared.
		$out    = array();
		foreach ( $found as $row ) {
			$out[] = array(
				'subject_type' => (string) $row['subject_type'],
				'subject_id'   => (int) $row['subject_id'],
				'url_count'    => (int) $row['url_count'],
				'result'       => (string) $row['result'],
				'reason'       => (string) $row['reason'],
				'created_at'   => (string) $row['created_at'],
			);
		}
		return $out;
	}
}
