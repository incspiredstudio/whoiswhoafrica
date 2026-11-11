<?php
/**
 * Upload rules: an allowlist of image types for the media library, the real type read from the file content, a size limit,
 * server-side re-encoding with metadata stripped, and a scanner hook. Anything else is refused with a plain message.
 * Evidence files (JPG, PNG, PDF) are private and handled in M3; their allowed types are listed here for that issue.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Uploads;

/**
 * Uploads.
 */
final class Uploads {

	/**
	 * Allowed in the media library: extension pattern => MIME type.
	 */
	public const IMAGE_TYPES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'avif'         => 'image/avif',
	);

	/**
	 * Allowed as private evidence files (used by the claims issue in M3).
	 */
	public const EVIDENCE_TYPES = array( 'image/jpeg', 'image/png', 'application/pdf' );

	/**
	 * Largest upload in bytes (10 MB).
	 */
	public const MAX_BYTES = 10485760;

	/**
	 * Attach hooks.
	 */
	public static function init(): void {
		add_filter( 'upload_mimes', array( self::class, 'allowed_types' ), 99 );
		add_filter( 'wp_check_filetype_and_ext', array( self::class, 'check_real_type' ), 99, 3 );
		add_filter( 'wp_handle_upload_prefilter', array( self::class, 'prefilter' ) );
		add_filter( 'wp_handle_upload', array( self::class, 'after_upload' ), 10, 2 );
	}

	/**
	 * Replace the list of allowed types with the allowlist.
	 *
	 * @param array<string, string> $mimes Types core would allow.
	 * @return array<string, string>
	 */
	public static function allowed_types( array $mimes ): array {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- hook signature.
		return self::IMAGE_TYPES;
	}

	/**
	 * The type of a file read from its content, not its name.
	 *
	 * @param string $path Path.
	 */
	public static function real_mime( string $path ): string {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( false === $finfo ) {
			return '';
		}
		$mime = finfo_file( $finfo, $path );
		finfo_close( $finfo );
		return false === $mime ? '' : $mime;
	}

	/**
	 * Refuse a file whose real type is not allowed, whatever its extension says.
	 *
	 * @param array<string, mixed> $data     Result so far: ext, type, proper_filename.
	 * @param string               $file     Path.
	 * @param string               $filename Name.
	 * @return array<string, mixed>
	 */
	public static function check_real_type( $data, $file, $filename ) {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- hook signature.
		$real = self::real_mime( (string) $file );
		if ( ! in_array( $real, self::IMAGE_TYPES, true ) ) {
			return array(
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			);
		}
		return $data;
	}

	/**
	 * Before WordPress stores the upload: type, size and scanner checks. Setting 'error' makes core refuse it.
	 *
	 * @param array<string, mixed> $file One $_FILES entry.
	 * @return array<string, mixed>
	 */
	public static function prefilter( array $file ): array {
		$path = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( $size > self::MAX_BYTES ) {
			$file['error'] = __( 'That file is too large. The limit is 10 MB.', 'whoiswho' );
			return $file;
		}
		if ( '' === $path || ! in_array( self::real_mime( $path ), self::IMAGE_TYPES, true ) ) {
			$file['error'] = __( 'That file type is not allowed. Use a JPG, PNG, WebP or AVIF image.', 'whoiswho' );
			return $file;
		}
		if ( ! self::scanner()->is_clean( $path ) ) {
			$file['error'] = __( 'That file did not pass the safety check.', 'whoiswho' );
		}
		return $file;
	}

	/**
	 * The scanner in use. The default accepts everything that passed the other checks.
	 */
	public static function scanner(): FileScanner {
		$scanner = apply_filters( 'wiw_guard_file_scanner', new NullScanner() );
		return $scanner instanceof FileScanner ? $scanner : new NullScanner();
	}

	/**
	 * After the file is stored: re-encode it so metadata (GPS, camera, embedded data) is gone.
	 *
	 * @param array<string, mixed> $upload Upload result: file, url, type.
	 * @param string               $context upload or sideload.
	 * @return array<string, mixed>
	 */
	public static function after_upload( array $upload, string $context = 'upload' ): array {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- hook signature.
		if ( ! empty( $upload['file'] ) && ! empty( $upload['type'] ) ) {
			self::sanitise_image( (string) $upload['file'], (string) $upload['type'] );
		}
		return $upload;
	}

	/**
	 * Re-encode an image in place, applying its orientation first. Failure leaves the stored file as it is.
	 *
	 * @param string $path Path.
	 * @param string $mime MIME type.
	 * @return bool Whether the file was re-encoded.
	 */
	public static function sanitise_image( string $path, string $mime ): bool {
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ), true ) ) {
			return false;
		}
		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return false;
		}
		$editor->maybe_exif_rotate();
		$saved = $editor->save( $path, $mime );
		return ! is_wp_error( $saved );
	}
}
