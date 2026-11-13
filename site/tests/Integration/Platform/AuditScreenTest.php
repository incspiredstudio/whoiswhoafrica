<?php
/**
 * The Audit log screen, the Verify chain action and the audit:verify command (REQ-051, REQ-188). Needs the WordPress test
 * suite. Plain TestCase with a probe table so the real log is never touched.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\Admin\AuditScreen;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Cli\AuditVerify;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class AuditScreenTest extends TestCase {

	private const PROBE = 'wiw_auditscreen_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function tearDown(): void {
		global $wpdb;
		wp_set_current_user( 0 );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function logger( int $actor = 7, string $at = '2026-11-11 10:00:00' ): AuditLogger {
		return new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => $at, static fn (): int => $actor );
	}

	private function screen(): AuditScreen {
		return new AuditScreen( $this->logger( 1, '2026-11-12 09:00:00' ), self::PROBE );
	}

	private function as_role( string $role ): void {
		wp_set_current_user( 0 );
		wp_set_current_user( self::factory_user( $role ) );
	}

	private static function factory_user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		return (int) $id;
	}

	private function seed(): void {
		$this->logger( 7, '2026-11-01 10:00:00' )->log(
			'profile.unpublish',
			array(
				'type' => 'profile',
				'id'   => 1,
			),
			null,
			null,
			'a'
		);
		$this->logger( 8, '2026-11-05 10:00:00' )->log(
			'import.apply',
			array(
				'type' => 'import_batch',
				'id'   => 'b1',
			)
		);
		$this->logger( 7, '2026-11-09 10:00:00' )->log(
			'profile.restore',
			array(
				'type' => 'profile',
				'id'   => 1,
			)
		);
	}

	/**
	 * Alter one row without fixing its hash.
	 *
	 * @param int $id Row id.
	 */
	private function tamper( int $id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . self::PROBE . ' SET reason = %s WHERE id = %d', 'edited by hand', $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- probe table.
	}

	public function test_REQ_051_audit_screen_requires_wiw_view_audit(): void {
		$this->seed();
		$this->as_role( 'wiw_reviewer' );
		try {
			$this->screen()->html( new Request( array() ) );
			$this->fail( 'a Reviewer must be refused' );
		} catch ( \DomainException $e ) {
			$this->assertStringContainsString( 'audit', $e->getMessage() );
		}
		$this->as_role( 'administrator' );
		$html = $this->screen()->html( new Request( array() ) );
		$this->assertStringContainsString( 'profile.unpublish', $html );
		$this->assertStringNotContainsString( 'type="submit" name="delete"', $html );
		$this->assertStringNotContainsString( 'Delete', $html );
		$this->assertStringNotContainsString( 'Edit', $html, 'the screen offers no edit or delete control' );
	}

	public function test_REQ_051_audit_screen_filters_by_actor_action_and_date(): void {
		$this->seed();
		$this->as_role( 'administrator' );
		$screen = $this->screen();
		$this->assertCount( 3, $screen->rows( array() ) );
		$this->assertCount( 2, $screen->rows( array( 'actor' => 7 ) ) );
		$this->assertCount( 2, $screen->rows( array( 'action' => 'profile.' ) ), 'an action filter matches the start of the action' );
		$dated = $screen->rows(
			array(
				'from' => '2026-11-04',
				'to'   => '2026-11-06',
			)
		);
		$this->assertCount( 1, $dated );
		$this->assertSame( 'import.apply', $dated[0]['action'] );
		$this->assertSame( 'profile.restore', $screen->rows( array() )[0]['action'], 'newest first' );
	}

	public function test_REQ_051_verify_action_reports_broken_row(): void {
		$this->seed();
		$this->as_role( 'administrator' );
		$ok = $this->screen()->verify();
		$this->assertTrue( $ok['intact'] );
		$this->assertStringContainsString( 'intact', $this->screen()->html( new Request( array( 'result' => 'intact' ) ) ) );

		$rows = $this->screen()->rows( array() );
		$this->tamper( (int) $rows[1]['id'] );
		$bad = $this->screen()->verify();
		$this->assertFalse( $bad['intact'] );
		$this->assertSame( (int) $rows[1]['id'], $bad['broken_id'] );
		$html = $this->screen()->html(
			new Request(
				array(
					'result' => 'broken',
					'row'    => (string) $bad['broken_id'],
				)
			)
		);
		$this->assertStringContainsString( 'row ' . $bad['broken_id'], $html );
	}

	public function test_REQ_051_verifying_is_audited(): void {
		$this->seed();
		$this->as_role( 'administrator' );
		$this->screen()->verify();
		$actions = array_column( $this->screen()->rows( array() ), 'action' );
		$this->assertSame( 'audit.verify', $actions[0] );
	}

	public function test_REQ_051_audit_verify_command_exits_non_zero_when_broken(): void {
		$this->seed();
		$command = new AuditVerify( $this->logger() );
		$out     = new Output( false );
		$this->assertSame( 0, $command->run( array(), $out ) );
		$this->assertSame( array( 'Audit chain intact.' ), $out->lines() );

		global $wpdb;
		$id = (int) $wpdb->get_var( 'SELECT id FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id ASC LIMIT 1 OFFSET 1' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- probe table.
		$this->tamper( $id );
		$broken = new Output( false );
		$this->assertSame( 1, $command->run( array(), $broken ) );
		$this->assertSame( array( 'Audit chain broken at row ' . $id . '.' ), $broken->lines() );
		$this->assertSame( 'audit:verify', $command->name() );
	}
}
