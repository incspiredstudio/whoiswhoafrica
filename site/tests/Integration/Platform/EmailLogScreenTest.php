<?php
/**
 * The Email log screen (REQ-075, REQ-051). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Mail\Admin\EmailLogScreen;
use WhoIsWho\Core\Platform\Mail\WpdbEmailLog;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

/**
 * Plain TestCase on purpose (real tables, see ProfileTablesTest); the log table is emptied in setUp.
 */
final class EmailLogScreenTest extends TestCase {

	private const PROBE = 'wiw_emaillog_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . EmailLogScreen::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function tearDown(): void {
		global $wpdb;
		wp_set_current_user( 0 );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	private function screen( int $actor ): EmailLogScreen {
		wp_set_current_user( 0 );
		wp_set_current_user( $actor );
		return new EmailLogScreen( new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => $actor ), new WpdbEmailLog() );
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	private function seed(): void {
		$log = new WpdbEmailLog();
		$log->add( 'msg-1', 'claim.invite', str_repeat( 'a', 64 ), 'sent', 'sent' );
		$log->add( 'msg-2', 'staff.digest', str_repeat( 'b', 64 ), 'sent', 'failed' );
	}

	public function test_REQ_075_the_service_refuses_users_without_the_capability(): void {
		$this->seed();
		$this->expectException( \DomainException::class );
		$this->screen( $this->user( 'wiw_reviewer' ) )->rows();
	}

	public function test_REQ_075_a_failed_send_shows_as_failed(): void {
		$this->seed();
		$html = $this->screen( $this->user( 'administrator' ) )->html( new Request( array() ) );
		$this->assertStringContainsString( 'wiw-failed', $html );
		$this->assertStringContainsString( 'Failed', $html );
		$this->assertStringContainsString( 'staff.digest', $html );

		$only_failed = $this->screen( $this->user( 'administrator' ) )->html( new Request( array( 'result' => 'failed' ) ) );
		$this->assertStringContainsString( 'staff.digest', $only_failed );
		$this->assertStringNotContainsString( 'claim.invite', $only_failed, 'the result filter works' );
		$by_event = $this->screen( $this->user( 'administrator' ) )->html( new Request( array( 'event' => 'claim.invite' ) ) );
		$this->assertStringNotContainsString( 'staff.digest', $by_event, 'the event filter works' );
	}

	public function test_REQ_075_recording_a_bounce_is_audited(): void {
		$this->seed();
		$admin  = $this->user( 'administrator' );
		$screen = $this->screen( $admin );
		$rows   = $screen->rows();
		$first  = end( $rows );
		$screen->record( (int) $first['id'], 'bounce' );

		$after = $screen->rows();
		$this->assertCount( 3, $after, 'a bounce event was added to the log' );
		$this->assertSame( 'bounce', $after[0]['type'] );
		$this->assertSame( 'recorded', $after[0]['result'] );
		$this->assertSame( $first['recipient_ref'], $after[0]['recipient_ref'] );

		$audit = $this->audit_rows();
		$this->assertCount( 1, $audit );
		$this->assertSame( 'email.record_bounce', $audit[0]['action'] );
		$this->assertSame( (string) $first['id'], $audit[0]['subject_id'] );
		$this->assertSame( (string) $admin, $audit[0]['actor_id'] );

		$screen->record( (int) $first['id'], 'complaint' );
		$this->assertSame( 'email.record_complaint', $this->audit_rows()[1]['action'] );
	}

	public function test_REQ_075_only_the_capability_can_record(): void {
		$this->seed();
		$id = (int) $this->screen( $this->user( 'administrator' ) )->rows()[0]['id'];
		try {
			$this->screen( $this->user( 'wiw_editor' ) )->record( $id, 'bounce' );
			$this->fail( 'an Editor must not record' );
		} catch ( \DomainException $e ) {
			$this->assertSame( array(), $this->audit_rows() );
		}
		$this->expectException( \InvalidArgumentException::class );
		$this->screen( $this->user( 'administrator' ) )->record( $id, 'unsubscribe' );
	}

	public function test_REQ_075_the_screen_shows_no_message_body_or_full_address(): void {
		$this->seed();
		$html = $this->screen( $this->user( 'administrator' ) )->html( new Request( array() ) );
		$this->assertStringNotContainsString( str_repeat( 'a', 64 ), $html, 'only a short reference of the recipient is shown' );
		$this->assertStringContainsString( str_repeat( 'a', 10 ), $html );
		$this->assertStringNotContainsString( '@', strip_tags( $html ) );
		$this->assertStringContainsString( '_wpnonce', $html, 'the record forms carry a nonce' );
	}
}
