<?php
/**
 * Where uploaded import bundles are kept (REQ-092, REQ-237, contract 1.1 section 2): a private directory outside the web root.
 * An upload is a zip of the bundle folder: manifest.json, records.jsonl and, from contract 1.1, images/<sha256>.<ext>. The files
 * may sit at the top of the zip or inside one top folder. Only those names are read out of the archive, one by one, into a new
 * folder with a random name (nothing is extracted by a name the archive chooses). The whole zip is refused when an entry has an
 * absolute path, a ".." part, a backslash or is a symbolic link, or when the unpacked size passes the cap. Other harmless
 * entries (a folder, a system file) are ignored. The folder is deleted when its import is finished.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Admin;

use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\Images\ImagePolicy;

/**
 * Bundle store.
 */
final class BundleStore {

	/**
	 * Largest zip accepted (the PHP upload limit of the host may be lower; the Import screen then uploads in pieces).
	 */
	public const MAX_BYTES = 536870912;

	/**
	 * Largest total unpacked size.
	 */
	public const MAX_UNPACKED = 1073741824;

	public const ENTRIES = array( 'manifest.json', 'records.jsonl' );

	public const IMAGES_DIR = 'images';

	/**
	 * Root folder.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Constructor.
	 *
	 * @param string|null $root Root folder; null uses the configured private folder.
	 */
	public function __construct( ?string $root = null ) {
		$this->root = rtrim( str_replace( '\\', '/', $root ?? self::default_root() ), '/' );
	}

	/**
	 * The configured private folder: the PRIVATE_UPLOADS_DIR setting, else a folder beside the web root, plus /import.
	 */
	public static function default_root(): string {
		$base = defined( 'WIW_PRIVATE_DIR' ) ? (string) constant( 'WIW_PRIVATE_DIR' ) : (string) getenv( 'PRIVATE_UPLOADS_DIR' );
		if ( '' === $base ) {
			$base = dirname( rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) ) . '/wiw-private';
		}
		return rtrim( str_replace( '\\', '/', $base ), '/' ) . '/import';
	}

	/**
	 * The root folder.
	 */
	public function root(): string {
		return $this->root;
	}

	/**
	 * Whether a path is inside the web root.
	 *
	 * @param string $path Path.
	 */
	public static function inside_web_root( string $path ): bool {
		$web  = rtrim( (string) str_replace( '\\', '/', (string) realpath( ABSPATH ) ), '/' ) . '/';
		$real = (string) str_replace( '\\', '/', (string) realpath( $path ) );
		return '' !== $real && str_starts_with( $real . '/', $web );
	}

	/**
	 * Make sure the root exists and is outside the web root.
	 *
	 * @throws \RuntimeException With a message fit to show.
	 */
	public function ensure_root(): void {
		if ( ! is_dir( $this->root ) && ! wp_mkdir_p( $this->root ) ) {
			throw new \RuntimeException( 'The private import folder cannot be created.' );
		}
		if ( self::inside_web_root( $this->root ) ) {
			throw new \RuntimeException( 'The private import folder is inside the web root, so nothing was stored.' );
		}
	}

	/**
	 * Store an uploaded zip and open the bundle it holds.
	 *
	 * @param array{name: string, tmp_name: string, size: int, error: int}|null $file The upload.
	 * @return array{dir: string, bundle: BundleReader, name: string}
	 * @throws \RuntimeException With a message fit to show, for a bad upload.
	 * @throws \Throwable Any failure while reading the zip, after the folder is removed.
	 */
	public function receive( ?array $file ): array {
		if ( null === $file || UPLOAD_ERR_NO_FILE === $file['error'] ) {
			throw new \RuntimeException( 'Choose a bundle file to upload.' );
		}
		if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] > self::MAX_BYTES ) {
			throw new \RuntimeException( 'The file is too large or the upload failed. The limit is 512 MB.' );
		}
		if ( ! str_ends_with( strtolower( $file['name'] ), '.zip' ) || ! class_exists( \ZipArchive::class ) ) {
			throw new \RuntimeException( 'Upload the bundle as a .zip file holding manifest.json and records.jsonl.' );
		}
		$this->ensure_root();
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file['tmp_name'] ) ) {
			throw new \RuntimeException( 'That file is not a readable zip.' );
		}
		$plan = self::plan( $zip );
		$dir  = $this->root . '/' . bin2hex( random_bytes( 12 ) );
		mkdir( $dir, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- a private folder outside the web root.
		try {
			foreach ( $plan as $target => $index ) {
				if ( str_starts_with( $target, self::IMAGES_DIR . '/' ) && ! is_dir( $dir . '/' . self::IMAGES_DIR ) ) {
					mkdir( $dir . '/' . self::IMAGES_DIR, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- as above.
				}
				self::copy_entry( $zip, $index, $dir . '/' . $target );
			}
			$zip->close();
			return array(
				'dir'    => $dir,
				'bundle' => BundleReader::open( $dir ),
				'name'   => $file['name'],
			);
		} catch ( \Throwable $e ) {
			$zip->close();
			$this->discard( $dir );
			throw $e;
		}
	}

	/**
	 * Check every entry of the archive and decide which ones to read: target path in the bundle folder => entry index.
	 *
	 * @param \ZipArchive $zip Open archive.
	 * @return array<string, int>
	 * @throws \RuntimeException When the archive must be refused.
	 */
	public static function plan( \ZipArchive $zip ): array {
		$names = array();
		$total = 0;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive property.
			$stat = $zip->statIndex( $i );
			if ( false === $stat ) {
				throw new \RuntimeException( 'The zip cannot be read.' );
			}
			$name = (string) $stat['name'];
			if ( '' === $name || str_contains( $name, '\\' ) || str_starts_with( $name, '/' ) || 1 === preg_match( '#^[A-Za-z]:#', $name ) || in_array( '..', explode( '/', $name ), true ) || str_contains( $name, "\0" ) ) {
				throw new \RuntimeException( 'The zip holds a file with an unsafe path, so nothing was stored.' );
			}
			if ( self::is_symlink( $zip, $i ) ) {
				throw new \RuntimeException( 'The zip holds a link, so nothing was stored.' );
			}
			$total += (int) $stat['size'];
			if ( $total > self::MAX_UNPACKED ) {
				throw new \RuntimeException( 'The bundle is too large when unpacked. Split it into smaller bundles.' );
			}
			$names[ $name ] = $i;
		}
		$prefix = self::prefix( array_keys( $names ) );
		$plan   = array();
		foreach ( $names as $name => $index ) {
			if ( '' !== $prefix && ! str_starts_with( $name, $prefix ) ) {
				continue;
			}
			$inner = substr( $name, strlen( $prefix ) );
			if ( in_array( $inner, self::ENTRIES, true ) ) {
				$plan[ $inner ] = $index;
			} elseif ( 1 === preg_match( ImagePolicy::FILE_PATTERN, $inner ) ) {
				$stat = $zip->statIndex( $index );
				// A file over the image limit is not unpacked; the record still imports and that image is refused.
				if ( false !== $stat && (int) $stat['size'] <= ImagePolicy::MAX_BYTES ) {
					$plan[ $inner ] = $index;
				}
			}
		}
		foreach ( self::ENTRIES as $entry ) {
			if ( ! isset( $plan[ $entry ] ) ) {
				throw new \RuntimeException( 'The zip must hold manifest.json and records.jsonl.' );
			}
		}
		return $plan;
	}

	/**
	 * The folder the bundle sits in inside the zip: "" when manifest.json is at the top, or "<folder>/" for one top folder.
	 *
	 * @param string[] $names Entry names.
	 * @throws \RuntimeException When manifest.json is not at the top or in exactly one top folder.
	 */
	private static function prefix( array $names ): string {
		if ( in_array( 'manifest.json', $names, true ) ) {
			return '';
		}
		$found = array();
		foreach ( $names as $name ) {
			if ( 1 === preg_match( '#^([^/]+)/manifest\.json$#', $name, $m ) ) {
				$found[] = $m[1] . '/';
			}
		}
		if ( 1 !== count( $found ) ) {
			throw new \RuntimeException( 'The zip must hold manifest.json and records.jsonl.' );
		}
		return $found[0];
	}

	/**
	 * Whether an entry is a symbolic link (Unix mode bits in the external attributes).
	 *
	 * @param \ZipArchive $zip   Archive.
	 * @param int         $index Entry.
	 */
	private static function is_symlink( \ZipArchive $zip, int $index ): bool {
		$opsys = 0;
		$attr  = 0;
		if ( ! $zip->getExternalAttributesIndex( $index, $opsys, $attr ) ) {
			return false;
		}
		return \ZipArchive::OPSYS_UNIX === $opsys && 0120000 === ( ( $attr >> 16 ) & 0170000 );
	}

	/**
	 * Copy one entry to a file by streaming, checking the bytes written against the entry size.
	 *
	 * @param \ZipArchive $zip    Archive.
	 * @param int         $index  Entry.
	 * @param string      $target Target file.
	 * @throws \RuntimeException When the entry cannot be read.
	 */
	private static function copy_entry( \ZipArchive $zip, int $index, string $target ): void {
		$in = $zip->getStreamIndex( $index );
		if ( false === $in ) {
			throw new \RuntimeException( 'The zip cannot be read.' );
		}
		$out = fopen( $target, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- a private folder outside the web root.
		if ( false === $out ) {
			fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- as above.
			throw new \RuntimeException( 'The private import folder cannot be written.' );
		}
		$stat = $zip->statIndex( $index );
		try {
			// Never write more than the entry declares (plus one byte, to notice an entry that holds more): a zip bomb that lies in
			// its header stops here instead of filling the disk (issue 810).
			$written = false === $stat ? false : stream_copy_to_stream( $in, $out, self::copy_limit( (int) $stat['size'] ) );
		} finally {
			fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- as above.
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- as above.
		}
		if ( false === $written || false === $stat || $written !== (int) $stat['size'] ) {
			throw new \RuntimeException( 'The zip cannot be read.' );
		}
	}

	/**
	 * The most bytes copied out of one entry: its declared size plus one, and never past the unpacked cap.
	 *
	 * @param int $declared Size the entry's header declares.
	 */
	public static function copy_limit( int $declared ): int {
		return min( max( 0, $declared ) + 1, self::MAX_UNPACKED );
	}

	/**
	 * Add one piece of a zip uploaded in pieces (the Import screen sends pieces small enough for any host's PHP upload limit).
	 * The pieces are joined in a private file named by a random upload id; a piece is accepted only at the exact offset the file
	 * has reached, so a repeated or lost piece is noticed and the browser can resend from the right place.
	 *
	 * @param string $upload_id Upload id from the first piece ("" to start a new upload).
	 * @param int    $offset    Where this piece starts.
	 * @param int    $total     Size of the whole zip.
	 * @param string $piece     Path of the uploaded piece.
	 * @return array{upload_id: string, received: int, path: string}
	 * @throws \RuntimeException With a message fit to show.
	 */
	public function append_piece( string $upload_id, int $offset, int $total, string $piece ): array {
		if ( $total < 1 || $total > self::MAX_BYTES ) {
			throw new \RuntimeException( 'The file is too large. The limit is 512 MB.' );
		}
		$this->ensure_root();
		$folder = $this->root . '/uploads';
		if ( ! is_dir( $folder ) && ! wp_mkdir_p( $folder ) ) {
			throw new \RuntimeException( 'The private import folder cannot be created.' );
		}
		if ( '' === $upload_id ) {
			$this->clear_old_pieces( $folder );
			if ( 0 !== $offset ) {
				throw new \RuntimeException( 'The upload must start at the beginning of the file.' );
			}
			$upload_id = bin2hex( random_bytes( 16 ) );
		}
		if ( 1 !== preg_match( '/^[0-9a-f]{32}$/', $upload_id ) ) {
			throw new \RuntimeException( 'Unknown upload.' );
		}
		$path = $folder . '/' . $upload_id . '.part';
		$have = is_file( $path ) ? (int) filesize( $path ) : 0;
		if ( $have !== $offset ) {
			throw new \RuntimeException( 'A piece of the upload went missing. Start the upload again.' );
		}
		$size = is_file( $piece ) ? (int) filesize( $piece ) : 0;
		if ( $size < 1 || $offset + $size > $total ) {
			throw new \RuntimeException( 'The upload failed. Try again.' );
		}
		$in  = fopen( $piece, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- the uploaded piece.
		$out = fopen( $path, 'ab' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- a private folder outside the web root.
		if ( false === $in || false === $out ) {
			throw new \RuntimeException( 'The private import folder cannot be written.' );
		}
		stream_copy_to_stream( $in, $out );
		fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- as above.
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- as above.
		clearstatcache( true, $path );
		return array(
			'upload_id' => $upload_id,
			'received'  => (int) filesize( $path ),
			'path'      => $path,
		);
	}

	/**
	 * Delete the joined file of a finished or abandoned upload.
	 *
	 * @param string $upload_id Upload id.
	 */
	public function drop_upload( string $upload_id ): void {
		$path = $this->root . '/uploads/' . $upload_id . '.part';
		if ( 1 === preg_match( '/^[0-9a-f]{32}$/', $upload_id ) && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Delete pieces of uploads abandoned more than a day ago.
	 *
	 * @param string $folder Uploads folder.
	 */
	private function clear_old_pieces( string $folder ): void {
		foreach ( (array) glob( $folder . '/*.part' ) as $file ) {
			if ( is_string( $file ) && is_file( $file ) && (int) filemtime( $file ) < time() - DAY_IN_SECONDS ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Remove a stored bundle folder and its images (only inside the root).
	 *
	 * @param string $dir Folder.
	 */
	public function discard( string $dir ): void {
		$dir = str_replace( '\\', '/', $dir );
		if ( 0 !== strpos( $dir, $this->root . '/' ) || str_contains( $dir, '..' ) || ! is_dir( $dir ) ) {
			return;
		}
		$images = $dir . '/' . self::IMAGES_DIR;
		if ( is_dir( $images ) && ! is_link( $images ) ) {
			foreach ( (array) glob( $images . '/*' ) as $file ) {
				if ( is_string( $file ) && is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
			rmdir( $images ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- our own private folder.
		}
		foreach ( self::ENTRIES as $entry ) {
			if ( is_file( $dir . '/' . $entry ) ) {
				wp_delete_file( $dir . '/' . $entry );
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- our own private folder.
	}
}
