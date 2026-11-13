<?php
/**
 * Audit-table triggers and the nightly off-server export (REQ-051). Needs the WordPress test suite. Plain TestCase with probe
 * tables so the real log is never touched.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Audit\AuditExport;
use WhoIsWho\Core\Platform\Audit\AuditGuard;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class AuditGuardTest extends TestCase {

	private const PROBE = 'wiw_auditguard_probe';

	private string $dir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$this->dir = sys_get_temp_dir() . '/wiw-audit-export-' . uniqid();
		delete_option( AuditGuard::OPTION_MISSING );
		delete_option( AuditExport::OPTION_LAST_RUN );
		delete_option( AuditExport::OPTION_LAST_ID );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		foreach ( array_merge( glob( $this->dir . '/*' ) ?: array(), glob( $this->dir . '/.htaccess' ) ?: array() ) as $file ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		delete_option( AuditGuard::OPTION_MISSING );
		parent::tearDown();
	}

	private function logger(): AuditLogger {
		return new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => 3 );
	}

	public function test_REQ_051_triggers_block_update_and_delete_when_privileged(): void {
		global $wpdb;
		$this->logger()->log(
			'profile.unpublish',
			array(
				'type' => 'profile',
				'id'   => 1,
			)
		);
		if ( ! AuditGuard::install( self::PROBE ) ) {
			$this->markTestSkipped( 'This database account may not create triggers.' );
		}
		$this->assertTrue( AuditGuard::installed( self::PROBE ) );
		$this->assertSame( 'on', AuditGuard::status() );
		$table = $wpdb->prefix . self::PROBE;
		$wpdb->suppress_errors( true );
		$updated = $wpdb->query( 'UPDATE ' . $table . " SET reason = 'x'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- probe table.
		$deleted = $wpdb->query( 'DELETE FROM ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- probe table.
		$wpdb->suppress_errors( false );
		$this->assertFalse( $updated, 'an UPDATE is refused by the database' );
		$this->assertFalse( $deleted, 'a DELETE is refused by the database' );
		$this->assertSame( 1, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- probe table.
		$this->logger()->log(
			'profile.restore',
			array(
				'type' => 'profile',
				'id'   => 1,
			)
		);
		$this->assertTrue( $this->logger()->verify()['intact'], 'appending still works' );
	}

	public function test_REQ_051_missing_trigger_privilege_does_not_fail_migration(): void {
		$this->assertFalse( AuditGuard::install( 'wiw_no_such_audit_table' ), 'a refused or impossible trigger is reported, not thrown' );
		$this->assertSame( 'off', AuditGuard::status() );
		$this->assertSame( '1', get_option( AuditGuard::OPTION_MISSING ) );
	}

	public function test_REQ_051_nightly_export_job_records_last_run(): void {
		$this->logger()->log(
			'profile.unpublish',
			array(
				'type' => 'profile',
				'id'   => 1,
			),
			null,
			null,
			'first'
		);
		$this->logger()->log(
			'profile.restore',
			array(
				'type' => 'profile',
				'id'   => 1,
			)
		);
		$queue         = new MemoryQueue();
		$runner        = new JobRunner( $queue, static fn (): int => 1000000 );
		$queue->runner = $runner;
		$export        = new AuditExport( $this->dir, self::PROBE, 'test-signing-key' );
		$export->register( $runner );
		$runner->enqueue( AuditExport::JOB );
		$runner->run_due( 30 );

		$this->assertGreaterThan( 0, (int) get_option( AuditExport::OPTION_LAST_RUN ), 'the last run is recorded' );
		$files = glob( $this->dir . '/audit-*.jsonl' ) ?: array();
		$this->assertCount( 1, $files );
		$lines = array_filter( explode( "\n", (string) file_get_contents( $files[0] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertCount( 2, $lines );
		$row = json_decode( (string) $lines[0], true );
		$this->assertSame( array( 'id', 'at', 'actor_id', 'action', 'subject_type', 'subject_id', 'old_value', 'new_value', 'reason', 'batch_ref', 'prev_hash', 'hash' ), array_keys( $row ), 'no more than the table holds' );
		$this->assertTrue( $export->verify_file( $files[0] ), 'the signature matches' );
		file_put_contents( $files[0], 'tampered', FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertFalse( $export->verify_file( $files[0] ) );

		$this->logger()->log(
			'profile.hide',
			array(
				'type' => 'profile',
				'id'   => 2,
			)
		);
		$export->run();
		$this->assertCount( 2, glob( $this->dir . '/audit-*.jsonl' ) ?: array(), 'only the new rows go into the next file' );
		$export->run();
		$this->assertCount( 2, glob( $this->dir . '/audit-*.jsonl' ) ?: array(), 'nothing new, no new file' );
	}

	public function test_REQ_051_the_export_folder_is_closed_to_the_web(): void {
		$export = new AuditExport( $this->dir, self::PROBE, 'k' );
		$export->run();
		$this->assertFileExists( $this->dir . '/.htaccess' );
		$this->assertFileExists( $this->dir . '/index.php' );
	}
}
