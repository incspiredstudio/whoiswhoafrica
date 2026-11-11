<?php
/**
 * Only Review\StateMachine writes a profile's publication state (REQ-210). No other source file may reference StateWriter.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch;

use WhoIsWho\Tests\TestCase;

final class StateWriterTest extends TestCase {

	public function test_REQ_210_only_state_machine_references_the_state_writer(): void {
		$root = dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/src';
		$it   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		$bad  = array();
		foreach ( $it as $file ) {
			$path = str_replace( '\\', '/', (string) $file );
			if ( '.php' !== substr( $path, -4 ) ) {
				continue;
			}
			if ( str_ends_with( $path, '/Directory/State/StateWriter.php' ) || str_ends_with( $path, '/Review/StateMachine.php' ) ) {
				continue;
			}
			// Only code tokens count, so a comment that names the class is fine.
			foreach ( token_get_all( (string) file_get_contents( $path ) ) as $token ) {
				if ( is_array( $token ) && in_array( $token[0], array( T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED ), true ) && 1 === preg_match( '/(^|\\\\)StateWriter$/', $token[1] ) ) {
					$bad[] = $path;
					break;
				}
			}
		}
		$this->assertSame( array(), $bad, 'Only Review\StateMachine may use StateWriter.' );
	}

	public function test_REQ_210_no_source_file_writes_pub_state_outside_the_writer(): void {
		$root = dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/src';
		$it   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		$bad  = array();
		foreach ( $it as $file ) {
			$path = str_replace( '\\', '/', (string) $file );
			// StateMachine only names pub_state in the values it hands to the audit log.
			if ( '.php' !== substr( $path, -4 ) || str_ends_with( $path, '/Directory/State/StateWriter.php' ) || str_ends_with( $path, '/Review/StateMachine.php' ) ) {
				continue;
			}
			$code = (string) file_get_contents( $path );
			if ( 1 === preg_match( '/[\'"]pub_state[\'"]\s*=>/', $code ) ) {
				$bad[] = $path;
			}
		}
		$this->assertSame( array(), $bad, 'pub_state is written only by StateWriter.' );
	}
}
