<?php
/**
 * Capability changes and sensitive capability use are audited (REQ-244, REQ-188, REQ-051). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityAudit;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

/**
 * Plain TestCase on purpose: users and roles are real rows, and the audit table is a scratch copy.
 */
final class CapabilityAuditTest extends TestCase {

	private const PROBE = 'wiw_capaudit_audit_probe';

	/**
	 * Listener under test.
	 *
	 * @var CapabilityAudit|null
	 */
	private ?CapabilityAudit $listener = null;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$this->listener = new CapabilityAudit( $this->logger() );
		$this->listener->register();
	}

	protected function tearDown(): void {
		global $wpdb;
		if ( null !== $this->listener ) {
			$this->listener->unregister();
		}
		remove_role( 'wiw_capaudit_role' );
		wp_set_current_user( 0 );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function logger(): AuditLogger {
		return new AuditLogger(
			new WpdbConnection( self::PROBE ),
			static fn (): string => '2026-11-11 10:00:00',
			static fn (): int => (int) get_current_user_id()
		);
	}

	private function user(): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'capaudit' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'wiw_reviewer',
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	/**
	 * Audit rows with a given action.
	 *
	 * @param string $action Action.
	 * @return array<int, array<string, mixed>>
	 */
	private function rows( string $action = '' ): array {
		global $wpdb;
		$all = (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
		return array() === $all || '' === $action ? $all : array_values( array_filter( $all, static fn ( array $r ): bool => $action === $r['action'] ) );
	}

	public function test_REQ_244_capability_grant_and_revoke_are_audited(): void {
		$admin   = (int) wp_insert_user(
			array(
				'user_login' => 'capadmin' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'administrator',
			)
		);
		$subject = $this->user();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB

		wp_set_current_user( $admin );
		( new \WP_User( $subject ) )->add_cap( 'wiw_run_imports' );
		$grant = $this->rows( 'user.capabilities' );
		$this->assertCount( 1, $grant );
		$this->assertSame( (string) $subject, $grant[0]['subject_id'] );
		$this->assertSame( (string) $admin, $grant[0]['actor_id'] );
		$this->assertSame( array( 'granted' => array( 'wiw_run_imports' ) ), json_decode( (string) $grant[0]['new_value'], true ) );
		$this->assertSame( array( 'revoked' => array() ), json_decode( (string) $grant[0]['old_value'], true ) );
		$this->assertNull( $grant[0]['reason'], 'a signed-in actor needs no system reason' );

		( new \WP_User( $subject ) )->remove_cap( 'wiw_run_imports' );
		$rows = $this->rows( 'user.capabilities' );
		$this->assertCount( 2, $rows );
		$this->assertSame( array( 'revoked' => array( 'wiw_run_imports' ) ), json_decode( (string) $rows[1]['old_value'], true ) );
		$this->assertSame( array( 'granted' => array() ), json_decode( (string) $rows[1]['new_value'], true ) );

		( new \WP_User( $subject ) )->add_cap( 'wiw_run_imports' );
		( new \WP_User( $subject ) )->add_cap( 'wiw_run_imports' );
		$this->assertCount( 3, $this->rows( 'user.capabilities' ), 'a write that changes nothing writes no row' );
	}

	public function test_REQ_244_a_change_with_nobody_signed_in_is_logged_as_the_system(): void {
		$subject = $this->user();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		wp_set_current_user( 0 );
		( new \WP_User( $subject ) )->add_cap( 'wiw_export_data' );
		$rows = $this->rows( 'user.capabilities' );
		$this->assertCount( 1, $rows );
		$this->assertSame( '0', $rows[0]['actor_id'] );
		$this->assertSame( CapabilityAudit::SYSTEM_REASON, $rows[0]['reason'] );
	}

	public function test_REQ_244_changing_a_users_role_is_audited(): void {
		$subject = $this->user();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		( new \WP_User( $subject ) )->set_role( 'wiw_editor' );
		$rows = $this->rows( 'user.capabilities' );
		$this->assertNotEmpty( $rows );
		$last = json_decode( (string) end( $rows )['new_value'], true );
		$this->assertContains( 'wiw_editor', $last['granted'] );
	}

	public function test_REQ_244_role_edit_is_audited(): void {
		add_role( 'wiw_capaudit_role', 'Probe role', array( 'read' => true ) );
		$created = $this->rows( 'role.create' );
		$this->assertCount( 1, $created );
		$this->assertSame( 'wiw_capaudit_role', $created[0]['subject_id'] );
		$this->assertSame( array( 'granted' => array( 'read' ) ), json_decode( (string) $created[0]['new_value'], true ) );

		get_role( 'wiw_capaudit_role' )->add_cap( 'wiw_view_audit' );
		$edit = $this->rows( 'role.capabilities' );
		$this->assertCount( 1, $edit );
		$this->assertSame( array( 'granted' => array( 'wiw_view_audit' ) ), json_decode( (string) $edit[0]['new_value'], true ) );

		get_role( 'wiw_capaudit_role' )->remove_cap( 'wiw_view_audit' );
		$this->assertSame( array( 'revoked' => array( 'wiw_view_audit' ) ), json_decode( (string) $this->rows( 'role.capabilities' )[1]['old_value'], true ) );

		remove_role( 'wiw_capaudit_role' );
		$this->assertCount( 1, $this->rows( 'role.delete' ) );
	}

	public function test_REQ_188_sensitive_capability_use_is_logged(): void {
		$actor = $this->user();
		wp_set_current_user( $actor );
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		$logger = $this->logger();

		foreach ( AuditLogger::SENSITIVE_CAPABILITIES as $capability ) {
			$this->assertNotNull(
				$logger->log_use(
					$capability,
					array(
						'type' => 'export',
						'id'   => 'e1',
					),
					'Quarterly export'
				),
				$capability
			);
		}
		$this->assertNull(
			$logger->log_use(
				'wiw_view_dashboard_review',
				array(
					'type' => 'screen',
					'id'   => 'dash',
				)
			),
			'ordinary capabilities are not logged'
		);

		$rows = $this->rows( 'capability.use' );
		$this->assertCount( count( AuditLogger::SENSITIVE_CAPABILITIES ), $rows );
		$this->assertSame( array( 'capability' => 'wiw_export_data' ), json_decode( (string) $rows[0]['new_value'], true ) );
		$this->assertSame( (string) $actor, $rows[0]['actor_id'] );
		$this->assertSame( 'Quarterly export', $rows[0]['reason'] );
	}

	public function test_REQ_244_the_diff_helper_reads_granted_and_denied_the_same_way(): void {
		$this->assertSame(
			array(
				'granted' => array( 'b' ),
				'revoked' => array( 'a' ),
			),
			CapabilityAudit::diff(
				array(
					'a' => true,
					'c' => false,
				),
				array(
					'b' => true,
					'c' => false,
				)
			)
		);
		$this->assertSame(
			array(
				'granted' => array(),
				'revoked' => array( 'a' ),
			),
			CapabilityAudit::diff( array( 'a' => true ), array( 'a' => false ) ),
			'an explicit deny counts as revoked'
		);
	}
}
