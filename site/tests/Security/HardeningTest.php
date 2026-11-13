<?php
/**
 * Guard hardening (REQ-046, REQ-229). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Security;

use WhoIsWho\Guard\Hardening\Hardening;
use WhoIsWho\Tests\TestCase;

final class HardeningTest extends TestCase {

	public function test_registration_closed_and_application_passwords_off(): void {
		$this->requireWordPress();
		update_option( 'users_can_register', 1 );
		$this->assertSame( 0, (int) get_option( 'users_can_register' ), 'registration stays closed even if the database says open' );
		$this->assertFalse( wp_is_application_passwords_available() );
	}

	public function test_REQ_229_default_role_is_empty_even_if_option_changed(): void {
		$this->requireWordPress();
		update_option( 'default_role', 'administrator' );
		$this->assertSame( '', get_option( 'default_role' ) );
	}

	public function test_REQ_046_php_version_header_is_stripped_on_every_response_path(): void {
		$this->requireWordPress();
		foreach ( array( 'send_headers', 'admin_init', 'login_init' ) as $hook ) {
			$this->assertNotFalse( has_action( $hook, array( Hardening::class, 'strip_powered_by' ) ), $hook );
		}
		Hardening::strip_powered_by();
		foreach ( headers_list() as $line ) {
			$this->assertStringNotContainsStringIgnoringCase( 'x-powered-by', $line );
		}
	}

	public function test_REQ_046_xmlrpc_is_off(): void {
		$this->requireWordPress();
		$this->assertFalse( apply_filters( 'xmlrpc_enabled', true ) );
		$this->assertSame(
			array(),
			apply_filters(
				'xmlrpc_methods',
				array(
					'pingback.ping' => 'x',
					'wp.getPosts'   => 'y',
				)
			)
		);
		$this->assertFalse( apply_filters( 'pings_open', true, 1 ) );
		$this->assertArrayNotHasKey( 'X-Pingback', apply_filters( 'wp_headers', array( 'X-Pingback' => 'http://example.org/xmlrpc.php' ) ) );
	}

	public function test_REQ_046_automatic_updates_are_off(): void {
		$this->requireWordPress();
		foreach ( array( 'auto_update_core', 'auto_update_plugin', 'auto_update_theme', 'auto_update_translation', 'allow_minor_auto_core_updates', 'allow_major_auto_core_updates' ) as $filter ) {
			$this->assertFalse( apply_filters( $filter, true ), $filter );
		}
		$this->assertTrue( apply_filters( 'automatic_updater_disabled', false ) );
	}

	public function test_REQ_046_no_sign_in_or_sign_up_link_for_visitors(): void {
		$this->requireWordPress();
		$this->assertSame( '', apply_filters( 'loginout', '<a href="/wp-login.php">Log in</a>' ) );
		$this->assertSame( '', apply_filters( 'register', '<li><a href="/register">Register</a></li>' ) );
	}

	public function test_REQ_046_file_modification_is_disabled_in_production(): void {
		$this->assertSame( array(), Hardening::config_problems( true, true, true ) );
		$this->assertNotEmpty( Hardening::config_problems( true, false, true ), 'production with file mods allowed is flagged' );
		$this->assertNotEmpty( Hardening::config_problems( true, null, true ), 'production with file mods undefined is flagged' );
		$this->assertNotEmpty( Hardening::config_problems( false, null, false ), 'file editing allowed is flagged anywhere' );
		$this->assertSame( array(), Hardening::config_problems( false, null, true ), 'file mods may be on outside production' );
	}
}
