<?php
/**
 * The sources snapshot (REQ-136, REQ-243): one row per source named in an import manifest (source_id and display name), updated by
 * every batch. The manifest is the only source of names (contract question U8 is open). The website never edits this table by hand.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Sources;

/**
 * Source snapshot table.
 */
final class SourceRepository {

	public const TABLE = 'wiw_sources';

	public const TABLE_DEFINITION = 'source_id varchar(100) NOT NULL,
		label varchar(190) NOT NULL,
		first_batch_id varchar(64) NOT NULL,
		last_batch_id varchar(64) NOT NULL,
		last_import_at datetime NOT NULL,
		PRIMARY KEY  (source_id)';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create or update a row for each source in a manifest. An entry without a usable source_id or name is skipped.
	 *
	 * @param array<int, mixed> $sources  The manifest's sources array.
	 * @param string            $batch_id The batch the manifest belongs to.
	 * @return int Number of sources written.
	 */
	public function record_manifest( array $sources, string $batch_id ): int {
		global $wpdb;
		$written = 0;
		$now     = gmdate( 'Y-m-d H:i:s' );
		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$id    = isset( $source['source_id'] ) && is_string( $source['source_id'] ) ? trim( $source['source_id'] ) : '';
			$label = isset( $source['name'] ) && is_string( $source['name'] ) ? trim( $source['name'] ) : '';
			if ( '' === $id || strlen( $id ) > 100 || '' === $label ) {
				continue;
			}
			$label = mb_substr( $label, 0, 190 );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table() . ' (source_id, label, first_batch_id, last_batch_id, last_import_at) VALUES (%s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE label = VALUES(label), last_batch_id = VALUES(last_batch_id), last_import_at = VALUES(last_import_at)', $id, $label, $batch_id, $batch_id, $now ) );
			++$written;
		}
		return $written;
	}

	/**
	 * Every source, by label.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY label ASC, source_id ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		return is_array( $rows ) ? $rows : array();
	}
}
