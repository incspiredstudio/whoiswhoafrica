<?php
/**
 * The wiw_bulk_actions table: one row per bulk unpublish with its filter, reason, count, actor, times and progress (REQ-193).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Bulk;

/**
 * Bulk actions table access.
 */
class BulkRepository {

	public const TABLE = 'wiw_bulk_actions';

	public const QUEUED   = 'queued';
	public const RUNNING  = 'running';
	public const DONE     = 'done';
	public const RESTORED = 'restored';

	/**
	 * Column and key definitions, used by the migration.
	 */
	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		batch_ref varchar(32) NOT NULL,
		filter_json text NOT NULL,
		reason text NOT NULL,
		match_count int(10) unsigned NOT NULL DEFAULT 0,
		done_count int(10) unsigned NOT NULL DEFAULT 0,
		restored_count int(10) unsigned NOT NULL DEFAULT 0,
		status varchar(12) NOT NULL DEFAULT \'queued\',
		actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		completed_at datetime NULL,
		restored_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY batch_ref (batch_ref),
		KEY created_at (created_at)';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Add a batch.
	 *
	 * @param string               $batch_ref Reference shared with the audit rows.
	 * @param array<string, mixed> $filter    The filter.
	 * @param string               $reason    Why.
	 * @param int                  $count     Matches when it started.
	 * @param int                  $actor_id  Who.
	 */
	public function create( string $batch_ref, array $filter, string $reason, int $count, int $actor_id ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'batch_ref'   => $batch_ref,
				'filter_json' => (string) wp_json_encode( $filter ),
				'reason'      => $reason,
				'match_count' => $count,
				'status'      => self::QUEUED,
				'actor_id'    => $actor_id,
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * One batch row.
	 *
	 * @param string $batch_ref Reference.
	 * @return array<string, mixed>|null
	 */
	public function get( string $batch_ref ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE batch_ref = %s', $batch_ref ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Change columns of a batch.
	 *
	 * @param string               $batch_ref Reference.
	 * @param array<string, mixed> $values    Column => value.
	 */
	public function update( string $batch_ref, array $values ): void {
		global $wpdb;
		$wpdb->update( self::table(), $values, array( 'batch_ref' => $batch_ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Recent batches, newest first.
	 *
	 * @param int $limit How many.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 20 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', max( 1, $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $rows ) ? $rows : array();
	}
}
