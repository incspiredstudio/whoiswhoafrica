<?php
/**
 * The private import directory the command line reads bundles from (REQ-237): outside the web root, one folder per bundle, each
 * holding manifest.json and records.jsonl. A bundle is named by its folder name only; a name that could leave the directory is
 * refused, as is a directory that sits inside the web root.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Cli;

/**
 * Private import directory.
 */
final class PrivateImportDir {

	/**
	 * Root, without a trailing slash.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Constructor.
	 *
	 * @param string|null $root The import directory; the default lives beside the web root.
	 */
	public function __construct( ?string $root = null ) {
		$this->root = rtrim( str_replace( '\\', '/', $root ?? self::default_root() ), '/' );
	}

	/**
	 * The default location: WIW_PRIVATE_DIR (or the PRIVATE_UPLOADS_DIR variable) plus /import, else a sibling of the web root.
	 */
	public static function default_root(): string {
		$base = defined( 'WIW_PRIVATE_DIR' ) ? (string) constant( 'WIW_PRIVATE_DIR' ) : (string) getenv( 'PRIVATE_UPLOADS_DIR' );
		if ( '' === $base ) {
			$base = dirname( rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) ) . '/wiw-private';
		}
		return rtrim( str_replace( '\\', '/', $base ), '/' ) . '/import';
	}

	/**
	 * The directory path.
	 */
	public function root(): string {
		return $this->root;
	}

	/**
	 * Folder of a named bundle.
	 *
	 * @param string $name Folder name inside the import directory.
	 * @return string Absolute folder.
	 * @throws \RuntimeException When the name is unsafe, the folder is missing, or the directory is in the web root.
	 */
	public function bundle( string $name ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $name ) || false !== strpos( $name, '..' ) ) {
			throw new \RuntimeException( 'A bundle is named by its folder name inside the private import directory.' );
		}
		$root = realpath( $this->root );
		if ( false === $root ) {
			throw new \RuntimeException( 'The private import directory does not exist.' );
		}
		$root = rtrim( str_replace( '\\', '/', $root ), '/' );
		if ( self::inside_web_root( $root ) ) {
			throw new \RuntimeException( 'The private import directory is inside the web root, so nothing was read.' );
		}
		$dir = realpath( $root . '/' . $name );
		$dir = false === $dir ? '' : str_replace( '\\', '/', $dir );
		if ( '' === $dir || 0 !== strpos( $dir . '/', $root . '/' ) || ! is_dir( $dir ) ) {
			throw new \RuntimeException( 'No such bundle in the private import directory.' );
		}
		return $dir;
	}

	/**
	 * Whether a path lies inside the web root.
	 *
	 * @param string $path Path.
	 */
	public static function inside_web_root( string $path ): bool {
		$web  = rtrim( str_replace( '\\', '/', (string) realpath( ABSPATH ) ), '/' ) . '/';
		$real = str_replace( '\\', '/', (string) realpath( $path ) );
		return '' !== $real && 0 === strpos( $real . '/', $web );
	}
}
