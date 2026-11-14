<?php
/**
 * Upload rules: an allowlist of image types for the media library, the real type read from the file content, a size limit,
 * server-side re-encoding with metadata stripped, and a scanner hook. Anything else is refused with a plain message.
 * The two hero-video types (MP4, WebM) are allowed only for people who may change the home page (issue 799), up to 6 MB, checked
 * by content; they are never re-encoded here (the site cannot transcode), so editors upload files already optimised.
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
	 * Allowed in the media library for people holding VIDEO_CAPABILITY: extension pattern => MIME type.
	 */
	public const VIDEO_TYPES = array(
		'mp4'  => 'video/mp4',
		'webm' => 'video/webm',
	);

	/**
	 * The capability that lets a person upload a hero video (the Home page settings capability).
	 */
	public const VIDEO_CAPABILITY = 'wiw_curate_home';

	/**
	 * Largest video in bytes (6 MB, the hero video block limit).
	 */
	public const VIDEO_MAX_BYTES = 6291456;

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
		// Sideloads (media_handle_sideload: invitation photos, profile manager photos, import images) get the same checks (issue 810).
		add_filter( 'wp_handle_sideload_prefilter', array( self::class, 'sideload_prefilter' ) );
		add_filter( 'wp_handle_upload', array( self::class, 'after_upload' ), 10, 2 );
	}

	/**
	 * Replace the list of allowed types with the allowlist.
	 *
	 * @param array<string, string> $mimes Types core would allow.
	 * @return array<string, string>
	 */
	public static function allowed_types( array $mimes ): array {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- hook signature.
		return self::may_upload_video() ? array_merge( self::IMAGE_TYPES, self::VIDEO_TYPES ) : self::IMAGE_TYPES;
	}

	/**
	 * Whether the current person may put hero videos in the media library.
	 */
	public static function may_upload_video(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( self::VIDEO_CAPABILITY );
	}

	/**
	 * Whether a real (content) type is allowed for this person: an image, or a hero video for someone who may upload one.
	 *
	 * @param string $real Type read from the file content.
	 */
	private static function real_type_allowed( string $real ): bool {
		return in_array( $real, self::IMAGE_TYPES, true ) || ( in_array( $real, self::VIDEO_TYPES, true ) && self::may_upload_video() );
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
		if ( ! self::real_type_allowed( $real ) ) {
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
		$package = self::package_upload_kind();
		if ( '' !== $package ) {
			return self::check_package( $file, $package, isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '' );
		}
		return self::check_media( $file );
	}

	/**
	 * Before WordPress stores a sideloaded file (media_handle_sideload): the media checks only. The theme or plugin zip allowance
	 * never applies here, whatever screen the request came from, because core's package upload goes through wp_handle_upload.
	 *
	 * @param array<string, mixed> $file One $_FILES-like entry.
	 * @return array<string, mixed>
	 */
	public static function sideload_prefilter( array $file ): array {
		return self::check_media( $file );
	}

	/**
	 * The media library checks: type read from content, size and scanner.
	 *
	 * @param array<string, mixed> $file One $_FILES-like entry.
	 * @return array<string, mixed>
	 */
	private static function check_media( array $file ): array {
		$path = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		$real = '' === $path ? '' : self::real_mime( $path );
		if ( in_array( $real, self::VIDEO_TYPES, true ) && self::may_upload_video() ) {
			if ( $size > self::VIDEO_MAX_BYTES ) {
				$file['error'] = __( 'That video is too large. The limit is 6 MB; aim for 2 MB (optimise it with site/bin/optimise-hero-video.sh).', 'whoiswho' );
				return $file;
			}
			if ( ! self::scanner()->is_clean( $path ) ) {
				$file['error'] = __( 'That file did not pass the safety check.', 'whoiswho' );
			}
			return $file;
		}
		if ( $size > self::MAX_BYTES ) {
			$file['error'] = __( 'That file is too large. The limit is 10 MB.', 'whoiswho' );
			return $file;
		}
		if ( '' === $path || ! in_array( $real, self::IMAGE_TYPES, true ) ) {
			$file['error'] = __( 'That file type is not allowed. Use a JPG, PNG, WebP or AVIF image.', 'whoiswho' );
			return $file;
		}
		if ( ! self::scanner()->is_clean( $path ) ) {
			$file['error'] = __( 'That file did not pass the safety check.', 'whoiswho' );
		}
		return $file;
	}

	/**
	 * Which core package upload this request is: "theme" on update.php?action=upload-theme, "plugin" on
	 * update.php?action=upload-plugin (core's own upload screens, which reach wp_handle_upload through File_Upload_Upgrader after
	 * checking their nonce), else ''. Read from the globals update.php sets, never from the request directly.
	 */
	public static function package_upload_kind(): string {
		$page   = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		$action = isset( $GLOBALS['action'] ) && is_string( $GLOBALS['action'] ) ? $GLOBALS['action'] : '';
		if ( 'update.php' !== $page ) {
			return '';
		}
		return 'upload-theme' === $action ? 'theme' : ( 'upload-plugin' === $action ? 'plugin' : '' );
	}

	/**
	 * A theme or plugin zip on core's upload screen: allowed only for someone who may install that kind of package, only when the
	 * name ends in .zip and the bytes really are a zip (PK\x03\x04), and only when the scanner passes it. PHP's own upload limit
	 * applies; the media size limit does not, because the bundle's zips are larger than any image.
	 *
	 * @param array<string, mixed> $file    One $_FILES entry.
	 * @param string               $package theme or plugin.
	 * @param string               $path    Temporary path.
	 * @return array<string, mixed>
	 */
	private static function check_package( array $file, string $package, string $path ): array {
		$capability = 'theme' === $package ? 'install_themes' : 'install_plugins';
		if ( ! current_user_can( $capability ) ) {
			$file['error'] = __( 'You are not allowed to install themes or plugins on this site.', 'whoiswho' );
			return $file;
		}
		$name = isset( $file['name'] ) ? strtolower( (string) $file['name'] ) : '';
		if ( '.zip' !== substr( $name, -4 ) || ! self::is_zip( $path ) ) {
			$file['error'] = __( 'That is not a zip file. Upload the theme or plugin .zip from the install bundle.', 'whoiswho' );
			return $file;
		}
		if ( ! self::scanner()->is_clean( $path ) ) {
			$file['error'] = __( 'That file did not pass the safety check.', 'whoiswho' );
		}
		return $file;
	}

	/**
	 * Whether a file starts with the zip local-file signature.
	 *
	 * @param string $path Path.
	 */
	public static function is_zip( string $path ): bool {
		if ( '' === $path || ! is_readable( $path ) ) {
			return false;
		}
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $handle ) {
			return false;
		}
		$head = (string) fread( $handle, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return "PK\x03\x04" === $head;
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
		if ( is_wp_error( $saved ) ) {
			return false;
		}
		// Do not rely on the encoder to drop metadata: remove it from the bytes as well.
		$bytes = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$clean = 'image/jpeg' === $mime ? self::strip_jpeg_metadata( $bytes ) : ( 'image/png' === $mime ? self::strip_png_metadata( $bytes ) : $bytes );
		if ( $clean !== $bytes && '' !== $clean ) {
			file_put_contents( $path, $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return true;
	}

	/**
	 * Remove comment and application segments (EXIF, XMP, ICC extras, thumbnails) from JPEG bytes. APP0 (JFIF) and APP14 (Adobe
	 * colour transform) stay because they affect how the image displays.
	 *
	 * @param string $bytes JPEG bytes.
	 */
	public static function strip_jpeg_metadata( string $bytes ): string {
		$length = strlen( $bytes );
		if ( $length < 4 || "\xFF\xD8" !== substr( $bytes, 0, 2 ) ) {
			return $bytes;
		}
		$out = "\xFF\xD8";
		$pos = 2;
		while ( $pos + 4 <= $length ) {
			if ( "\xFF" !== $bytes[ $pos ] ) {
				return $bytes;
				// Not a well-formed segment stream: leave the file as it is.
			}
			$marker = ord( $bytes[ $pos + 1 ] );
			if ( 0xDA === $marker || 0xD9 === $marker ) {
				// Start of scan or end of image: the rest is image data.
				return $out . substr( $bytes, $pos );
			}
			$size = ( ord( $bytes[ $pos + 2 ] ) << 8 ) + ord( $bytes[ $pos + 3 ] );
			if ( $size < 2 || $pos + 2 + $size > $length ) {
				return $bytes;
			}
			$drop = ( $marker >= 0xE1 && $marker <= 0xED ) || 0xEF === $marker || 0xFE === $marker;
			if ( ! $drop ) {
				$out .= substr( $bytes, $pos, 2 + $size );
			}
			$pos += 2 + $size;
		}
		return $bytes;
	}

	/**
	 * Remove text and EXIF chunks from PNG bytes. Chunks that affect display (palette, transparency, gamma, ICC) stay.
	 *
	 * @param string $bytes PNG bytes.
	 */
	public static function strip_png_metadata( string $bytes ): string {
		$signature = "\x89PNG\r\n\x1a\n";
		if ( strlen( $bytes ) < 12 || substr( $bytes, 0, 8 ) !== $signature ) {
			return $bytes;
		}
		$out    = $signature;
		$pos    = 8;
		$length = strlen( $bytes );
		while ( $pos + 12 <= $length ) {
			$unpacked = unpack( 'N', substr( $bytes, $pos, 4 ) );
			$chunk    = is_array( $unpacked ) ? (int) $unpacked[1] : 0;
			$type     = substr( $bytes, $pos + 4, 4 );
			$total    = 12 + $chunk;
			if ( $pos + $total > $length ) {
				return $bytes;
			}
			if ( ! in_array( $type, array( 'tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME' ), true ) ) {
				$out .= substr( $bytes, $pos, $total );
			}
			$pos += $total;
			if ( 'IEND' === $type ) {
				break;
			}
		}
		return $out;
	}
}
