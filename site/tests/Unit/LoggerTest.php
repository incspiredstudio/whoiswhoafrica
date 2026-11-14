<?php
/**
 * Structured logging: ids only, request id on every line, 30-day retention (REQ-050, REQ-069).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Log\FileSink;
use WhoIsWho\Core\Platform\Log\LogSink;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

final class LoggerTest extends TestCase {

	/**
	 * Temporary folder.
	 *
	 * @var string
	 */
	private string $dir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/wiw-log-test-' . uniqid();
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/{,.}*', GLOB_BRACE ) ?: array() as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		parent::tearDown();
	}

	public function test_REQ_050_log_lines_contain_ids_not_personal_data(): void {
		$lines  = array();
		$sink   = new class( $lines ) implements LogSink {
			/**
			 * Lines.
			 *
			 * @var array<int, string>
			 */
			private array $lines;

			/**
			 * Constructor.
			 *
			 * @param array<int, string> $lines Lines.
			 */
			public function __construct( array &$lines ) {
				$this->lines = &$lines;
			}

			/**
			 * Write.
			 *
			 * @param string $line Line.
			 */
			public function write( string $line ): void {
				$this->lines[] = $line;
			}
		};
		$logger = new Logger( $sink, new PiiCatalogue( array() ), 'req123456789' );

		$logger->log(
			'warning',
			'Could not email jane.doe@example.org',
			array(
				'profile_id' => 42,
				'name'       => 'Jane Doe',
				'email'      => 'jane.doe@example.org',
				'job_ref'    => 'abc123 for jane@example.org',
				'payload'    => array( 'x' => 1 ),
			)
		);

		$this->assertCount( 1, $lines );
		$this->assertStringNotContainsString( 'jane', strtolower( $lines[0] ) );
		$this->assertStringNotContainsString( 'Doe', $lines[0] );
		$data = json_decode( $lines[0], true );
		$this->assertSame( 'req123456789', $data['request'] );
		$this->assertSame( 42, $data['context']['profile_id'] );
		$this->assertSame( '[redacted]', $data['context']['name'] );
		$this->assertSame( '[redacted]', $data['context']['payload'] );
		$this->assertSame( 'abc123 for [email]', $data['context']['job_ref'] );
		$this->assertSame( 'warning', $data['level'] );
	}

	public function test_REQ_050_logs_older_than_30_days_are_removed(): void {
		$now  = strtotime( '2026-11-11 12:00:00 UTC' );
		$sink = new FileSink( $this->dir, static fn (): int => $now );
		mkdir( $this->dir, 0750, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( array( '2026-09-01', '2026-10-11', '2026-10-12', '2026-11-10' ) as $day ) {
			file_put_contents( $this->dir . "/wiw-$day.log", "x\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		file_put_contents( $this->dir . '/other.txt', 'keep' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$this->assertSame( 2, $sink->purge( 30 ), '2026-09-01 and 2026-10-11 are 30 or more days old.' );
		$this->assertFileDoesNotExist( $this->dir . '/wiw-2026-09-01.log' );
		$this->assertFileExists( $this->dir . '/wiw-2026-10-12.log' );
		$this->assertFileExists( $this->dir . '/other.txt' );

		// Writing creates the daily file and a deny-all guard.
		$sink->write( '{"a":1}' );
		$this->assertFileExists( $this->dir . '/wiw-2026-11-11.log' );
		$this->assertFileExists( $this->dir . '/.htaccess' );
	}

	public function test_REQ_050_the_guard_files_exist_before_the_first_line_is_written_issue_810(): void {
		$seen = array();
		$sink = new FileSink(
			$this->dir,
			function () use ( &$seen ): int {
				// The clock is read just before the log file is named and written: the guards must already be there.
				$seen[] = file_exists( $this->dir . '/.htaccess' ) && file_exists( $this->dir . '/index.php' );
				return strtotime( '2026-11-11 12:00:00 UTC' );
			}
		);
		$sink->write( '{"a":1}' );
		$this->assertSame( array( true ), $seen, 'a log line is never written into an unguarded folder' );
		$this->assertStringContainsString( 'Require all denied', (string) file_get_contents( $this->dir . '/.htaccess' ) );
	}
}
