<?php
/**
 * The retention job (REQ-063, REQ-233): expired signed links, old logs and old nominations go, suppression and the audit log
 * never, and the log of what it did holds counts only. Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Compliance\Retention\RetentionJob;
use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\WpTestCase;

final class RetentionJobTest extends WpTestCase {

	private const NOW = 1800000000;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		foreach ( array( 'wiw_signed_links', 'wiw_email_events', 'wiw_purge_log', AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES ) as $table ) {
			$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_set_current_user( 0 );
	}

	private function job( ?array $config = null ): RetentionJob {
		$config = $config ?? array(
			'signed_links_days'          => 14,
			'signed_links_check_days'    => 2,
			'signed_link_check_purposes' => array( 'verify_nominator', 'verify_requester' ),
			'logs_days'                  => 30,
			'nominations_days'           => 730,
		);
		return new RetentionJob( AuditLogger::for_site(), $config, static fn (): int => self::NOW );
	}

	private function ago( float $days ): string {
		return gmdate( 'Y-m-d H:i:s', (int) ( self::NOW - $days * 86400 ) );
	}

	private function link( string $purpose, float $expired_days_ago ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wiw_signed_links',
			array(
				'token_hash'   => hash( 'sha256', $purpose . $expired_days_ago . wp_rand() ),
				'purpose'      => $purpose,
				'subject_type' => 'thing',
				'subject_id'   => '1',
				'email'        => 'someone@example.test',
				'created_at'   => $this->ago( $expired_days_ago + 1 ),
				'expires_at'   => $this->ago( $expired_days_ago ),
			)
		);
	}

	private function rows( string $table, string $where = '1=1' ): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . $table . ' WHERE ' . $where ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
	}

	public function test_REQ_063_retention_job_removes_expired_data(): void {
		global $wpdb;
		$this->link( 'claim', 20 );          // Old general link: goes.
		$this->link( 'claim', 5 );           // Recently expired: stays for now.
		$this->link( 'verify_nominator', 3 ); // Short-lived check, past 48 hours: goes.
		$this->link( 'verify_nominator', 1 ); // Check, within 48 hours: stays.
		$wpdb->insert(
			$wpdb->prefix . 'wiw_email_events',
			array(
				'message_id'    => 'm1',
				'event'         => 'e',
				'recipient_ref' => str_repeat( 'a', 64 ),
				'type'          => 'text',
				'result'        => 'sent',
				'created_at'    => $this->ago( 40 ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'wiw_email_events',
			array(
				'message_id'    => 'm2',
				'event'         => 'e',
				'recipient_ref' => str_repeat( 'b', 64 ),
				'type'          => 'text',
				'result'        => 'sent',
				'created_at'    => $this->ago( 2 ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'wiw_nominations',
			array(
				'category_id'     => 1,
				'nominator_name'  => 'Nora',
				'nominator_email' => 'nora@example.test',
				'contact_email'   => 'c@example.test',
				'created_at'      => gmdate( 'Y-m-d H:i:s', time() - 800 * 86400 ),
				'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$dry = $this->job()->run( true );
		$this->assertTrue( $dry['dry_run'] );
		$this->assertSame( 2, $dry['signed_links'] );
		$this->assertSame( 1, $dry['logs'] );
		$this->assertSame( 1, $dry['nominations'] );
		$this->assertSame( 4, $this->rows( 'wiw_signed_links' ), 'a dry run changes nothing' );
		$this->assertSame( 2, $this->rows( 'wiw_email_events' ) );

		$real = $this->job()->run();
		$this->assertSame( 2, $real['signed_links'] );
		$this->assertSame( 2, $this->rows( 'wiw_signed_links' ) );
		$this->assertSame( 0, $this->rows( 'wiw_signed_links', "purpose = 'verify_nominator' AND expires_at < '" . $this->ago( 2 ) . "'" ) );
		$this->assertSame( 1, $this->rows( 'wiw_email_events' ) );
		$this->assertSame( 1, $this->rows( 'wiw_nominations', 'contact_email IS NULL AND nominator_email IS NULL' ), 'the nomination was anonymised' );

		$again = $this->job()->run();
		$this->assertSame( 0, $again['signed_links'] + $again['logs'] + $again['nominations'], 'a second run finds nothing' );
	}

	public function test_REQ_063_retention_never_purges_suppression_or_audit(): void {
		( new Suppression( AuditLogger::for_site() ) )->add_declined_nominee( 'Kept Name Zq', 'individual' );
		AuditLogger::for_site()->log(
			'test.entry',
			array(
				'type' => 'thing',
				'id'   => 1,
			)
		);
		$suppressed = $this->rows( 'wiw_suppression' );
		$audited    = $this->rows( 'wiw_audit_log' );
		$this->assertGreaterThan( 0, $suppressed );
		$this->assertGreaterThan( 0, $audited );

		// Even a config that tries to give them a period cannot make the job touch them.
		$this->job(
			array(
				'suppression' => 1,
				'audit_log'   => 1,
				'logs_days'   => 1,
			)
		)->run();
		$this->assertSame( $suppressed, $this->rows( 'wiw_suppression' ) );
		$this->assertGreaterThanOrEqual( $audited, $this->rows( 'wiw_audit_log' ), 'the audit log only grows' );
		$this->assertFalse( RetentionJob::may_purge( 'wiw_suppression' ) );
		$this->assertFalse( RetentionJob::may_purge( 'wiw_audit_log' ) );
		$this->assertTrue( RetentionJob::may_purge( 'wiw_signed_links' ) );

		$config = require dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/config/retention.php';
		$this->assertSame( 'indefinite', $config['suppression'] );
		$this->assertSame( 'kept', $config['audit_log'] );
	}

	public function test_REQ_063_retention_log_has_no_pii(): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wiw_nominations',
			array(
				'category_id'     => 1,
				'nominator_name'  => 'Nora Nominator',
				'nominator_email' => 'nora.nominator@example.test',
				'contact_email'   => 'press.contact@example.test',
				'created_at'      => gmdate( 'Y-m-d H:i:s', time() - 800 * 86400 ),
				'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->link( 'verify_nominator', 9 );
		$this->job()->run();
		$rows = (string) wp_json_encode( $wpdb->get_results( "SELECT action, subject_type, subject_id, new_value FROM {$wpdb->prefix}wiw_audit_log WHERE action IN ('retention.run','nomination.retention')", ARRAY_A ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertStringContainsString( 'retention.run', $rows );
		foreach ( array( 'Nora', 'nora.nominator', 'press.contact', 'someone@example.test', '@' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $rows, 'the retention log holds counts only' );
		}
		$this->assertStringContainsString( 'signed_links', $rows );
	}

	public function test_REQ_063_retention_values_come_from_config_and_the_job_is_scheduled(): void {
		$config = require dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/config/retention.php';
		$this->assertSame( 14, $config['signed_links_days'] );
		$this->assertSame( 2, $config['signed_links_check_days'], '48 hours for nominator and requester checks' );
		$this->assertSame( 30, $config['logs_days'] );
		$this->assertSame( 730, $config['nominations_days'], 'nominations 2 years' );

		// With no number set, nothing is purged for that class.
		$this->link( 'claim', 400 );
		$this->job( array( 'signed_links_days' => null ) )->run();
		$this->assertSame( 1, $this->rows( 'wiw_signed_links' ) );
		$this->assertTrue( method_exists( RetentionJob::class, 'register' ) );
	}
}
