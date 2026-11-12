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
	 */
	public function add( string $subject_type, int $subject_id, int $url_count, string $result ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'subject_type' => substr( $subject_type, 0, 30 ),
				'subject_id'   => $subject_id,
				'url_count'    => $url_count,
				'result'       => $result,
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			)
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
		global $wpdb;
		$found = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			$wpdb->prepare( 'SELECT subject_type, subject_id, url_count, result, created_at FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d OFFSET %d', $limit, $offset ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			ARRAY_A
		);
		$out   = array();
		foreach ( $found as $row ) {
			$out[] = array(
				'subject_type' => (string) $row['subject_type'],
				'subject_id'   => (int) $row['subject_id'],
				'url_count'    => (int) $row['url_count'],
				'result'       => (string) $row['result'],
				'created_at'   => (string) $row['created_at'],
			);
		}
		return $out;
	}
}
