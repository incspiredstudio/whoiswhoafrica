<?php
/**
 * Roles, registration and the capability sync inside WordPress (REQ-235, REQ-229, REQ-244). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\TestCase;

final class RoleSyncTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		delete_option( RoleSync::OPTION_SYNCED );
		delete_option( RoleSync::OPTION_VERSION );
		// Start from a clean slate: DDL in other tests commits the transaction, so roles can leak between runs.
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( ( new CapabilityCatalogue() )->plugin_capabilities() as $cap ) {
				$admin->remove_cap( $cap );
			}
			$admin->remove_cap( 'wiw_future_capability' );
		}
		foreach ( array( 'subscriber', 'contributor', 'author', 'editor' ) as $role ) {
			if ( null === get_role( $role ) ) {
				add_role( $role, ucfirst( $role ), array( 'read' => true ) );
			}
		}
	}

	private function sync(): RoleSync {
		return new RoleSync( new CapabilityCatalogue() );
	}

	public function test_REQ_235_builtin_roles_are_removed_on_activation(): void {
		$this->sync()->activate();
		foreach ( array( 'subscriber', 'contributor', 'author', 'editor' ) as $role ) {
			$this->assertNull( get_role( $role ), "{$role} should be gone" );
		}
		$this->assertNotNull( get_role( 'wiw_reviewer' ) );
		$this->assertNotNull( get_role( 'wiw_editor' ) );
		$this->assertNotNull( get_role( 'administrator' ) );
	}

	public function test_REQ_229_registration_is_closed_and_default_role_is_empty(): void {
		update_option( 'default_role', 'subscriber' );
		update_option( 'users_can_register', 1 );
		$this->sync()->activate();
		$this->assertSame( '', get_option( 'default_role' ) );
		$this->assertSame( 0, (int) get_option( 'users_can_register' ) );
	}

	public function test_REQ_235_role_bundles_in_wordpress_equal_the_catalogue(): void {
		$c = new CapabilityCatalogue();
		$this->sync()->activate();
		foreach ( array(
			'wiw_reviewer' => 'reviewer',
			'wiw_editor'   => 'editor',
		) as $role_name => $bundle ) {
			$role = get_role( $role_name );
			$this->assertNotNull( $role );
			$granted = array_keys( array_filter( $role->capabilities ) );
			$this->assertEqualsCanonicalizing( $c->for_bundle( $bundle ), $granted, "{$role_name} bundle" );
		}
		$admin = get_role( 'administrator' );
		$this->assertNotNull( $admin );
		foreach ( $c->plugin_capabilities() as $cap ) {
			$this->assertTrue( $admin->has_cap( $cap ), "administrator should have {$cap}" );
		}
	}

	public function test_REQ_244_catalogue_sync_keeps_custom_grants(): void {
		$this->sync()->activate();
		$editor = get_role( 'wiw_editor' );
		$this->assertNotNull( $editor );

		// An Administrator grants an extra capability and takes one default away.
		$editor->add_cap( 'wiw_run_imports' );
		$editor->remove_cap( 'wiw_curate_home' );

		// A new capability reaches the roles on the next sync (simulated with a catalogue that has one more).
		$config            = require dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/config/capabilities.php';
		$config['version'] = 2;
		$config['capabilities']['wiw_future_capability'] = array(
			'source' => 'plugin',
			'roles'  => array( 'editor', 'administrator' ),
		);
		( new RoleSync( new CapabilityCatalogue( $config ) ) )->maybe_sync();

		$editor = get_role( 'wiw_editor' );
		$this->assertNotNull( $editor );
		$this->assertTrue( $editor->has_cap( 'wiw_future_capability' ), 'new capability added to the editor bundle' );
		$this->assertTrue( $editor->has_cap( 'wiw_run_imports' ), 'custom grant kept' );
		$this->assertFalse( $editor->has_cap( 'wiw_curate_home' ), 'a deliberate revocation is not re-added' );
		$admin = get_role( 'administrator' );
		$this->assertNotNull( $admin );
		$this->assertTrue( $admin->has_cap( 'wiw_future_capability' ), 'Administrator gets new capabilities' );
	}
}
