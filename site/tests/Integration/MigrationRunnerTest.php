<?php
/**
 * Forward-only migration runner (REQ-056, REQ-051). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class MigrationRunnerTest extends TestCase {

	/**
	 * Temporary migrations folder.
	 *
	 * @var string
	 */
	private string $dir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		$this->dir = sys_get_temp_dir() . '/wiw-migrations-' . uniqid();
		mkdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		delete_option( Runner::OPTION_VERSION );
		delete_option( Runner::OPTION_LOCK );
		delete_option( 'wiw_test_trace' );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*.php' ) ?: array() as $file ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( '' !== $this->dir && is_dir( $this->dir ) ) {
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		delete_option( 'wiw_test_trace' );
		parent::tearDown();
	}

	/**
	 * Write a migration file whose up() appends its version to an option, then runs $extra.
	 *
	 * @param string $version  Version.
	 * @param string $extra    PHP statements run inside up().
	 * @param bool   $contract Contract flag.
	 */
	private function write_migration( string $version, string $extra = '', bool $contract = false ): void {
		$contract_php = $contract ? 'true' : 'false';
		$code         = <<<PHP
<?php
return new class implements \WhoIsWho\Core\Platform\Migrations\MigrationInterface {
	public function version(): string { return '{$version}'; }
	public function description(): string { return 'test {$version}'; }
	public function up(): void {
		\$trace   = get_option( 'wiw_test_trace', array() );
		\$trace[] = '{$version}';
		update_option( 'wiw_test_trace', \$trace, false );
		{$extra}
	}
	public function is_contract(): bool { return {$contract_php}; }
	public function pii_columns(): array { return array(); }
};
PHP;
		file_put_contents( $this->dir . '/' . $version . '.php', $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public function test_REQ_056_migrations_run_in_order_and_are_idempotent(): void {
		$this->write_migration( '2026111103' );
		$this->write_migration( '2026111101' );
		$this->write_migration( '2026111102' );
		$runner = new Runner( $this->dir, false, false );

		$first = $runner->run();
		$this->assertSame( array( '2026111101', '2026111102', '2026111103' ), $first['applied'] );
		$this->assertNull( $first['error'] );
		$this->assertSame( '2026111103', $runner->current_version() );
		$this->assertSame( array( '2026111101', '2026111102', '2026111103' ), get_option( 'wiw_test_trace' ) );

		$second = $runner->run();
		$this->assertSame( array(), $second['applied'] );
		$this->assertCount( 3, get_option( 'wiw_test_trace' ), 're-running applies nothing' );
	}

	public function test_REQ_056_failed_migration_does_not_advance_version(): void {
		$this->write_migration( '2026111101' );
		$this->write_migration( '2026111102', "throw new \\RuntimeException( 'jane@example.org is wrong' );" );
		$this->write_migration( '2026111103' );
		$runner = new Runner( $this->dir, false, false );

		$result = $runner->run();
		$this->assertSame( array( '2026111101' ), $result['applied'] );
		$this->assertSame( '2026111101', $runner->current_version(), 'version stops before the failed migration' );
		$this->assertNotNull( $result['error'] );
		$this->assertStringContainsString( '2026111102', (string) $result['error'] );
		$this->assertStringNotContainsString( 'jane@example.org', (string) $result['error'], 'no personal data in the report' );
		$this->assertFalse( get_option( Runner::OPTION_LOCK ), 'the lock is released after a failure' );
	}

	public function test_REQ_056_production_migrate_requires_deploy_flag(): void {
		$this->write_migration( '2026111101' );

		$refused = ( new Runner( $this->dir, true, false ) )->run();
		$this->assertSame( array(), $refused['applied'] );
		$this->assertNotNull( $refused['error'] );
		$this->assertSame( '0', ( new Runner( $this->dir, true, false ) )->current_version() );

		$allowed = ( new Runner( $this->dir, true, true ) )->run();
		$this->assertSame( array( '2026111101' ), $allowed['applied'] );
	}

	public function test_REQ_056_overlapping_runs_are_refused(): void {
		$this->write_migration( '2026111101' );
		add_option( Runner::OPTION_LOCK, (string) time(), '', false );

		$result = ( new Runner( $this->dir, false, false ) )->run();
		$this->assertSame( array(), $result['applied'] );
		$this->assertStringContainsString( 'in progress', (string) $result['error'] );
	}

	public function test_REQ_051_schema_helper_creates_utf8mb4_innodb_tables(): void {
		global $wpdb;
		$table = \WhoIsWho\Core\Platform\Migrations\Schema::create_table( 'wiw_schema_probe', 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT, note varchar(190) NOT NULL, PRIMARY KEY  (id)' );
		try {
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->assertIsArray( $status );
			$this->assertSame( 'InnoDB', $status['Engine'] );
			$this->assertStringStartsWith( 'utf8mb4', (string) $status['Collation'] );
		} finally {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
		}
	}

	public function test_REQ_056_contract_migrations_are_listed_and_there_is_no_down(): void {
		$this->write_migration( '2026111101' );
		$this->write_migration( '2026111102', '', true );
		$this->assertSame( array( '2026111102' ), ( new Runner( $this->dir, false, false ) )->contract_versions() );
		$this->assertFalse( method_exists( MigrationInterface::class, 'down' ), 'no way to run a down migration' );
	}
}
