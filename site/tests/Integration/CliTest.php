<?php
/**
 * bin/wiw.php and its command registry (REQ-056, REQ-095). Needs the WordPress test suite for the migrate tests.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Cli\Application;
use WhoIsWho\Core\Platform\Cli\MigrateCommand;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class CliTest extends TestCase {

	/**
	 * Temporary migrations folder.
	 *
	 * @var string
	 */
	private string $dir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/wiw-cli-migrations-' . uniqid();
		mkdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*.php' ) ?: array() as $file ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( defined( 'WIW_WP_TESTS' ) && WIW_WP_TESTS ) {
			delete_option( Runner::OPTION_VERSION );
			delete_option( Runner::OPTION_APPLIED );
			delete_option( Runner::OPTION_LOCK );
		}
		parent::tearDown();
	}

	/**
	 * Write a migration that does nothing, or throws.
	 *
	 * @param string $version Version.
	 * @param bool   $fail    Whether up() throws.
	 */
	private function write_migration( string $version, bool $fail = false ): void {
		$body = $fail ? "throw new \\RuntimeException( 'secret@example.org' );" : '';
		$code = <<<PHP
<?php
return new class implements \WhoIsWho\Core\Platform\Migrations\MigrationInterface {
	public function version(): string { return '{$version}'; }
	public function description(): string { return 'test {$version}'; }
	public function up(): void { {$body} }
	public function is_contract(): bool { return false; }
	public function pii_columns(): array { return array(); }
};
PHP;
		file_put_contents( $this->dir . '/' . $version . '.php', $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	private function app( bool $production = false, bool $flag = false ): Application {
		$this->requireWordPress();
		delete_option( Runner::OPTION_VERSION );
		delete_option( Runner::OPTION_APPLIED );
		delete_option( Runner::OPTION_LOCK );
		$app = new Application();
		$app->add( new MigrateCommand( new Runner( $this->dir, $production, $flag ) ) );
		return $app;
	}

	public function test_REQ_056_cli_migrate_command_runs_pending_migrations(): void {
		$this->write_migration( '2026111101' );
		$this->write_migration( '2026111102' );
		$app = $this->app();

		$dry = new Output( false );
		$this->assertSame( 0, $app->run( array( 'wiw.php', 'migrate', '--dry-run' ), $dry ) );
		$this->assertSame( array( 'Current version: 0', 'Pending 2026111101: test 2026111101', 'Pending 2026111102: test 2026111102' ), $dry->lines() );
		$this->assertSame( '0', ( new Runner( $this->dir, false, false ) )->current_version(), 'a dry run applies nothing' );

		$out = new Output( false );
		$this->assertSame( 0, $app->run( array( 'wiw.php', 'migrate' ), $out ) );
		$this->assertSame( array( 'Applied 2026111101', 'Applied 2026111102', 'Done. Version 2026111102' ), $out->lines() );

		$again = new Output( false );
		$this->assertSame( 0, $app->run( array( 'wiw.php', 'migrate' ), $again ) );
		$this->assertSame( array( 'Nothing to apply.' ), $again->lines() );
	}

	public function test_REQ_056_cli_exits_non_zero_on_failure_without_personal_data(): void {
		$this->write_migration( '2026111101' );
		$this->write_migration( '2026111102', true );
		$out = new Output( false );
		$this->assertSame( 1, $this->app()->run( array( 'wiw.php', 'migrate' ), $out ) );
		$text = implode( "\n", $out->lines() );
		$this->assertStringContainsString( '2026111102', $text );
		$this->assertStringNotContainsString( 'secret@example.org', $text );

		$prod = new Output( false );
		$this->assertSame( 1, $this->app( true, false )->run( array( 'wiw.php', 'migrate' ), $prod ), 'production without the deploy flag refuses' );
	}

	public function test_REQ_056_cli_with_no_arguments_lists_commands(): void {
		$app = new Application();
		$app->add( new MigrateCommand( new Runner( $this->dir, false, false ) ) );
		$out = new Output( false );
		$this->assertSame( 0, $app->run( array( 'wiw.php' ), $out ) );
		$this->assertStringContainsString( 'migrate', implode( "\n", $out->lines() ) );
		$this->assertStringContainsString( 'Usage: php bin/wiw.php', $out->lines()[0] );

		$unknown = new Output( false );
		$this->assertSame( 2, $app->run( array( 'wiw.php', 'nope' ), $unknown ), 'an unknown command is a usage error' );
	}

	public function test_REQ_056_cli_refuses_web_context(): void {
		$this->assertTrue( Application::is_cli( 'cli' ) );
		$this->assertFalse( Application::is_cli( 'apache2handler' ) );
		$this->assertFalse( Application::is_cli( 'fpm-fcgi' ) );

		// The real script, served by PHP's web server: it must answer 403 and run nothing.
		$port = 18000 + random_int( 0, 999 );
		$bin  = dirname( __DIR__, 2 ) . '/bin';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- the test needs a real web SAPI.
		$proc = proc_open(
			array( PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $bin ),
			array(
				1 => array( 'file', sys_get_temp_dir() . '/wiw-cli-web.out', 'w' ),
				2 => array( 'file', sys_get_temp_dir() . '/wiw-cli-web.err', 'w' ),
			),
			$pipes
		);
		$this->assertIsResource( $proc );
		try {
			$status = 0;
			for ( $i = 0; $i < 30; $i++ ) {
				$body = @file_get_contents( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the server may not be listening yet; the loop retries.
					"http://127.0.0.1:{$port}/wiw.php",
					false,
					stream_context_create(
						array(
							'http' => array(
								'ignore_errors' => true,
								'timeout'       => 2,
							),
						)
					)
				); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
				if ( false !== $body ) {
					$status = (int) substr( (string) $http_response_header[0], 9, 3 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					break;
				}
				usleep( 200000 );
			}
			$this->assertSame( 403, $status );
			$this->assertSame( "Forbidden\n", $body );
		} finally {
			proc_terminate( $proc );
			proc_close( $proc );
		}
	}
}
