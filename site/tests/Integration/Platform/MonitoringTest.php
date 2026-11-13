<?php
/**
 * Monitoring hooks: failure alerts, the backup-age check and view counts staying off public surfaces (REQ-050, REQ-045, REQ-078).
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Log\FileSink;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Monitoring\Alerts;
use WhoIsWho\Core\Platform\Monitoring\BackupCheck;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Platform\Log\LogSink;
use WhoIsWho\Tests\TestCase;

final class MonitoringTest extends TestCase {

	/**
	 * Mails the alerts tried to send.
	 *
	 * @var array<int, array{0: string, 1: string, 2: string}>
	 */
	private array $sent = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		$this->sent = array();
		delete_option( Alerts::OPTION_RECIPIENT );
		delete_option( BackupCheck::OPTION );
		delete_transient( Alerts::THROTTLE_PREFIX . 'job_' . md5( 'import.chunk' ) );
		delete_transient( Alerts::THROTTLE_PREFIX . 'form' );
		delete_transient( Alerts::THROTTLE_PREFIX . 'formcount' );
		wp_set_current_user( 0 );
	}

	private function alerts(): Alerts {
		return new Alerts(
			function ( string $to, string $subject, string $body ): bool {
				$this->sent[] = array( $to, $subject, $body );
				return true;
			}
		);
	}

	public function test_REQ_050_failed_job_reaches_the_recipient_with_ids_only(): void {
		update_option( Alerts::OPTION_RECIPIENT, 'ops@example.org' );
		$this->alerts()->job_failed( 'import.chunk', '42' );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'ops@example.org', $this->sent[0][0] );
		$this->assertStringContainsString( 'import.chunk', $this->sent[0][2] );
		$this->assertStringContainsString( '42', $this->sent[0][2] );
	}

	public function test_REQ_050_repeated_job_failures_send_one_alert_per_hour(): void {
		$a = $this->alerts();
		$a->job_failed( 'import.chunk', '1' );
		$a->job_failed( 'import.chunk', '2' );
		$this->assertCount( 1, $this->sent );
	}

	public function test_REQ_050_alerts_go_to_the_site_admin_address_when_none_is_set(): void {
		$this->alerts()->job_failed( 'import.chunk', '1' );
		$this->assertSame( get_option( 'admin_email' ), $this->sent[0][0] );
	}

	public function test_REQ_050_a_burst_of_form_failures_raises_one_alert(): void {
		$a = $this->alerts();
		for ( $i = 0; $i < Alerts::FORM_THRESHOLD - 1; $i++ ) {
			$a->form_failure( 'enquiry', 'SERVER', array() );
		}
		$this->assertCount( 0, $this->sent, 'A few failures are normal.' );
		$a->form_failure( 'enquiry', 'SERVER', array() );
		$a->form_failure( 'enquiry', 'SERVER', array() );
		$this->assertCount( 1, $this->sent );
		$this->assertStringNotContainsString( '@', $this->sent[0][2] );
	}

	public function test_REQ_050_form_failure_logged_without_pii(): void {
		$lines  = array();
		$sink   = new class( $lines ) implements LogSink {
			/** @var array<int, string> */
			public array $lines;
			/** @param array<int, string> $lines Lines. */
			public function __construct( array &$lines ) {
				$this->lines = &$lines;
			}
			public function write( string $line ): void {
				$this->lines[] = $line;
			}
		};
		$logger = new Logger( $sink, new PiiCatalogue() );
		$logger->log(
			'warning',
			'form_failure',
			array(
				'form'   => 'enquiry',
				'code'   => 'SPAM',
				'fields' => 'email',
				'email'  => 'ada@example.com',
			)
		);
		$this->assertNotEmpty( $lines );
		$this->assertStringNotContainsString( 'ada@example.com', implode( "\n", $lines ) );
		$this->assertStringContainsString( 'form_failure', $lines[0] );
	}

	public function test_REQ_045_backup_check_flags_missing_and_stale_backups(): void {
		$now   = 1800000000;
		$check = new BackupCheck( static fn (): int => $now );
		$this->assertSame( BackupCheck::NEVER, $check->status() );
		$check->record();
		$this->assertSame( BackupCheck::FRESH, $check->status() );
		$stale = new BackupCheck( static fn (): int => $now + 3 * 86400 );
		$this->assertSame( BackupCheck::STALE, $stale->status() );
		$this->assertStringContainsString( '3 days', $stale->message() );
	}

	public function test_REQ_045_backup_notice_shows_to_administrators_only(): void {
		$check = new BackupCheck( static fn (): int => 1800000000 );
		wp_set_current_user( 0 );
		ob_start();
		$check->notice();
		$this->assertSame( '', (string) ob_get_clean() );
		$admin = wp_insert_user(
			array(
				'user_login' => 'monitor_admin_' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password( 20 ),
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $admin );
		ob_start();
		$check->notice();
		$out = (string) ob_get_clean();
		wp_set_current_user( 0 );
		wp_delete_user( (int) $admin );
		$this->assertStringContainsString( 'notice-warning', $out );
		$this->assertStringContainsString( 'backup', strtolower( $out ) );
	}

	public function test_REQ_078_view_counts_not_public(): void {
		$root = dirname( __DIR__, 3 ) . '/wp-content';
		$hits = array();
		$dirs = array( $root . '/themes', $root . '/plugins/whoiswho-core/src/Directory/Public', $root . '/plugins/whoiswho-core/src/Content' );
		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( 'php' !== $file->getExtension() || false !== strpos( (string) $file->getPathname(), 'node_modules' ) ) {
					continue;
				}
				$code = (string) file_get_contents( $file->getPathname() );
				if ( false !== strpos( $code, 'ViewCounter' ) || false !== strpos( $code, 'wiw_view_counts_daily' ) ) {
					$hits[] = $file->getPathname();
				}
			}
		}
		$this->assertSame( array(), $hits, 'Public templates and read models must not read daily view counts.' );
	}
}
