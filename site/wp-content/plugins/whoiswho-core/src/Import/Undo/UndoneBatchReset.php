<?php
/**
 * Importing an undone batch again (REQ-237, #810). The undo took the batch's unreviewed profiles down (Rejected or Unpublished)
 * and let the record map forget them. Before the same batch id is registered again, this remembers each such record's profile
 * (and puts a Rejected one back to Imported - not public, the only move the state machine allows from Rejected), so that when
 * the new import reaches the record, ProfileApplier takes the same profile back (take()) and sets its fields and state again
 * instead of making a duplicate. A record the map already links to another profile (imported again in a later batch) is left
 * alone. The old batch row, its results, its image log and any leftover progress are removed, so the batch starts afresh.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Undo;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\DryRun\DryRun;
use WhoIsWho\Core\Import\Images\ImageLog;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Undone batch reset.
 */
final class UndoneBatchReset {

	/**
	 * Option prefix for the profiles a re-imported batch takes back: record id => profile id.
	 */
	public const OPTION_PREFIX = 'wiw_import_reopen_';

	/**
	 * Get an undone batch ready to be registered again.
	 *
	 * @param string $batch_id Batch id.
	 * @return int Profiles that will be taken back.
	 */
	public function reset( string $batch_id ): int {
		global $wpdb;
		$map     = new RecordMap();
		$states  = new StateRepository();
		$machine = new StateMachine();
		$linked    = 0;
		$reopen    = array();
		$old       = ( new BatchRepository() )->get( $batch_id );
		$since     = null === $old ? '' : (string) $old['created_at'];
		$rows    = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT record_id, code, profile_id FROM ' . ResultRepository::table() . ' WHERE batch_id = %s AND profile_id IS NOT NULL', $batch_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; value prepared.
		foreach ( $rows as $row ) {
			$profile_id = (int) $row['profile_id'];
			$record_id  = (string) $row['record_id'];
			$state_row  = $states->get( $profile_id );
			if ( ProfileApplier::CREATED !== (string) $row['code'] || null === get_post( $profile_id ) || null === $state_row || ! $this->taken_down_by_undo( $state_row, $since ) ) {
				continue;
			}
			$state = (string) $state_row['pub_state'];
			if ( ! in_array( $state, array( PubState::REJECTED, PubState::UNPUBLISHED ), true ) || null !== $map->profile_for( $record_id ) || null !== $map->record_for( $profile_id ) ) {
				continue;
			}
			try {
				if ( PubState::REJECTED === $state ) {
					$machine->transition( $profile_id, PubState::IMPORTED_NOT_PUBLIC, 'Import batch ' . $batch_id . ' imported again' );
				}
			} catch ( StateException $e ) {
				unset( $e );
				continue;
			}
			$reopen[ $record_id ] = $profile_id;
			++$linked;
		}
		update_option( self::key( $batch_id ), $reopen, false );
		$wpdb->delete( ResultRepository::table(), array( 'batch_id' => $batch_id ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the batch starts afresh.
		$wpdb->delete( BatchRepository::table(), array( 'batch_id' => $batch_id ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- as above.
		ImageLog::clear( $batch_id );
		DryRun::reset( $batch_id );
		delete_option( BatchUndo::STATE_PREFIX . substr( md5( $batch_id ), 0, 20 ) );
		delete_option( MediaUndo::OPTION_PREFIX . substr( md5( $batch_id ), 0, 20 ) );
		AuditLogger::for_site()->log(
			'import.reopen',
			array(
				'type' => 'import_batch',
				'id'   => $batch_id,
			),
			null,
			array( 'taken_back' => $linked ),
			'An undone batch is imported again',
			$batch_id
		);
		return $linked;
	}
	/**
	 * Whether the undo (not a person) left the profile in its state: nobody reviewed it, or it is Rejected with a review dated
	 * after the batch was registered (the state machine records the person who undid the batch as the reviewer of a profile the
	 * undo rejected). Taken back, a profile only returns to a state that is not public until the import sets it again.
	 *
	 * @param array<string, mixed> $row   State row.
	 * @param string               $since When the batch was first registered (UTC), or ''.
	 */
	private function taken_down_by_undo( array $row, string $since ): bool {
		if ( empty( $row['reviewer_id'] ) ) {
			return true;
		}
		return PubState::REJECTED === (string) $row['pub_state'] && '' !== $since && (string) $row['reviewed_at'] >= $since;
	}

	/**
	 * The profile a re-imported batch takes back for a record, once; null when there is none.
	 *
	 * @param string $batch_id  Batch id.
	 * @param string $record_id Record id.
	 */
	public static function take( string $batch_id, string $record_id ): ?int {
		$reopen = get_option( self::key( $batch_id ), array() );
		if ( ! is_array( $reopen ) || ! isset( $reopen[ $record_id ] ) ) {
			return null;
		}
		$profile_id = (int) $reopen[ $record_id ];
		unset( $reopen[ $record_id ] );
		if ( array() === $reopen ) {
			delete_option( self::key( $batch_id ) );
		} else {
			update_option( self::key( $batch_id ), $reopen, false );
		}
		return $profile_id > 0 ? $profile_id : null;
	}

	/**
	 * The option for a batch.
	 *
	 * @param string $batch_id Batch id.
	 */
	private static function key( string $batch_id ): string {
		return self::OPTION_PREFIX . substr( md5( $batch_id ), 0, 20 );
	}
}
