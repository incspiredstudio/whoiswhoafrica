<?php
/**
 * The security and authentication suite for the foundations (M0-QAX-02; REQ-046, REQ-047, REQ-229, REQ-235). Independent of the
 * tests that sit beside the code: it asks the running WordPress the questions the acceptance criteria ask. Needs the WordPress
 * test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Security;

use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\TwoFactor\PluginStatus;
use WhoIsWho\Core\Platform\TwoFactor\StaffStatus;
use WhoIsWho\Guard\Headers\Headers;
use WhoIsWho\Tests\WpTestCase;

final class FoundationsSecuritySuiteTest extends WpTestCase {

	private const AGREED_HEADERS = array(
		'X-Content-Type-Options',
		'Referrer-Policy',
		'Permissions-Policy',
		'X-Frame-Options',
	);

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( 0 );
	}

	public function test_REQ_046_security_headers_on_every_response(): void {
		// Public and admin, over HTTPS and plain HTTP: the agreed headers are always present and never empty.
		foreach ( array( array( true, false ), array( false, false ), array( true, true ), array( false, true ) ) as $case ) {
			$headers = Headers::headers( $case[0], $case[1], 'nonce123' );
			$label   = ( $case[0] ? 'https' : 'http' ) . ( $case[1] ? ' admin' : ' public' );
			foreach ( self::AGREED_HEADERS as $name ) {
				$this->assertArrayHasKey( $name, $headers, "$label lacks $name" );
				$this->assertNotSame( '', trim( (string) $headers[ $name ] ), "$label has an empty $name" );
			}
			$this->assertTrue(
				isset( $headers['Content-Security-Policy'] ) || isset( $headers['Content-Security-Policy-Report-Only'] ),
				"$label lacks a Content-Security-Policy"
			);
			$this->assertSame( $case[0], isset( $headers['Strict-Transport-Security'] ), "$label: HSTS only over HTTPS" );
		}
		$this->assertSame( 'nosniff', Headers::headers( true, false, 'n' )['X-Content-Type-Options'] );
		$this->assertSame( 'DENY', Headers::headers( true, false, 'n' )['X-Frame-Options'] );
	}

	public function test_REQ_229_no_registration_or_login_route_for_visitors(): void {
		$this->assertSame( 0, (int) get_option( 'users_can_register' ), 'registration is closed' );
		$this->assertSame( '', (string) get_option( 'default_role' ), 'there is no default role' );
		$this->assertSame( '', apply_filters( 'register', '<a href="/wp-login.php?action=register">Register</a>' ), 'no register link' );
		$this->assertSame( '', apply_filters( 'loginout', '<a href="/wp-login.php">Log in</a>' ), 'no sign-in link for visitors' );
		$this->assertFalse( wp_is_application_passwords_available(), 'application passwords are off' );
		$this->assertFalse( apply_filters( 'xmlrpc_enabled', true ), 'XML-RPC is off' );

		$user = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$this->assertFalse( wp_is_application_passwords_available_for_user( $user ), 'not even for staff' );
	}

	public function test_REQ_047_two_factor_available_for_every_role_and_never_blocking(): void {
		$catalogue = new CapabilityCatalogue();
		$status    = new PluginStatus();
		$this->assertTrue( $status->available(), 'the two-factor software is loaded for every role' );
		$this->assertFalse( class_exists( '\WhoIsWho\Core\Platform\TwoFactor\Enforcement' ), 'nothing enforces it (D-070)' );
		foreach ( array_merge( array_keys( $catalogue->roles() ), array( 'administrator' ) ) as $role ) {
			$id = self::factory()->user->create( array( 'role' => $role ) );
			$this->assertContains( $role, StaffStatus::staff_roles(), "$role is listed on the Setup screen's two-factor advice" );
			$this->assertFalse( $status->is_enrolled( $id ), "$role: optional, so a new account starts without it" );
		}
	}

	public function test_REQ_235_role_capability_matrix_matches_defaults(): void {
		$catalogue = new CapabilityCatalogue();
		$bundles   = array(
			'wiw_reviewer'  => $catalogue->for_bundle( 'reviewer' ),
			'wiw_editor'    => $catalogue->for_bundle( 'editor' ),
			'administrator' => $catalogue->for_bundle( 'administrator' ),
		);
		$plugin    = $catalogue->plugin_capabilities();
		foreach ( $bundles as $role => $expected ) {
			$id = self::factory()->user->create( array( 'role' => $role ) );
			foreach ( $plugin as $cap ) {
				$this->assertSame( in_array( $cap, $expected, true ), user_can( $id, $cap ), "$role and $cap" );
			}
		}
		foreach ( $catalogue->fixed() as $cap ) {
			foreach ( array( 'wiw_reviewer', 'wiw_editor' ) as $role ) {
				$id = self::factory()->user->create( array( 'role' => $role ) );
				$this->assertFalse( user_can( $id, $cap ), "$role must not hold the fixed $cap" );
			}
		}
	}

	public function test_REQ_235_a_granted_capability_works_and_a_revoked_one_does_not(): void {
		$reviewer = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		$cap      = 'wiw_run_imports';
		if ( user_can( $reviewer, $cap ) ) {
			$this->markTestSkipped( 'the default reviewer bundle already holds this capability' );
		}
		( new \WP_User( $reviewer ) )->add_cap( $cap );
		$this->assertTrue( user_can( $reviewer, $cap ), 'granted' );
		( new \WP_User( $reviewer ) )->remove_cap( $cap );
		$this->assertFalse( user_can( $reviewer, $cap ), 'revoked' );

		// A fixed capability granted the same way has no effect.
		( new \WP_User( $reviewer ) )->add_cap( 'manage_options' );
		$this->assertFalse( user_can( $reviewer, 'manage_options' ), 'a fixed capability cannot be granted to a non-Administrator' );
	}
}
