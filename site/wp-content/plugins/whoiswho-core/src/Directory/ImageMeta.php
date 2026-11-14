<?php
/**
 * The attachment meta of a profile's portrait or logo (REQ-162, REQ-203, D-024, contract 1.1 section 10): the licence, licence
 * link, creator, attribution text and source page that the credit line shows, the kind (photo or logo), and, for imported
 * images, the file hash and the import batch that created it (so an undo can remove it). One list of keys for the importer, the
 * Add profile screen and the PublicProfile read model.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

/**
 * Image meta keys.
 */
final class ImageMeta {

	public const LICENCE     = '_wiw_image_licence';
	public const LICENCE_URL = '_wiw_image_licence_url';
	public const CREATOR     = '_wiw_image_creator';
	public const ATTRIBUTION = '_wiw_image_attribution';
	public const SOURCE_URL  = '_wiw_image_source_url';
	public const KIND        = '_wiw_image_kind';
	public const SHA256      = '_wiw_image_sha256';
	public const BATCH       = '_wiw_import_batch';

	/**
	 * On an attachment set as a profile's image by an import: the image it replaced, so an undo can put it back.
	 */
	public const REPLACED = '_wiw_image_replaced';

	/**
	 * Write the credit meta of an attachment.
	 *
	 * @param int                  $attachment_id Attachment.
	 * @param array<string, mixed> $credit        licence, licence_url, creator, attribution_text, source_url, kind.
	 */
	public static function save( int $attachment_id, array $credit ): void {
		$map = array(
			'licence'          => self::LICENCE,
			'licence_url'      => self::LICENCE_URL,
			'creator'          => self::CREATOR,
			'attribution_text' => self::ATTRIBUTION,
			'source_url'       => self::SOURCE_URL,
			'kind'             => self::KIND,
		);
		foreach ( $map as $key => $meta_key ) {
			if ( isset( $credit[ $key ] ) && is_string( $credit[ $key ] ) ) {
				$value = str_ends_with( $key, 'url' ) ? esc_url_raw( $credit[ $key ], array( 'http', 'https' ) ) : sanitize_text_field( $credit[ $key ] );
				update_post_meta( $attachment_id, $meta_key, wp_slash( $value ) );
			}
		}
	}
}
