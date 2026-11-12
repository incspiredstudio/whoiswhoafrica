<?php
/**
 * Staff deactivation (REQ-235, REQ-051). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Users;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Mail\StaffAudience;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Users\Deactivation;
use WhoIsWho\Tests\TestCase;

final class DeactivationTest extends TestCase {

	private const PROBE = 'wiw_deactivation_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		add_filter( 'authenticate', array( Deactivation::class, 'block_sign_in' ), 100 );
	}

	protected function tearDown(): void {
		global $wpdb;
		remove_filter( 'authenticate', array( Deactivation::class, 'block_sign_in' ), 100 );
		wp_set_current_user( 0 );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function user( string $role, string $password = 'pw-for-tests-1' ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => $password,
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	private function service( int $actor ): Deactivation {
		wp_set_current_user( 0 );
		wp_set_current_user( $actor );
		return new Deactivation( new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => $actor ) );
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_235_deactivation_ends_sessions_and_is_audited(): void {
		$admin = $this->user( 'administrator' );
		$staff = $this->user( 'wiw_editor' );

		$tokens = \WP_Session_Tokens::get_instance( $staff );
		$tokens->create( time() + 3600 );
		$tokens->create( time() + 7200 );
		$this->assertCount( 2, $tokens->get_all() );

		$this->service( $admin )->deactivate( $staff, 'Left the team' );

		$this->assertCount( 0, \WP_Session_Tokens::get_instance( $staff )->get_all(), 'every session ends at once' );
		$this->assertTrue( Deactivation::is_deactivated( $staff ) );

		$rows = $this->rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'user.deactivate', $rows[0]['action'] );
		$this->assertSame( (string) $staff, $rows[0]['subject_id'] );
		$this->assertSame( (string) $admin, $rows[0]['actor_id'] );
		$this->assertSame( 'Left the team', $rows[0]['reason'] );

		$this->service( $admin )->reactivate( $staff, 'Back from leave' );
		$this->assertFalse( Deactivation::is_deactivated( $staff ) );
		$this->assertSame( array( 'user.deactivate', 'user.reactivate' ), array_column( $this->rows(), 'action' ) );
	}

	public function test_REQ_235_deactivated_user_cannot_sign_in(): void {
		$admin = $this->user( 'administrator' );
		$staff = $this->user( 'wiw_editor', 'correct-horse-9' );
		$login = get_userdata( $staff )->user_login;

		$this->assertInstanceOf( \WP_User::class, wp_authenticate( $login, 'correct-horse-9' ) );

		$this->service( $admin )->deactivate( $staff, 'Left' );
		$result = wp_authenticate( $login, 'correct-horse-9' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringNotContainsString( 'deactivated', strtolower( $result->get_error_message() ), 'the error is the same generic one' );

		$this->service( $admin )->reactivate( $staff, 'Back' );
		$this->assertInstanceOf( \WP_User::class, wp_authenticate( $login, 'correct-horse-9' ) );
	}

	public function test_REQ_235_only_a_user_who_can_manage_users_may_do_it(): void {
		$staff = $this->user( 'wiw_editor' );
		foreach ( array( 'wiw_editor', 'wiw_reviewer' ) as $role ) {
			$service = $this->service( $this->user( $role ) );
			try {
				$service->deactivate( $staff, 'x' );
				$this->fail( $role . ' must not deactivate' );
			} catch ( \DomainException $e ) {
				$this->assertFalse( Deactivation::is_deactivated( $staff ) );
			}
		}
		$this->assertSame( array(), $this->rows(), 'a refused attempt writes no row' );
	}

	public function test_REQ_235_nobody_can_deactivate_themselves(): void {
		$admin = $this->user( 'administrator' );
		$this->expectException( \DomainException::class );
		$this->service( $admin )->deactivate( $admin, 'oops' );
	}

	public function test_REQ_235_deactivated_users_receive_no_staff_notifications(): void {
		$admin = $this->user( 'administrator' );
		$staff = $this->user( 'administrator' );
		$this->assertContains( get_userdata( $staff )->user_email, StaffAudience::addresses( 'administrators' ) );
		$this->service( $admin )->deactivate( $staff, 'Left' );
		$this->assertNotContains( get_userdata( $staff )->user_email, StaffAudience::addresses( 'administrators' ), 'the key set here is the key the audience reads' );
	}

	public function test_REQ_235_the_users_list_shows_a_link_only_to_those_who_can_use_it(): void {
		$admin  = $this->user( 'administrator' );
		$staff  = $this->user( 'wiw_editor' );
		$editor = $this->user( 'wiw_editor' );

		$service = $this->service( $admin );
		$actions = $service->row_actions( array(), get_userdata( $staff ) );
		$this->assertStringContainsString( 'Deactivate', $actions['wiw_deactivation'] );
		$this->assertStringContainsString( '_wpnonce=', $actions['wiw_deactivation'] );
		$this->assertArrayNotHasKey( 'wiw_deactivation', $service->row_actions( array(), get_userdata( $admin ) ), 'no link on your own row' );

		$service->deactivate( $staff, 'Left' );
		$this->assertStringContainsString( 'Reactivate', $service->row_actions( array(), get_userdata( $staff ) )['wiw_deactivation'] );

		$as_editor = $this->service( $editor );
		$this->assertArrayNotHasKey( 'wiw_deactivation', $as_editor->row_actions( array(), get_userdata( $staff ) ) );
	}
}
