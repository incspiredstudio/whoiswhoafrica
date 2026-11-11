<?php
/**
 * The guard must not depend on or call whoiswho-core (REQ-208).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Tests\TestCase;

final class GuardIndependenceTest extends TestCase {

	public function test_REQ_208_guard_loads_without_core_plugin(): void {
		$root  = dirname( __DIR__, 2 ) . '/wp-content/mu-plugins';
		$files = array( $root . '/whoiswho-guard.php', $root . '/whoiswho-guard/Guard.php' );
		foreach ( $files as $file ) {
			$code = (string) file_get_contents( $file );
			$this->assertStringNotContainsString( 'WhoIsWho\Core', $code, basename( $file ) . ' references core' );
			$this->assertStringNotContainsString( 'whoiswho-core', $code, basename( $file ) . ' references core' );
		}
	}

	public function test_REQ_208_guard_owns_no_data(): void {
		$code = (string) file_get_contents( dirname( __DIR__, 2 ) . '/wp-content/mu-plugins/whoiswho-guard/Guard.php' );
		foreach ( array( 'add_option', 'update_option', '$wpdb', 'add_menu_page', 'add_submenu_page' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $code );
		}
	}
}
