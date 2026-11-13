<?php
/**
 * Where the plugin finds the third-party plugin code it loads: beside it (a composer or managed install) or in its own bundled
 * folder (a zip installed on a plain WordPress or WordPress Studio) (REQ-046, REQ-056).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhoIsWho\Core\Platform\ThirdParty;

require_once dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/src/Platform/ThirdParty.php';

final class ThirdPartyLocationTest extends TestCase {

	private string $root = '';

	protected function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/wiw-third-' . uniqid();
		mkdir( $this->root . '/plugins/whoiswho-core/bundled/two-factor', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	protected function tearDown(): void {
		foreach ( array( '/plugins/whoiswho-core/bundled/two-factor', '/plugins/whoiswho-core/bundled', '/plugins/whoiswho-core', '/plugins/two-factor', '/plugins' ) as $dir ) {
			if ( is_dir( $this->root . $dir ) ) {
				rmdir( $this->root . $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		rmdir( $this->root ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		parent::tearDown();
	}

	public function test_REQ_046_uses_the_bundled_folder_when_nothing_sits_beside_the_plugin(): void {
		$core = $this->root . '/plugins/whoiswho-core/';
		$this->assertSame( rtrim( $core, '/' ) . '/bundled', ThirdParty::dir_for( 'two-factor', $core ) );
	}

	public function test_REQ_046_prefers_a_copy_beside_the_plugin(): void {
		mkdir( $this->root . '/plugins/two-factor' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$core = $this->root . '/plugins/whoiswho-core/';
		$this->assertSame( $this->root . '/plugins', ThirdParty::dir_for( 'two-factor', $core ) );
	}

	public function test_REQ_056_falls_back_to_the_sibling_folder_when_there_is_no_bundle(): void {
		$core = $this->root . '/plugins/whoiswho-core/';
		$this->assertSame( $this->root . '/plugins', ThirdParty::dir_for( 'user-role-editor', $core ) );
	}
}
