<?php
/**
 * The test suite must not read any .env* file (REQ-046).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch;

use WhoIsWho\Tests\TestCase;

final class NoEnvFilesTest extends TestCase {

	public function test_REQ_046_no_test_reads_an_env_file(): void {
		$needle = '/\.env(?![.\w]*example)[\'"\s\/]/';
		$files  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__ ), \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() || in_array( $file->getFilename(), array( basename( __FILE__ ), 'ConfigTemplateTest.php' ), true ) ) { // The config test only inspects .env.example (names, no values).
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			$this->assertDoesNotMatchRegularExpression( $needle, $code, $file->getFilename() . ' mentions an .env file' );
		}
	}
}
