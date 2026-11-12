<?php
/**
 * Import batch rows (REQ-237): the contract version and file hash of each bundle, the settings in force, its status and the
 * progress cursor. A batch id is unique across runs, so a bundle cannot be registered twice.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import;

/**
 * Batch repository.
 */
final class BatchRepository {

	public const TABLE = 'wiw_import_batches';

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		batch_id varchar(64) NOT NULL,
		run_id varchar(64) NOT NULL,
		contract_version varchar(10) NOT NULL,
		file_hash varchar(80) NOT NULL,
		file_name varchar(190) NULL,
		record_count int(10) unsigned NOT NULL DEFAULT 0,
		counts longtext NULL,
		settings_snapshot longtext NULL,
		status varchar(20) NOT NULL,
		progress_cursor int(10) unsigned NOT NULL DEFAULT 0,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY batch_id (batch_id),
		KEY status (status),
		KEY file_hash (file_hash)';

	public const STATUSES = array( 'checked', 'dry_run', 'applying', 'applied', 'undone', 'refused' );

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Register a checked bundle.
	 *
	 * @param BundleReader         $bundle   The opened, checked bundle.
	 * @param int                  $user_id  The Administrator who started the import.
	 * @param array<string, mixed> $settings Settings in force (for example the import-everything-as-not-public switch).
	 * @param string               $file_name Bundle file name, for the history screen.
	 * @return int Row id.
	 * @throws \RuntimeException When the batch id already exists.
	 */
	public function register( BundleReader $bundle, int $user_id, array $settings = array(), string $file_name = '' ): int {
		global $wpdb;
		$m   = $bundle->manifest();
		$now = gmdate( 'Y-m-d H:i:s' );
		$ok  = $this->exists( (string) $m['batch_id'] ) ? false : $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'batch_id'          => (string) $m['batch_id'],
				'run_id'            => (string) $m['run_id'],
				'contract_version'  => (string) $m['contract_version'],
				'file_hash'         => $bundle->file_hash(),
				'file_name'         => '' === $file_name ? null : $file_name,
				'record_count'      => (int) $m['record_count'],
				'counts'            => wp_json_encode( $m['counts'] ),
				'settings_snapshot' => wp_json_encode( $settings ),
				'status'            => 'checked',
				'created_by'        => $user_id,
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);
		if ( false === $ok ) {
			throw new \RuntimeException( 'That batch id is already registered.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Save progress: the number of record lines handled so far and, optionally, the status.
	 *
	 * @param string      $batch_id Batch id.
	 * @param int         $cursor   Lines handled.
	 * @param string|null $status   New status, or null to keep it.
	 * @throws \InvalidArgumentException For an unknown status.
	 */
	public function advance( string $batch_id, int $cursor, ?string $status = null ): void {
		global $wpdb;
		if ( null !== $status && ! in_array( $status, self::STATUSES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown batch status.' );
		}
		$values = array(
			'progress_cursor' => max( 0, $cursor ),
			'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( null !== $status ) {
			$values['status'] = $status;
		}
		$wpdb->update( self::table(), $values, array( 'batch_id' => $batch_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Store the dry run on the batch: the hash of the previewed file and the counts by code, in the settings snapshot, and the
	 * status dry_run. Apply refuses a file whose hash differs.
	 *
	 * @param string             $batch_id  Batch id.
	 * @param string             $file_hash Previewed file hash.
	 * @param array<string, int> $counts    Counts by result code.
	 */
	public function record_dry_run( string $batch_id, string $file_hash, array $counts ): void {
		global $wpdb;
		$batch    = $this->get( $batch_id );
		$snapshot = null === $batch ? array() : json_decode( (string) $batch['settings_snapshot'], true );
		$snapshot = is_array( $snapshot ) ? $snapshot : array();

		$snapshot['dry_run'] = array(
			'file_hash' => $file_hash,
			'counts'    => $counts,
			'at'        => gmdate( 'Y-m-d H:i:s' ),
		);
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'settings_snapshot' => wp_json_encode( $snapshot ),
				'status'            => 'dry_run',
				'updated_at'        => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'batch_id' => $batch_id )
		);
	}

	/**
	 * Whether a batch id is registered.
	 *
	 * @param string $batch_id Batch id.
	 */
	public function exists( string $batch_id ): bool {
		return null !== $this->get( $batch_id );
	}

	/**
	 * One batch by its batch id.
	 *
	 * @param string $batch_id Batch id.
	 * @return array<string, mixed>|null
	 */
	public function get( string $batch_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE batch_id = %s', $batch_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}
}
