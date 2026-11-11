<?php
/**
 * Fixed capabilities, no self-grant, one role per user (REQ-235, REQ-244, REQ-245). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Security;

use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Guard\Capabilities\Capabilities;
use WhoIsWho\Tests\WpTestCase;

final class FixedCapabilitiesTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Start clean: tests that create tables commit the transaction, so role grants can leak between runs.
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
	}

	/**
	 * Roles of a user, read fresh from the database.
	 *
	 * @param int $id User id.
	 * @return string[]
	 */
	private function roles( int $id ): array {
		clean_user_cache( $id );
		return (array) get_userdata( $id )->roles;
	}

	public function test_REQ_235_fixed_capability_cannot_be_granted_to_non_administrator(): void {
		$editor = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$user   = new \WP_User( $editor );
		$user->add_cap( 'manage_options' ); // A stored per-user grant.
		$user->add_cap( 'wiw_manage_register' );
		get_role( 'wiw_editor' )->add_cap( 'install_plugins' ); // A stored role grant.

		$this->assertFalse( user_can( $editor, 'manage_options' ) );
		$this->assertFalse( user_can( $editor, 'wiw_manage_register' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		$this->assertFalse( user_can( $editor, 'install_plugins' ) );

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->assertTrue( user_can( $admin, 'manage_options' ), 'the Administrator keeps them' );
		$this->assertTrue( user_can( $editor, 'edit_posts' ), 'ordinary capabilities are not affected' );
	}

	public function test_REQ_244_user_cannot_grant_self_a_capability(): void {
		$editor = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		wp_set_current_user( $editor );

		( new \WP_User( $editor ) )->add_cap( 'wiw_run_imports' );
		clean_user_cache( $editor );
		$this->assertFalse( user_can( $editor, 'wiw_run_imports' ), 'a self-grant has no effect' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown

		( new \WP_User( $editor ) )->set_role( 'administrator' );
		$this->assertSame( array( 'wiw_editor' ), $this->roles( $editor ), 'a user cannot change their own role' );

		// Someone else can: the Administrator changes the editor's role.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		( new \WP_User( $editor ) )->set_role( 'wiw_reviewer' );
		$this->assertSame( array( 'wiw_reviewer' ), $this->roles( $editor ) );
	}

	public function test_REQ_245_second_role_is_refused(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user  = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		wp_set_current_user( $admin );

		( new \WP_User( $user ) )->add_role( 'wiw_reviewer' );
		$this->assertSame( array( 'wiw_editor' ), $this->roles( $user ), 'a second role is refused' );
		$this->assertSame(
			1,
			Capabilities::role_count(
				array(
					'wiw_editor'   => true,
					'wiw_reviewer' => true,
				)
			) - 1
		);
	}

	public function test_REQ_245_email_addresses_are_unique_per_user(): void {
		self::factory()->user->create( array( 'user_email' => 'same@example.org' ) );
		$second = wp_insert_user(
			array(
				'user_login' => 'second-user',
				'user_pass'  => 'Another-Strong-Pass-123',
				'user_email' => 'same@example.org',
			)
		);
		$this->assertInstanceOf( \WP_Error::class, $second, 'a second account with the same email is refused' );
	}

	public function test_REQ_235_guard_fixed_list_matches_catalogue(): void {
		$catalogue = ( new CapabilityCatalogue() )->fixed();
		$guard     = Capabilities::FIXED;
		sort( $catalogue );
		sort( $guard );
		$this->assertSame( $catalogue, $guard, 'the guard list and the plugin catalogue agree' );
	}

	public function test_REQ_235_meta_capability_extension_point_exists(): void {
		add_filter(
			'wiw_guard_map_meta_cap',
			static function ( $caps, $cap ) {
				return 'wiw_probe_cap' === $cap ? array( 'do_not_allow' ) : $caps;
			},
			10,
			2
		);
		$this->assertSame( array( 'do_not_allow' ), Capabilities::map_meta_cap( array( 'read' ), 'wiw_probe_cap', 1, array() ) );
	}
}
