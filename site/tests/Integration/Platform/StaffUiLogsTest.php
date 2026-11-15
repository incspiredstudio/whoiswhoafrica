<?php
/**
 * The staff logs as shared list tables (REQ-275, REQ-051, REQ-075, REQ-209): the Audit log, the Email log and the Purge log are
 * read only, so their tables have no checkboxes and no bulk actions; they page with the kit's pager and keep their filters. The
 * Email log keeps its one single action per row (record a bounce or a complaint) as row actions carrying the action's nonce,
 * shown only to a holder of the capability. Needs the WordPress test suite. Plain TestCase: real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\Admin\AuditLogTable;
use WhoIsWho\Core\Platform\Audit\Admin\AuditScreen;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Cache\Admin\PurgeLogScreen;
use WhoIsWho\Core\Platform\Cache\MemoryPurgeLog;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Mail\Admin\EmailLogScreen;
use WhoIsWho\Core\Platform\Mail\Admin\EmailLogTable;
use WhoIsWho\Core\Platform\Mail\WpdbEmailLog;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class StaffUiLogsTest extends TestCase {

	private const PROBE = 'wiw_staffui_logs_probe';

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

	private function as_role( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'sul' . $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		wp_set_current_user( 0 );
		wp_set_current_user( $id );
		return $id;
	}

	private function logger( int $actor = 7 ): AuditLogger {
		return new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => $actor );
	}

	public function test_REQ_275_audit_log_has_no_checkboxes_no_bulk_and_pages_by_fifty(): void {
		for ( $i = 1; $i <= 53; $i++ ) {
			$this->logger()->log(
				'profile.touch',
				array(
					'type' => 'profile',
					'id'   => $i,
				)
			);
		}
		$this->as_role( 'administrator' );
		$screen = new AuditScreen( $this->logger(), self::PROBE );
		$this->assertSame( 53, $screen->count( array() ) );
		$this->assertSame( 53, $screen->count( array( 'action' => 'profile.' ) ) );
		$this->assertSame( 0, $screen->count( array( 'actor' => 99 ) ) );

		$html = $screen->html( new Request( array() ) );
		$this->assertStringContainsString( 'wiw-list-table', $html );
		$this->assertStringContainsString( '53 items', $html, 'the pager counts every row' );
		$this->assertStringNotContainsString( 'name="ids[]"', $html, 'a log has no checkboxes' );
		$this->assertStringNotContainsString( 'wiw-bulk-select', $html, 'a log has no bulk actions' );
		$this->assertStringNotContainsString( '<div class="row-actions', $html, 'the audit log has no row actions' );
		$this->assertSame( array(), ( new AuditLogTable( $screen, array() ) )->allowed_bulk_actions() );
		$this->assertSame( 3, substr_count( ( new AuditScreen( $this->logger(), self::PROBE ) )->html( new Request( array( 'paged' => 2 ) ) ), 'profile.touch' ), 'page two holds the last three rows' );
	}

	public function test_REQ_275_email_log_has_no_checkboxes_and_record_row_actions_need_the_capability(): void {
		$log = new WpdbEmailLog();
		$log->add( 'msg-1', 'claim.invite', str_repeat( 'a', 64 ), 'sent', 'sent' );
		$admin  = $this->as_role( 'administrator' );
		$screen = new EmailLogScreen( $this->logger( $admin ), $log );
		$html   = $screen->html( new Request( array() ) );
		$this->assertStringContainsString( 'wiw-list-table', $html );
		$this->assertStringNotContainsString( 'name="ids[]"', $html, 'a log has no checkboxes' );
		$this->assertStringNotContainsString( 'wiw-bulk-select', $html, 'a log has no bulk actions' );
		$this->assertStringContainsString( '>Record bounce<', $html );
		$this->assertStringContainsString( '>Record complaint<', $html );
		$this->assertStringContainsString( 'action=' . EmailLogScreen::ACTION, $html );
		$this->assertStringContainsString( 'kind=bounce', $html );
		$this->assertStringContainsString( '_wpnonce=' . ActionGuard::nonce_for( EmailLogScreen::ACTION ), $html, 'the row action carries the record action\'s nonce' );
		$this->assertStringNotContainsString( 'Delete', $html );

		$row = $screen->rows()[0];
		$this->as_role( 'wiw_editor' );
		$this->assertSame( array(), ( new EmailLogTable( $screen, '', '' ) )->visible_row_actions( $row ), 'no record action without the capability' );
	}

	public function test_REQ_275_purge_log_has_no_checkboxes_and_no_bulk(): void {
		$log = new MemoryPurgeLog();
		for ( $i = 1; $i <= 51; $i++ ) {
			$log->add( 'profile', $i, 1, 'ok', 'unpublished', '2026-11-01 10:00:00' );
		}
		$log->add( 'profile', 99, 1, 'failed', 'declined', '2026-11-02 10:00:00' );
		$this->assertSame( 52, $log->count() );
		$this->assertSame( 1, $log->count( array( 'status' => 'failed' ) ) );
		$this->as_role( 'administrator' );
		$html = ( new PurgeLogScreen( $log ) )->html( new Request( array() ) );
		$this->assertStringContainsString( 'wiw-list-table', $html );
		$this->assertStringContainsString( '52 items', $html );
		$this->assertStringNotContainsString( 'name="ids[]"', $html );
		$this->assertStringNotContainsString( 'wiw-bulk-select', $html );
		$this->assertStringNotContainsString( '<div class="row-actions', $html );
		$this->assertStringContainsString( 'wiw-pill--danger">failed<', $html, 'a failed purge stands out' );
	}
}
