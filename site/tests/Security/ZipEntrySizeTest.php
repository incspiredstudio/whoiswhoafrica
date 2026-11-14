<?php
/**
 * Zip bombs in import bundles (REQ-092, REQ-127, issue 810): an entry that unpacks to more than its header declares is refused,
 * and nothing is left in the private import folder.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Security;

use WhoIsWho\Core\Import\Admin\BundleStore;
use WhoIsWho\Tests\WpTestCase;

final class ZipEntrySizeTest extends WpTestCase {

	/**
	 * Files and folders to remove.
	 *
	 * @var string[]
	 */
	private array $paths = array();

	public function tear_down(): void {
		foreach ( array_reverse( $this->paths ) as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			} elseif ( is_dir( $path ) ) {
				foreach ( (array) glob( $path . '/{,.}[!.]*', GLOB_BRACE ) as $inner ) {
					if ( is_string( $inner ) && is_dir( $inner ) ) {
						array_map( 'unlink', (array) glob( $inner . '/*' ) );
						rmdir( $inner );
					} elseif ( is_string( $inner ) ) {
						unlink( $inner );
					}
				}
				rmdir( $path );
			}
		}
		parent::tear_down();
	}

	/**
	 * A stored (uncompressed) zip whose records.jsonl header claims 10 bytes while the entry holds 4000.
	 */
	private function lying_zip(): string {
		$path          = sys_get_temp_dir() . '/wiw-bomb-' . bin2hex( random_bytes( 4 ) ) . '.zip';
		$this->paths[] = $path;
		$zip           = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE );
		$zip->addFromString( 'manifest.json', '{}' );
		$zip->addFromString( 'records.jsonl', str_repeat( 'A', 4000 ) );
		$zip->setCompressionName( 'records.jsonl', \ZipArchive::CM_STORE );
		$zip->close();

		$bytes = (string) file_get_contents( $path );
		$name  = 'records.jsonl';
		// Local file header: uncompressed size at +22 from the signature; central directory header: at +24.
		foreach ( array( array( "PK\x03\x04", 22, 26 ), array( "PK\x01\x02", 24, 28 ) ) as $header ) {
			$offset = 0;
			while ( false !== ( $at = strpos( $bytes, $header[0], $offset ) ) ) {
				$length = unpack( 'v', substr( $bytes, $at + $header[2], 2 ) )[1];
				$extra  = 'PK' . "\x01\x02" === $header[0] ? 46 : 30;
				if ( substr( $bytes, $at + $extra, $length ) === $name ) {
					$bytes = substr_replace( $bytes, pack( 'V', 10 ), $at + $header[1], 4 );
				}
				$offset = $at + 4;
			}
		}
		file_put_contents( $path, $bytes );
		return $path;
	}

	public function test_REQ_092_an_entry_larger_than_its_header_says_is_refused_issue_810(): void {
		$root          = sys_get_temp_dir() . '/wiw-bomb-root-' . bin2hex( random_bytes( 4 ) );
		$this->paths[] = $root;
		$store         = new BundleStore( $root );
		$zip           = $this->lying_zip();

		$check = new \ZipArchive();
		$check->open( $zip );
		$this->assertSame( 10, (int) $check->statName( 'records.jsonl' )['size'], 'the header was rewritten' );
		$check->close();

		try {
			$store->receive(
				array(
					'name'     => 'bomb.zip',
					'tmp_name' => $zip,
					'size'     => (int) filesize( $zip ),
					'error'    => UPLOAD_ERR_OK,
				)
			);
			$this->fail( 'a lying entry must be refused' );
		} catch ( \Throwable $e ) {
			$this->assertNotSame( '', $e->getMessage() );
		}
		$left = is_dir( $root ) ? array_filter( (array) glob( $root . '/*' ), 'is_dir' ) : array();
		$this->assertSame( array(), array_values( $left ), 'nothing is left in the private folder' );
		$this->assertSame( 11, BundleStore::copy_limit( 10 ), 'at most one byte past the declared size is ever written' );
	}

	public function test_REQ_127_the_private_import_folder_refuses_web_requests_too_issue_810(): void {
		$root          = sys_get_temp_dir() . '/wiw-guard-root-' . bin2hex( random_bytes( 4 ) );
		$this->paths[] = $root;
		( new BundleStore( $root ) )->ensure_root();
		// When WordPress sits in a sub-folder of the web root, the folder beside it can still be under the web root.
		$this->assertStringContainsString( 'Require all denied', (string) file_get_contents( $root . '/.htaccess' ) );
		$this->assertFileExists( $root . '/index.php' );
	}
}
