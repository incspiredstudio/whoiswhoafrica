<?php
/**
 * "Import from the server" (REQ-237, REQ-092, contract 1.1 section 2): a no-code path for bundles larger than the host's upload
 * limit. Staff put the zips in wp-content/uploads/wiw-import/ with the host's file manager (cPanel File Manager, for example);
 * the Import screen lists them and imports the chosen one through exactly the same checks as an upload. The folder is created
 * with an empty index.php and a .htaccess that refuses every web request, so the files cannot be downloaded. A zip is deleted
 * from the folder once it is staged (its contents then live in the private import folder until the import ends).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Admin;

/**
 * Server drop folder.
 */
final class ServerDrop {

	public const FOLDER = 'wiw-import';

	/**
	 * The folder path, or null when it is given in the constructor.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Constructor.
	 *
	 * @param string|null $dir Folder; null uses wp-content/uploads/wiw-import.
	 */
	public function __construct( ?string $dir = null ) {
		if ( null === $dir ) {
			$uploads = wp_upload_dir( null, false );
			$dir     = (string) $uploads['basedir'] . '/' . self::FOLDER;
		}
		$this->dir = rtrim( str_replace( '\\', '/', $dir ), '/' );
	}

	/**
	 * The folder path.
	 */
	public function dir(): string {
		return $this->dir;
	}

	/**
	 * The folder as staff see it, relative to the WordPress folder (for the instructions on screen).
	 */
	public function shown_path(): string {
		$root = rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) . '/';
		return str_starts_with( $this->dir, $root ) ? substr( $this->dir, strlen( $root ) ) : self::FOLDER;
	}

	/**
	 * Create the folder with its guards, when missing.
	 *
	 * @return bool Whether the folder exists and is guarded.
	 */
	public function ensure(): bool {
		if ( ! is_dir( $this->dir ) && ! wp_mkdir_p( $this->dir ) ) {
			return false;
		}
		$guards = array(
			'index.php' => "<?php\n// Silence is golden.\n",
			'.htaccess' => "# Import bundles are never served.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
		);
		foreach ( $guards as $name => $body ) {
			if ( ! is_file( $this->dir . '/' . $name ) ) {
				file_put_contents( $this->dir . '/' . $name, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guard files of our own folder.
			}
		}
		return is_file( $this->dir . '/.htaccess' ) && is_file( $this->dir . '/index.php' );
	}

	/**
	 * The zips in the folder, newest first: name, size in bytes and time.
	 *
	 * @return array<int, array{name: string, size: int, time: int}>
	 */
	public function zips(): array {
		$out = array();
		foreach ( (array) glob( $this->dir . '/*.zip' ) as $path ) {
			if ( ! is_string( $path ) || is_link( $path ) || ! is_file( $path ) || ! self::valid_name( basename( $path ) ) ) {
				continue;
			}
			$out[] = array(
				'name' => basename( $path ),
				'size' => (int) filesize( $path ),
				'time' => (int) filemtime( $path ),
			);
		}
		usort( $out, static fn ( array $a, array $b ): int => $b['time'] <=> $a['time'] );
		return $out;
	}

	/**
	 * The chosen zip as an upload array for the bundle store.
	 *
	 * @param string $name File name in the folder.
	 * @return array{name: string, tmp_name: string, size: int, error: int}
	 * @throws \RuntimeException With a message fit to show.
	 */
	public function take( string $name ): array {
		$path = $this->dir . '/' . $name;
		$real = realpath( $path );
		if ( ! self::valid_name( $name ) || false === $real || is_link( $path ) || ! is_file( $real ) || dirname( str_replace( '\\', '/', $real ) ) !== str_replace( '\\', '/', (string) realpath( $this->dir ) ) ) {
			throw new \RuntimeException( 'That file is not in the import folder.' );
		}
		return array(
			'name'     => $name,
			'tmp_name' => $real,
			'size'     => (int) filesize( $real ),
			'error'    => UPLOAD_ERR_OK,
		);
	}

	/**
	 * Delete a zip from the folder after it was staged.
	 *
	 * @param string $name File name.
	 */
	public function remove( string $name ): void {
		$path = $this->dir . '/' . $name;
		if ( self::valid_name( $name ) && is_file( $path ) && ! is_link( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Whether a file name is a plain zip name (no folder part).
	 *
	 * @param string $name Name.
	 */
	public static function valid_name( string $name ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,180}\.zip$/', $name );
	}
}
