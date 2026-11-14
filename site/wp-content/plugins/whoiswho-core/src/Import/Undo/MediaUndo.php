<?php
/**
 * The media part of a batch undo (REQ-237, contract 1.1 section 10): delete the media library items the batch created, except
 * on profiles a person has reviewed (those keep their image). Where the batch's image had replaced an earlier one, the earlier
 * one becomes the profile image again. It never touches a profile's state.
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

/**
 * Media undo.
 */
final class MediaUndo {

	/**
	 * Delete the batch's media.
	 *
	 * @param string $batch_id Batch id.
	 * @return int Attachments deleted.
	 */
	public function remove( string $batch_id ): int {
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
			if ( $profile_id > 0 && $this->kept( $states, $profile_id ) ) {
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
