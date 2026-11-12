<?php
/**
 * Import results (REQ-237, contract section 12): one row per record per batch holding the record id and the result code, never a
 * field value. The unique key on batch and record makes a resumed run safe: a record already recorded is not recorded twice.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Apply;

/**
 * Result repository.
 */
final class ResultRepository {

	public const TABLE = 'wiw_import_results';

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		batch_id varchar(64) NOT NULL,
		record_id varchar(64) NOT NULL,
		code varchar(24) NOT NULL,
		profile_id bigint(20) unsigned NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY batch_record (batch_id, record_id),
		KEY code (batch_id, code),
		KEY profile (profile_id)';

	public const APPLIED_CODES = array( 'CREATED', 'UNCHANGED', 'UPDATED', 'FLAGGED_CHANGE' );

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Record a result. A second result for the same batch and record is ignored.
	 *
	 * @param string   $batch_id   Batch id.
	 * @param string   $record_id  Record id (may be empty for a line that could not be read).
	 * @param string   $code       Result or refusal code.
	 * @param int|null $profile_id Profile post id, when there is one.
	 * @return bool Whether a row was added.
	 */
	public function record( string $batch_id, string $record_id, string $code, ?int $profile_id = null ): bool {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		$ok       = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'batch_id'   => $batch_id,
				'record_id'  => $record_id,
				'code'       => $code,
				'profile_id' => $profile_id,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$wpdb->suppress_errors( $suppress );
		return false !== $ok;
	}

	/**
	 * Whether a record already has a result in this batch.
	 *
	 * @param string $batch_id  Batch id.
	 * @param string $record_id Record id.
	 */
	public function has( string $batch_id, string $record_id ): bool {
		global $wpdb;
		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE batch_id = %s AND record_id = %s', $batch_id, $record_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * Counts by code for a batch.
	 *
	 * @param string $batch_id Batch id.
	 * @return array<string, int>
	 */
	public function counts( string $batch_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT code, COUNT(*) AS n FROM ' . self::table() . ' WHERE batch_id = %s GROUP BY code ORDER BY code', $batch_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['code'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * The [record_id, code] pairs of a batch, in the order they were recorded: the import result for acquisition (no field values).
	 *
	 * @param string $batch_id Batch id.
	 * @return array<int, array{0: string, 1: string}>
	 */
	public function pairs( string $batch_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT record_id, code FROM ' . self::table() . ' WHERE batch_id = %s ORDER BY id ASC', $batch_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array( (string) $row['record_id'], (string) $row['code'] );
		}
		return $out;
	}

	/**
	 * Profile ids a batch created.
	 *
	 * @param string $batch_id Batch id.
	 * @return int[]
	 */
	public function created_profile_ids( string $batch_id ): array {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT profile_id FROM ' . self::table() . " WHERE batch_id = %s AND code = 'CREATED' AND profile_id IS NOT NULL ORDER BY id ASC", $batch_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return array_map( 'intval', (array) $ids );
	}
}
