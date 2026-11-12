<?php
/**
 * The audit row of an applied import (REQ-228, REQ-051): the person who confirmed it, the batch, the file hash, the counts by
 * result code and the states the batch's profiles are in. Ids, hashes and counts only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Result;

use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Import audit.
 */
final class ImportAudit {

	/**
	 * Write the row.
	 *
	 * @param array<string, mixed> $batch A batch row from BatchRepository::get().
	 */
	public function record( array $batch ): void {
		$batch_id = (string) $batch['batch_id'];
		AuditLogger::for_site()->log(
			'import.apply',
			array(
				'type' => 'import_batch',
				'id'   => $batch_id,
			),
			null,
			array(
				'counts'       => ( new ResultRepository() )->counts( $batch_id ),
				'confirmed_by' => (int) $batch['created_by'],
				'file_hash'    => (string) $batch['file_hash'],
				'states'       => $this->states( $batch_id ),
			),
			'',
			$batch_id
		);
	}

	/**
	 * Profiles of the batch counted by publication state.
	 *
	 * @param string $batch_id Batch id.
	 * @return array<string, int>
	 */
	private function states( string $batch_id ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			$wpdb->prepare( 'SELECT ' . StateRepository::STATE_COLUMN . ' AS state, COUNT(*) AS n FROM ' . StateRepository::table() . ' WHERE import_batch = %s GROUP BY ' . StateRepository::STATE_COLUMN . ' ORDER BY state', $batch_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (string) $row['state'] ] = (int) $row['n'];
		}
		return $out;
	}
}
