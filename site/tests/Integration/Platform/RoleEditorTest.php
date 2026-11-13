<?php
/**
 * The role-editor plugin is limited to the Administrator (REQ-244, REQ-235). Plain TestCase: users and roles are real rows and
 * the audit table is a scratch copy. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityAudit;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\RoleEditor\RoleEditor;
use WhoIsWho\Tests\TestCase;

/**
 * Role editor access.
 */
final class RoleEditorTest extends TestCase {

	private const PROBE = 'wiw_roleeditor_audit_probe';

	/**
	 * Audit listener.
	 *
	 * @var CapabilityAudit|null
	 */
	private ?CapabilityAudit $listener = null;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		if ( null !== $this->listener ) {
			$this->listener->unregister();
			$this->listener = null;
			$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		}
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A user in a role.
	 *
	 * @param string $role Role.
	 */
	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'roleed' . wp_generate_password( 8, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	public function test_REQ_244_the_plugin_is_loaded_by_the_core_plugin(): void {
		$this->assertTrue( class_exists( '\URE_Core', false ) );
		$this->assertSame( 10, has_filter( 'ure_grant_all_caps_to_admin', '__return_false' ) );
	}

	public function test_REQ_244_role_editor_requires_wiw_manage_capabilities(): void {
		$admin = $this->user( 'administrator' );
		wp_set_current_user( $admin );
		$this->assertTrue( user_can( $admin, RoleEditor::GATE ) );
		foreach ( array( 'ure_edit_roles', 'ure_create_roles', 'ure_delete_roles', 'ure_manage_options', 'ure_reset_roles' ) as $cap ) {
			$this->assertTrue( user_can( $admin, $cap ), $cap );
		}

		foreach ( array( 'wiw_editor', 'wiw_reviewer' ) as $role ) {
			$staff = $this->user( $role );
			foreach ( array( 'ure_edit_roles', 'ure_manage_options', 'ure_create_capabilities' ) as $cap ) {
				$this->assertFalse( user_can( $staff, $cap ), "$role must not hold $cap" );
			}
		}
	}

	public function test_REQ_244_a_capability_stored_for_a_role_or_user_cannot_open_the_screen(): void {
		$staff = $this->user( 'wiw_editor' );
		get_role( 'wiw_editor' )->add_cap( 'ure_edit_roles' );
		( new \WP_User( $staff ) )->add_cap( 'ure_manage_options' );
		try {
			$this->assertFalse( user_can( $staff, 'ure_edit_roles' ) );
			$this->assertFalse( user_can( $staff, 'ure_manage_options' ) );
			$this->assertFalse( user_can( $staff, 'ure_create_roles' ), 'a plugin capability that was never stored is also refused' );
		} finally {
			get_role( 'wiw_editor' )->remove_cap( 'ure_edit_roles' );
		}
	}

	public function test_REQ_244_an_administrator_without_the_gate_is_refused(): void {
		$admin = $this->user( 'administrator' );
		$user  = new \WP_User( $admin );
		$user->add_cap( RoleEditor::GATE, false );
		$this->assertFalse( user_can( $admin, 'ure_edit_roles' ) );
	}

	public function test_REQ_235_fixed_capability_cannot_be_granted_to_non_administrator(): void {
		$staff = $this->user( 'wiw_editor' );
		// The way the plugin edits a user: write the capability into the user's stored capabilities.
		( new \WP_User( $staff ) )->add_cap( 'wiw_manage_capabilities' );
		( new \WP_User( $staff ) )->add_cap( 'manage_options' );
		get_role( 'wiw_editor' )->add_cap( 'install_plugins' );
		try {
			foreach ( ( new CapabilityCatalogue() )->fixed() as $cap ) {
				$this->assertFalse( user_can( $staff, $cap ), "$cap has no effect for a non-Administrator" );
			}
			$this->assertFalse( user_can( $staff, 'ure_edit_roles' ), 'granting the gate does not open the role editor either' );
		} finally {
			get_role( 'wiw_editor' )->remove_cap( 'install_plugins' );
		}
	}

	public function test_REQ_235_fixed_capabilities_are_hidden_from_the_plugins_lists(): void {
		$editor = new RoleEditor( new CapabilityCatalogue() );
		$full   = array(
			'manage_options'          => array( 'inner' => 'manage_options' ),
			'wiw_manage_capabilities' => array( 'inner' => 'wiw_manage_capabilities' ),
			'wiw_review_records'      => array( 'inner' => 'wiw_review_records' ),
		);
		$this->assertSame( array( 'wiw_review_records' ), array_keys( (array) $editor->hide_fixed_from_full_list( $full ) ) );
		$this->assertSame( array( 'wiw_review_records' ), array_keys( (array) $editor->hide_fixed_from_groups( $full ) ) );
		$this->assertSame( 'x', $editor->hide_fixed_from_full_list( 'x' ) );
	}

	public function test_REQ_244_self_grant_is_refused(): void {
		$admin = $this->user( 'administrator' );
		wp_set_current_user( $admin );
		( new \WP_User( $admin ) )->add_cap( 'wiw_view_audit_extra' );
		$this->assertFalse( user_can( $admin, 'wiw_view_audit_extra' ), 'a user cannot grant themselves a capability' );
		( new \WP_User( $admin ) )->set_role( 'wiw_editor' );
		$this->assertContains( 'administrator', ( new \WP_User( $admin ) )->roles, 'a user cannot change their own role' );
	}

	public function test_REQ_244_a_grant_made_through_the_role_editor_writes_an_audit_row(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$logger         = new AuditLogger(
			new WpdbConnection( self::PROBE ),
			static fn (): string => '2026-11-11 10:00:00',
			static fn (): int => (int) get_current_user_id()
		);
		$this->listener = new CapabilityAudit( $logger );
		$this->listener->register();

		$admin   = $this->user( 'administrator' );
		$subject = $this->user( 'wiw_reviewer' );
		wp_set_current_user( $admin );
		( new \WP_User( $subject ) )->add_cap( 'wiw_run_imports' );

		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . " WHERE action = 'user.capabilities'", ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertNotEmpty( $rows );
		$last = end( $rows );
		$this->assertSame( (string) $admin, $last['actor_id'] );
		$this->assertSame( (string) $subject, $last['subject_id'] );
	}
}
