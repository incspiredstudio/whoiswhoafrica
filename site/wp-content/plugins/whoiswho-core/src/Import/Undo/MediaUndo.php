<?php
/**
 * The media part of a batch undo (REQ-237, contract 1.1 section 10): delete the media library items the batch created, except
 * on profiles a person has reviewed (those keep their image), and except an image that a profile outside the batch also uses
 * (the import reuses an image with the same checksum, so a later batch's profile may show the very same attachment). Such an
 * image stays and passes to the profile that uses it. Where the batch's image had replaced an earlier one, the earlier one
 * becomes the profile image again. It never touches a profile's state. The work can be done in steps under a time budget.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Undo;

use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\ResultRepository;

/**
 * Media undo.
 */
final class MediaUndo {

	/**
	 * Option prefix for the progress of a stepped media undo (the last attachment id handled).
	 */
	public const OPTION_PREFIX = 'wiw_import_media_undo_';

	/**
	 * Delete the batch's media in one go.
	 *
	 * @param string $batch_id Batch id.
	 * @return int Attachments deleted.
	 */
	public function remove( string $batch_id ): int {
		return $this->step( $batch_id )['removed'];
	}

	/**
	 * Delete the batch's media, stopping when the budget is spent; call again to carry on.
	 *
	 * @param string        $batch_id Batch id.
	 * @param callable|null $stop     Returns true when the time budget is spent; checked before each image.
	 * @return array{removed: int, done: bool} Deleted in this call, and whether every image of the batch is handled.
	 */
	public function step( string $batch_id, ?callable $stop = null ): array {
		$key      = self::OPTION_PREFIX . substr( md5( $batch_id ), 0, 20 );
		$last     = (int) get_option( $key, 0 );
		$states   = new StateRepository();
		$in_batch = $this->batch_profiles( $batch_id );
		$removed  = 0;
		foreach ( $this->attachments( $batch_id, $last ) as $id ) {
			if ( null !== $stop && $stop() ) {
				update_option( $key, $last, false );
				return array(
					'removed' => $removed,
					'done'    => false,
				);
			}
			if ( $this->handle( $id, $in_batch, $states ) ) {
				++$removed;
			}
			$last = $id;
		}
		delete_option( $key );
		return array(
			'removed' => $removed,
			'done'    => true,
		);
	}

	/**
	 * Handle one attachment of the batch.
	 *
	 * @param int             $id       Attachment.
	 * @param array<int, int> $in_batch The batch's profiles (id => id).
	 * @param StateRepository $states   States.
	 * @return bool Whether it was deleted.
	 */
	private function handle( int $id, array $in_batch, StateRepository $states ): bool {
		$parent = (int) wp_get_post_parent_id( $id );
		$users  = $this->profiles_using( $id );
		if ( $parent > 0 && ! in_array( $parent, $users, true ) ) {
			$users[] = $parent;
		}
		$keepers = array();
		$leavers = array();
		foreach ( $users as $profile_id ) {
			if ( ! isset( $in_batch[ $profile_id ] ) || $this->kept( $states, $profile_id ) ) {
				$keepers[] = $profile_id;
			} else {
				$leavers[] = $profile_id;
			}
		}
		foreach ( $leavers as $profile_id ) {
			$this->let_go( $profile_id, $id );
		}
		if ( array() !== $keepers ) {
			if ( ! in_array( $parent, $keepers, true ) ) {
				// The image now belongs to a profile that stays, and no longer counts as the undone batch's.
				wp_update_post(
					array(
						'ID'          => $id,
						'post_parent' => $keepers[0],
					)
				);
				delete_post_meta( $id, ImageMeta::BATCH );
			}
			return false;
		}
		return false !== wp_delete_attachment( $id, true );
	}

	/**
	 * A profile of the undone batch stops using the image: the image it replaced comes back, else it has none.
	 *
	 * @param int $profile_id Profile.
	 * @param int $id         Attachment.
	 */
	private function let_go( int $profile_id, int $id ): void {
		if ( (int) get_post_meta( $profile_id, PublicProfile::IMAGE_META, true ) !== $id ) {
			return;
		}
		$previous = (int) get_post_meta( $id, ImageMeta::REPLACED, true );
		if ( $previous > 0 && $previous !== $id && null !== get_post( $previous ) ) {
			update_post_meta( $profile_id, PublicProfile::IMAGE_META, $previous );
		} else {
			delete_post_meta( $profile_id, PublicProfile::IMAGE_META );
		}
	}

	/**
	 * The batch's attachments after an id, oldest first.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $after    Attachment id already handled.
	 * @return int[]
	 */
	private function attachments( string $batch_id, int $after ): array {
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'meta_key'         => ImageMeta::BATCH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- undo only.
				'meta_value'       => $batch_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- undo only.
				'suppress_filters' => true,
			)
		);
		return array_values( array_filter( array_map( 'intval', $ids ), static fn ( int $id ): bool => $id > $after ) );
	}

	/**
	 * The profiles whose image is this attachment.
	 *
	 * @param int $id Attachment.
	 * @return int[]
	 */
	private function profiles_using( int $id ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", PublicProfile::IMAGE_META, (string) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one lookup per undone image.
		return array_values( array_unique( array_map( 'intval', $rows ) ) );
	}

	/**
	 * The profiles the batch created or updated.
	 *
	 * @param string $batch_id Batch id.
	 * @return array<int, int> id => id.
	 */
	private function batch_profiles( string $batch_id ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT profile_id FROM ' . ResultRepository::table() . ' WHERE batch_id = %s AND profile_id IS NOT NULL', $batch_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; value prepared.
		$out  = array();
		foreach ( $rows as $profile_id ) {
			$out[ (int) $profile_id ] = (int) $profile_id;
		}
		return $out;
	}

	/**
	 * Whether a profile keeps its image: a person reviewed it and it was not taken down by this undo.
	 *
	 * @param StateRepository $states     States.
	 * @param int             $profile_id Profile.
	 */
	private function kept( StateRepository $states, int $profile_id ): bool {
		$row   = $states->get( $profile_id );
		$state = $states->pub_state( $profile_id );
		return null !== $row && ProfileApplier::is_reviewed( $row ) && ! in_array( $state, array( PubState::REJECTED, PubState::UNPUBLISHED ), true );
	}
}
