<?php
/**
 * Imports the open-licence images of one record into the media library (contract 1.1 section 10, REQ-162, REQ-203, D-024,
 * REQ-237). Each image the policy accepted is checked again against its file: inside the bundle's images folder, not a link,
 * at most 5 MB, the sha256 it claims, and really the JPEG, PNG or WebP it says. A good image is sideloaded once (a file whose
 * hash is already in the library is reused), its licence, licence link, creator, attribution text and source page are stored as
 * attachment meta, and it becomes the profile's portrait (people) or logo (organisations). Every refusal is IMAGE_NOT_ALLOWED
 * for that image only. Attachments it creates carry the batch id, so undoing the batch removes them.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Images;

use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\PublicProfile;

/**
 * Image importer.
 */
final class ImageImporter {

	/**
	 * Import the accepted images of a record and set the profile image.
	 *
	 * @param int                              $profile_id  Profile.
	 * @param string                           $entity_type Entity type.
	 * @param string                           $name        Profile name, for the alternative text.
	 * @param array<int, array<string, mixed>> $images      Entries the policy accepted.
	 * @param string                           $bundle_dir  The bundle folder.
	 * @param string                           $batch_id    Batch.
	 * @return array{imported: int, refused: array<int, array{image_id: string, reason: string}>}
	 */
	public function import( int $profile_id, string $entity_type, string $name, array $images, string $bundle_dir, string $batch_id ): array {
		$out   = array(
			'imported' => 0,
			'refused'  => array(),
		);
		$ids   = array();
		$kinds = array();
		foreach ( $images as $image ) {
			$reason = $this->file_refusal( $image, $bundle_dir );
			if ( null === $reason ) {
				try {
					$id = $this->sideload( $profile_id, $name, $image, $bundle_dir, $batch_id );
				} catch ( \Throwable $e ) {
					unset( $e );
					$id     = 0;
					$reason = 'store';
				}
			}
			if ( null !== $reason || empty( $id ) ) {
				$out['refused'][] = array(
					'image_id' => (string) $image['image_id'],
					'reason'   => $reason ?? 'store',
				);
				continue;
			}
			++$out['imported'];
			$ids[]   = $id;
			$kinds[] = (string) $image['kind'];
		}//end foreach
		if ( array() !== $ids ) {
			$want  = in_array( $entity_type, array( 'organisation', 'institution' ), true ) ? 'logo' : 'photo';
			$found = array_search( $want, $kinds, true );
			$this->set_profile_image( $profile_id, $ids[ false === $found ? 0 : (int) $found ] );
		}
		return $out;
	}

	/**
	 * Why the file of an accepted entry is refused, or null.
	 *
	 * @param array<string, mixed> $image      Entry.
	 * @param string               $bundle_dir Bundle folder.
	 */
	public function file_refusal( array $image, string $bundle_dir ): ?string {
		$folder = realpath( $bundle_dir . '/images' );
		$path   = $bundle_dir . '/' . (string) $image['file'];
		$real   = realpath( $path );
		if ( false === $folder || false === $real || is_link( $path ) || ! is_file( $real ) || dirname( str_replace( '\\', '/', $real ) ) !== str_replace( '\\', '/', $folder ) ) {
			return 'missing';
		}
		$size = filesize( $real );
		if ( false === $size || $size < 1 || $size > ImagePolicy::MAX_BYTES ) {
			return 'size';
		}
		if ( ! hash_equals( ImagePolicy::hex( (string) $image['sha256'] ), (string) hash_file( 'sha256', $real ) ) ) {
			return 'hash';
		}
		$info = getimagesize( $real );
		$mime = is_array( $info ) ? (string) $info['mime'] : '';
		if ( $mime !== $image['mime_type'] || ! isset( ImagePolicy::TYPES[ $mime ] ) ) {
			return 'type';
		}
		return null;
	}

	/**
	 * Add the file to the media library, or reuse the attachment that already holds it.
	 *
	 * @param int                  $profile_id Profile.
	 * @param string               $name       Profile name.
	 * @param array<string, mixed> $image      Entry.
	 * @param string               $bundle_dir Bundle folder.
	 * @param string               $batch_id   Batch.
	 * @return int Attachment id, or 0 when it could not be stored.
	 */
	private function sideload( int $profile_id, string $name, array $image, string $bundle_dir, string $batch_id ): int {
		$hex      = ImagePolicy::hex( (string) $image['sha256'] );
		$existing = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'meta_key'         => ImageMeta::SHA256, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one indexed lookup per imported image.
				'meta_value'       => $hex, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
				'suppress_filters' => true,
			)
		);
		if ( array() !== $existing ) {
			$id = (int) $existing[0];
			ImageMeta::save( $id, $image );
			return $id;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( $hex );
		if ( '' === $tmp || ! copy( $bundle_dir . '/' . (string) $image['file'], $tmp ) ) {
			return 0;
		}
		$id = media_handle_sideload(
			array(
				'name'     => basename( (string) $image['file'] ),
				'tmp_name' => $tmp,
			),
			$profile_id,
			$name
		);
		if ( is_wp_error( $id ) ) {
			if ( is_file( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return 0;
		}
		$id = (int) $id;
		ImageMeta::save( $id, $image );
		update_post_meta( $id, ImageMeta::SHA256, $hex );
		update_post_meta( $id, ImageMeta::BATCH, $batch_id );
		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $name ) ) );
		return $id;
	}

	/**
	 * Make an attachment the profile's image, remembering the one it replaced for an undo.
	 *
	 * @param int $profile_id    Profile.
	 * @param int $attachment_id Attachment.
	 */
	private function set_profile_image( int $profile_id, int $attachment_id ): void {
		$previous = (int) get_post_meta( $profile_id, PublicProfile::IMAGE_META, true );
		if ( $previous === $attachment_id ) {
			return;
		}
		if ( $previous > 0 ) {
			update_post_meta( $attachment_id, ImageMeta::REPLACED, $previous );
		}
		update_post_meta( $profile_id, PublicProfile::IMAGE_META, $attachment_id );
	}
}
