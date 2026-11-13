<?php
/**
 * Activating the plugin on a plain WordPress sets up its tables safely: it runs pending migrations on a non-production site, never
 * in production, never fatals, and tells the owner when the schema is behind (REQ-056). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Cli\WpCliBridge;
use WhoIsWho\Core\Platform\Migrations\ActivationMigrator;
use WhoIsWho\Core\Platform\Migrations\PendingNotice;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Permalinks;
use WhoIsWho\Tests\TestCase;

final class ActivationMigrationTest extends TestCase {

	private string $dir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		$this->dir = sys_get_temp_dir() . '/wiw-act-migrations-' . uniqid();
		mkdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		delete_option( Runner::OPTION_VERSION );
		delete_option( Runner::OPTION_APPLIED );
		delete_option( Runner::OPTION_LOCK );
		delete_option( 'wiw_test_act_trace' );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*.php' ) ?: array() as $file ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		delete_option( 'wiw_test_act_trace' );
		delete_option( Runner::OPTION_VERSION );
		delete_option( Runner::OPTION_APPLIED );
		parent::tearDown();
	}

	private function writeMigration( string $version, string $body = '' ): void {
		$code = <<<PHP
<?php
return new class implements \WhoIsWho\Core\Platform\Migrations\MigrationInterface {
	public function version(): string { return '{$version}'; }
	public function description(): string { return 'test {$version}'; }
	public function is_contract(): bool { return false; }
	public function pii_columns(): array { return array(); }
	public function up(): void { update_option( 'wiw_test_act_trace', '{$version}', false ); {$body} }
};
PHP;
		file_put_contents( $this->dir . '/' . $version . '_t.php', $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public function test_REQ_056_activation_applies_pending_migrations_on_a_local_site(): void {
		$this->writeMigration( '2026999901' );
		$result = ActivationMigrator::run( new Runner( $this->dir, false, false ) );
		$this->assertSame( array( '2026999901' ), $result['applied'] );
		$this->assertNull( $result['error'] );
		$this->assertSame( '2026999901', get_option( 'wiw_test_act_trace' ) );
	}

	public function test_REQ_056_activation_applies_nothing_in_production(): void {
		$this->writeMigration( '2026999901' );
		$result = ActivationMigrator::run( new Runner( $this->dir, true, false ) );
		$this->assertSame( array(), $result['applied'] );
		$this->assertNotNull( $result['error'] );
		$this->assertFalse( get_option( 'wiw_test_act_trace' ) );
	}

	public function test_REQ_056_a_failing_migration_does_not_fatal_the_activation(): void {
		$this->writeMigration( '2026999901', 'throw new \RuntimeException( "secret detail" );' );
		$result = ActivationMigrator::run( new Runner( $this->dir, false, false ) );
		$this->assertNotNull( $result['error'] );
		$this->assertStringNotContainsString( 'secret detail', (string) $result['error'] );
	}

	public function test_REQ_056_activation_twice_is_safe(): void {
		$this->writeMigration( '2026999901' );
		ActivationMigrator::run( new Runner( $this->dir, false, false ) );
		$again = ActivationMigrator::run( new Runner( $this->dir, false, false ) );
		$this->assertSame( array(), $again['applied'] );
		$this->assertNull( $again['error'] );
	}

	public function test_REQ_056_notice_tells_the_owner_when_the_schema_is_behind(): void {
		$this->writeMigration( '2026999901' );
		$behind = PendingNotice::html( new Runner( $this->dir, false, false ), 'local' );
		$this->assertStringContainsString( 'notice-warning', $behind );
		$this->assertStringContainsString( 'migrate', $behind );
		$prod = PendingNotice::html( new Runner( $this->dir, true, false ), 'production' );
		$this->assertStringContainsString( 'deploy', $prod );
		ActivationMigrator::run( new Runner( $this->dir, false, false ) );
		$this->assertSame( '', PendingNotice::html( new Runner( $this->dir, false, false ), 'local' ) );
	}

	public function test_REQ_056_wp_cli_bridge_rebuilds_the_command_line(): void {
		$this->assertSame( array( 'wiw', 'sample:seed' ), WpCliBridge::argv( array( 'sample:seed' ), array() ) );
		$this->assertSame( array( 'wiw', 'migrate', '--dry-run' ), WpCliBridge::argv( array( 'migrate' ), array( 'dry-run' => true ) ) );
		$this->assertSame( array( 'wiw', 'import', '--file=a.csv' ), WpCliBridge::argv( array( 'import' ), array( 'file' => 'a.csv' ) ) );
	}

	public function test_REQ_056_activation_sets_pretty_permalinks_only_on_a_plain_site(): void {
		global $wp_rewrite;
		$before = get_option( 'permalink_structure' );
		try {
			$wp_rewrite->set_permalink_structure( '' );
			$this->assertTrue( Permalinks::ensure_pretty() );
			$this->assertSame( '/%postname%/', get_option( 'permalink_structure' ) );
			$wp_rewrite->set_permalink_structure( '/%year%/%postname%/' );
			$this->assertFalse( Permalinks::ensure_pretty() );
			$this->assertSame( '/%year%/%postname%/', get_option( 'permalink_structure' ) );
		} finally {
			$wp_rewrite->set_permalink_structure( (string) $before );
		}
	}
}
