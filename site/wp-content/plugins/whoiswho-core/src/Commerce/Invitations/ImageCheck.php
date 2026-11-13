<?php
/**
 * The checks on a picture sent through an invitation (REQ-013): a real JPEG, PNG or WebP image no larger than the limit, judged by
 * its content and not by its file name. A file over the limit, of another type, with a wrong extension, or that is not an image at
 * all is refused before anything is stored. Pure PHP, no WordPress calls.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce\Invitations;

/**
 * Image check.
 */
final class ImageCheck {

	public const MAX_BYTES = 2097152;

	/**
	 * Allowed content types and their extensions.
	 */
	public const TYPES = array(
		'image/jpeg' => array( 'jpg', 'jpeg' ),
		'image/png'  => array( 'png' ),
		'image/webp' => array( 'webp' ),
	);

	/**
	 * Check one uploaded file.
	 *
	 * @param array{name: string, tmp_name: string, size: int, error: int} $file As PHP reports it (see Request::file()).
	 * @param int                                                          $max  Most bytes.
	 * @return string|null An error code (too_big, wrong_type, upload_failed), or null when the file passes.
	 */
	public static function check( array $file, int $max = self::MAX_BYTES ): ?string {
		if ( UPLOAD_ERR_OK !== $file['error'] || '' === $file['tmp_name'] || ! is_file( $file['tmp_name'] ) ) {
			return UPLOAD_ERR_INI_SIZE === $file['error'] || UPLOAD_ERR_FORM_SIZE === $file['error'] ? 'too_big' : 'upload_failed';
		}
		$size = filesize( $file['tmp_name'] );
		if ( false === $size || $file['size'] > $max || $size > $max ) {
			return 'too_big';
		}
		$info = getimagesize( $file['tmp_name'] );
		$mime = is_array( $info ) ? $info['mime'] : '';
		if ( ! isset( self::TYPES[ $mime ] ) ) {
			return 'wrong_type';
		}
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		return in_array( $extension, self::TYPES[ $mime ], true ) ? null : 'wrong_type';
	}
}
