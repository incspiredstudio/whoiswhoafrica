<?php
/**
 * Where uploaded import bundles are kept (REQ-092, REQ-237): a private directory outside the web root. An upload is a zip with
 * exactly two entries, manifest.json and records.jsonl; only those two are read out of it (nothing is extracted by name from the
 * archive), into a new folder with a random name. Encryption at rest and the retention job belong to M3 privacy; the folder's
 * creation time and the batch row's created_at record the upload date.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Admin;

use WhoIsWho\Core\Import\BundleReader;

/**
 * Bundle store.
 */
final class BundleStore {

	public const MAX_BYTES = 52428800;

	public const ENTRIES = array( 'manifest.json', 'records.jsonl' );

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
			throw new \RuntimeException( 'The file is too large or the upload failed. The limit is 50 MB.' );
		}
		if ( ! str_ends_with( strtolower( $file['name'] ), '.zip' ) || ! class_exists( \ZipArchive::class ) ) {
			throw new \RuntimeException( 'Upload the bundle as a .zip file holding manifest.json and records.jsonl.' );
		}
		if ( ! is_dir( $this->root ) && ! wp_mkdir_p( $this->root ) ) {
			throw new \RuntimeException( 'The private import folder cannot be created.' );
		}
		if ( self::inside_web_root( $this->root ) ) {
			throw new \RuntimeException( 'The private import folder is inside the web root, so nothing was stored.' );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file['tmp_name'] ) ) {
			throw new \RuntimeException( 'That file is not a readable zip.' );
		}
		$dir = $this->root . '/' . bin2hex( random_bytes( 12 ) );
		mkdir( $dir, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- a private folder outside the web root.
		try {
			foreach ( self::ENTRIES as $entry ) {
				$stat = $zip->statName( $entry );
				if ( false === $stat || $stat['size'] > self::MAX_BYTES ) {
					throw new \RuntimeException( 'The zip must hold manifest.json and records.jsonl.' );
				}
				file_put_contents( $dir . '/' . $entry, (string) $zip->getFromName( $entry ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a private folder outside the web root.
			}
			$zip->close();
			return array(
				'dir'    => $dir,
				'bundle' => BundleReader::open( $dir ),
				'name'   => $file['name'],
			);
		} catch ( \Throwable $e ) {
			$this->discard( $dir );
			throw $e;
		}
	}

	/**
	 * Remove a stored bundle folder (only inside the root).
	 *
	 * @param string $dir Folder.
	 */
	public function discard( string $dir ): void {
		$dir = str_replace( '\\', '/', $dir );
		if ( 0 !== strpos( $dir, $this->root . '/' ) || str_contains( $dir, '..' ) ) {
			return;
		}
		foreach ( self::ENTRIES as $entry ) {
			if ( is_file( $dir . '/' . $entry ) ) {
				wp_delete_file( $dir . '/' . $entry );
			}
		}
		if ( is_dir( $dir ) ) {
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- our own private folder.
		}
	}
}
