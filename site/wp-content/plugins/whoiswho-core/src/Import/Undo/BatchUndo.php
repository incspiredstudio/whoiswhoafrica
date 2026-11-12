<?php
/**
 * Undo of an applied import batch (REQ-237, contract U14 default). Records the batch created and nobody has reviewed are taken
 * down through the state machine (and forgotten by the record map, so a later import creates them afresh); records the batch
 * updated and nobody has reviewed go back to the values of the version before the update, as a new version; every record a
 * person has reviewed is kept and listed by record id. Undo of a batch with reviewed records is allowed for the rest. One audit
 * row carries the counts.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Undo;

use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Db\Transaction;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Batch undo.
 */
final class BatchUndo {

	public const CAPABILITY = 'wiw_run_imports';

	/**
	 * Undo a batch.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $actor_id The person undoing it.
	 * @return array{removed: int, reverted: int, kept: int, kept_record_ids: string[]}
	 * @throws StateException When refused.
	 */
	public function undo( string $batch_id, int $actor_id ): array {
		if ( ! user_can( $actor_id, self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw StateException::refuse( 'Not allowed.' );
		}
		$batches = new BatchRepository();
		$batch   = $batches->get( $batch_id );
		if ( null === $batch || 'applied' !== $batch['status'] ) {
			throw StateException::refuse( 'Only an applied batch can be undone.' );
		}
		$counts = array(
			'removed'         => 0,
			'reverted'        => 0,
			'kept'            => 0,
			'kept_record_ids' => array(),
		);
		$states = new StateRepository();
		$map    = new RecordMap();
		foreach ( $this->entries( $batch_id ) as $entry ) {
			$profile_id = $entry['profile_id'];
			$row        = $states->get( $profile_id );
			if ( null === $row ) {
				continue;
			}
			if ( ProfileApplier::is_reviewed( $row ) ) {
				++$counts['kept'];
				$counts['kept_record_ids'][] = $entry['record_id'];
				continue;
			}
			if ( ProfileApplier::CREATED === $entry['code'] ) {
				$this->remove( $profile_id, (string) $row['pub_state'], $batch_id, $actor_id );
				$map->unlink_profile( $profile_id );
				++$counts['removed'];
			} elseif ( ProfileApplier::UPDATED === $entry['code'] && $this->revert( $profile_id, $batch_id, $actor_id ) ) {
				++$counts['reverted'];
			}
		}
		$batches->advance( $batch_id, (int) $batch['progress_cursor'], 'undone' );
		AuditLogger::for_site()->log(
			'import.undo',
			array(
				'type' => 'import_batch',
				'id'   => $batch_id,
			),
			null,
			array(
				'removed'  => $counts['removed'],
				'reverted' => $counts['reverted'],
				'kept'     => $counts['kept'],
			),
			'',
			$batch_id
		);
		return $counts;
	}

	/**
	 * The batch's profile results: record id, code and profile.
	 *
	 * @param string $batch_id Batch id.
	 * @return array<int, array{record_id: string, code: string, profile_id: int}>
	 */
	private function entries( string $batch_id ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			$wpdb->prepare( 'SELECT record_id, code, profile_id FROM ' . ResultRepository::table() . ' WHERE batch_id = %s AND profile_id IS NOT NULL ORDER BY id ASC', $batch_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'record_id'  => (string) $row['record_id'],
				'code'       => (string) $row['code'],
				'profile_id' => (int) $row['profile_id'],
			);
		}
		return $out;
	}

	/**
	 * Take a created, unreviewed profile down.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $state      Its state.
	 * @param string $batch_id   Batch.
	 * @param int    $actor_id   Person.
	 */
	private function remove( int $profile_id, string $state, string $batch_id, int $actor_id ): void {
		$to = PubState::is_public( $state ) ? PubState::UNPUBLISHED : PubState::REJECTED;
		( new StateMachine() )->transition( $profile_id, $to, 'Import batch ' . $batch_id . ' undone', $actor_id, $batch_id );
	}

	/**
	 * Put an updated, unreviewed profile back to the values before the batch's update, as a new version.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $batch_id   Batch.
	 * @param int    $actor_id   Person.
	 * @return bool Whether a version to go back to was found.
	 */
	private function revert( int $profile_id, string $batch_id, int $actor_id ): bool {
		$versions = new VersionRepository();
		$reason   = 'Updated by import batch ' . $batch_id;
		foreach ( $versions->for_profile( $profile_id ) as $version ) {
			if ( 'import' !== $version['created_via'] || $reason !== (string) $version['reason'] || null !== $version['approval_status'] ) {
				continue;
			}
			$before = json_decode( (string) $version['old_value'], true );
			$after  = json_decode( (string) $version['new_value'], true );
			if ( ! is_array( $before ) || ! is_array( $after ) ) {
				return false;
			}
			$fields = new ProfileFields();
			Transaction::run(
				static function () use ( $fields, $versions, $profile_id, $actor_id, $batch_id, $before, $after ): void {
					foreach ( $before as $key => $value ) {
						$fields->set( $profile_id, (string) $key, $value );
					}
					$versions->append( $profile_id, $actor_id, 'restore', 'Import batch ' . $batch_id . ' undone', $after, $before );
				}
			);
			return true;
		}
		return false;
	}
}
