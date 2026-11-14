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
use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\PublicProfile;
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

	public const STATE_PREFIX = 'wiw_import_undo_';

	/**
	 * Undo a batch.
	 *
	 * @param string        $batch_id Batch id.
	 * @param int           $actor_id The person undoing it.
	 * @param callable|null $stop Returns true when the time budget is spent; checked before each record. A large batch is then
	 *                            undone over several calls: progress is kept, and the batch becomes undone after the last.
	 * @return array{removed: int, reverted: int, kept: int, kept_record_ids: string[], media_removed: int, done: bool}
	 * @throws StateException When refused.
	 */
	public function undo( string $batch_id, int $actor_id, ?callable $stop = null ): array {
		if ( ! user_can( $actor_id, self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw StateException::refuse( 'Not allowed.' );
		}
		$batches = new BatchRepository();
		$batch   = $batches->get( $batch_id );
		if ( null === $batch || 'applied' !== $batch['status'] ) {
			throw StateException::refuse( 'Only an applied batch can be undone.' );
		}
		$state  = self::progress( $batch_id );
		$counts = $state['counts'];
		$states = new StateRepository();
		$map    = new RecordMap();
		foreach ( $this->entries( $batch_id, $state['last_id'] ) as $entry ) {
			if ( null !== $stop && $stop() ) {
				$counts['done'] = false;
				return $counts;
			}
			$profile_id = $entry['profile_id'];
			$row        = $states->get( $profile_id );
			if ( null !== $row && ProfileApplier::is_reviewed( $row ) ) {
				++$counts['kept'];
				$counts['kept_record_ids'][] = $entry['record_id'];
			} elseif ( null !== $row && ProfileApplier::CREATED === $entry['code'] ) {
				$this->remove( $profile_id, (string) $row['pub_state'], $batch_id, $actor_id );
				$map->unlink_profile( $profile_id );
				++$counts['removed'];
			} elseif ( null !== $row && ProfileApplier::UPDATED === $entry['code'] && $this->revert( $profile_id, $batch_id, $actor_id ) ) {
				++$counts['reverted'];
			}
			update_option(
				self::STATE_PREFIX . substr( md5( $batch_id ), 0, 20 ),
				array(
					'last_id' => $entry['id'],
					'counts'  => $counts,
				),
				false
			);
		}//end foreach
		$counts['media_removed'] = $this->remove_media( $batch_id );
		delete_option( self::STATE_PREFIX . substr( md5( $batch_id ), 0, 20 ) );
		$batches->advance( $batch_id, (int) $batch['progress_cursor'], 'undone' );
		AuditLogger::for_site()->log(
			'import.undo',
			array(
				'type' => 'import_batch',
				'id'   => $batch_id,
			),
			null,
			array(
				'removed'       => $counts['removed'],
				'reverted'      => $counts['reverted'],
				'kept'          => $counts['kept'],
				'media_removed' => $counts['media_removed'],
			),
			'',
			$batch_id
		);
		return $counts;
	}

	/**
	 * Whether an undo of the batch has started and not finished.
	 *
	 * @param string $batch_id Batch id.
	 */
	public static function in_progress( string $batch_id ): bool {
		return false !== get_option( self::STATE_PREFIX . substr( md5( $batch_id ), 0, 20 ), false );
	}

	/**
	 * The kept progress of an undo: the last result row handled and the counts so far.
	 *
	 * @param string $batch_id Batch id.
	 * @return array{last_id: int, counts: array{removed: int, reverted: int, kept: int, kept_record_ids: string[], media_removed: int, done: bool}}
	 */
	private static function progress( string $batch_id ): array {
		$saved  = get_option( self::STATE_PREFIX . substr( md5( $batch_id ), 0, 20 ), array() );
		$saved  = is_array( $saved ) ? $saved : array();
		$counts = is_array( $saved['counts'] ?? null ) ? $saved['counts'] : array();
		return array(
			'last_id' => (int) ( $saved['last_id'] ?? 0 ),
			'counts'  => array(
				'removed'         => (int) ( $counts['removed'] ?? 0 ),
				'reverted'        => (int) ( $counts['reverted'] ?? 0 ),
				'kept'            => (int) ( $counts['kept'] ?? 0 ),
				'kept_record_ids' => array_map( 'strval', (array) ( $counts['kept_record_ids'] ?? array() ) ),
				'media_removed'   => 0,
				'done'            => true,
			),
		);
	}

	/**
	 * Delete the media library files the batch created, except on profiles a person reviewed (those are kept with their image).
	 * Where the batch's image had replaced an earlier one, the earlier one is the profile's image again.
	 *
	 * @param string $batch_id Batch id.
	 * @return int Attachments deleted.
	 */
	private function remove_media( string $batch_id ): int {
		$ids     = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'meta_key'         => ImageMeta::BATCH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- undo only.
				'meta_value'       => $batch_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- undo only.
				'suppress_filters' => true,
			)
		);
		$states  = new StateRepository();
		$removed = 0;
		foreach ( $ids as $id ) {
			$id         = (int) $id;
			$profile_id = (int) wp_get_post_parent_id( $id );
			$row        = $profile_id > 0 ? $states->get( $profile_id ) : null;
			if ( null !== $row && ProfileApplier::is_reviewed( $row ) && PubState::REJECTED !== $row['pub_state'] && PubState::UNPUBLISHED !== $row['pub_state'] ) {
				continue;
			}
			if ( $profile_id > 0 && (int) get_post_meta( $profile_id, PublicProfile::IMAGE_META, true ) === $id ) {
				$previous = (int) get_post_meta( $id, ImageMeta::REPLACED, true );
				if ( $previous > 0 && null !== get_post( $previous ) ) {
					update_post_meta( $profile_id, PublicProfile::IMAGE_META, $previous );
				} else {
					delete_post_meta( $profile_id, PublicProfile::IMAGE_META );
				}
			}
			if ( false !== wp_delete_attachment( $id, true ) ) {
				++$removed;
			}
		}
		return $removed;
	}

	/**
	 * The batch's profile results after a result row: row id, record id, code and profile.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $after    Result row id already handled.
	 * @return array<int, array{id: int, record_id: string, code: string, profile_id: int}>
	 */
	private function entries( string $batch_id, int $after = 0 ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			$wpdb->prepare( 'SELECT id, record_id, code, profile_id FROM ' . ResultRepository::table() . ' WHERE batch_id = %s AND profile_id IS NOT NULL AND id > %d ORDER BY id ASC', $batch_id, $after ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id'         => (int) $row['id'],
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
