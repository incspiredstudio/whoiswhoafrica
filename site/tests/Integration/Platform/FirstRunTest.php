<?php
/**
 * First-run setup with no wp-config.php edit and no command line (REQ-056): a fresh install creates its tables in any environment,
 * an existing production schema is never changed from the browser, and a server that cannot host the plugin gets a plain-language
 * reason instead of a fatal error. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Setup\FirstRun;
use WhoIsWho\Core\Platform\Setup\SchemaState;
use WhoIsWho\Core\Platform\Setup\SetupNotice;
use WhoIsWho\Core\Platform\Setup\SystemCheck;
use WhoIsWho\Tests\TestCase;

final class FirstRunTest extends TestCase {

	private string $dir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		$this->dir = sys_get_temp_dir() . '/wiw-firstrun-' . uniqid();
		mkdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->reset_options();
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*.php' ) ?: array() as $file ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->reset_options();
		parent::tearDown();
	}

	private function reset_options(): void {
		foreach ( array( Runner::OPTION_VERSION, Runner::OPTION_APPLIED, Runner::OPTION_LOCK, SchemaState::OPTION_STARTED, SchemaState::OPTION_ERROR, 'wiw_test_first_trace' ) as $option ) {
			delete_option( $option );
		}
		delete_option( FirstRun::REDIRECT );
	}

	private function writeMigration( string $version, string $body = '' ): void {
		$code = <<<PHP
<?php
return new class implements \WhoIsWho\Core\Platform\Migrations\MigrationInterface {
	public function version(): string { return '{$version}'; }
	public function description(): string { return 'test {$version}'; }
	public function is_contract(): bool { return false; }
	public function pii_columns(): array { return array(); }
	public function up(): void { update_option( 'wiw_test_first_trace', '{$version}', false ); {$body} }
};
PHP;
		file_put_contents( $this->dir . '/' . $version . '_t.php', $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public function test_REQ_056_a_fresh_install_creates_its_tables_even_in_production(): void {
		$this->writeMigration( '2026999911' );
		$result = FirstRun::activate( $this->dir, true, true, array() );
		$this->assertSame( 'fresh', $result['mode'] );
		$this->assertSame( array( '2026999911' ), $result['applied'] );
		$this->assertSame( '', $result['error'] );
		$this->assertSame( '2026999911', get_option( 'wiw_test_first_trace' ) );
		$this->assertFalse( get_option( SchemaState::OPTION_STARTED ), 'finished, so no resume flag is left' );
		$this->assertNotFalse( get_option( FirstRun::REDIRECT ), 'the administrator is sent to the Setup screen once' );
	}

	public function test_REQ_056_an_existing_production_schema_is_never_migrated_from_the_browser(): void {
		$this->writeMigration( '2026999911' );
		$result = FirstRun::activate( $this->dir, true, false, array() );
		$this->assertSame( 'upgrade', $result['mode'] );
		$this->assertSame( array(), $result['applied'] );
		$this->assertFalse( get_option( 'wiw_test_first_trace' ), 'the pending migration did not run' );
		$this->assertSame( '', $result['error'], 'refusing in production is the normal answer, not a fault' );
	}

	public function test_REQ_056_an_upgrade_on_a_non_production_site_still_runs_on_activation(): void {
		$this->writeMigration( '2026999911' );
		$result = FirstRun::activate( $this->dir, false, false, array() );
		$this->assertSame( array( '2026999911' ), $result['applied'] );
	}

	public function test_REQ_056_a_server_that_cannot_host_the_plugin_gets_a_plain_reason_and_no_fatal(): void {
		$this->writeMigration( '2026999911' );
		$problem = array(
			'id'      => 'sqlite',
			'message' => 'This site uses a SQLite database.',
			'fix'     => 'Create the site on a host that gives you a MySQL or MariaDB database.',
		);
		$result  = FirstRun::activate( $this->dir, true, true, array( $problem ) );
		$this->assertSame( array(), $result['applied'] );
		$this->assertStringContainsString( 'SQLite', $result['error'] );
		$this->assertStringContainsString( 'MariaDB', $result['error'] );
		$this->assertFalse( get_option( 'wiw_test_first_trace' ) );
		$html = SetupNotice::html( array( $problem ), (string) get_option( SchemaState::OPTION_ERROR, '' ), true );
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'SQLite', $html );
		$this->assertStringContainsString( 'MariaDB', $html );
		$this->assertStringContainsString( 'page=wiw-setup', $html );
	}

	public function test_REQ_056_a_first_install_that_stopped_halfway_can_be_resumed_in_production(): void {
		$this->writeMigration( '2026999911' );
		$this->writeMigration( '2026999912', 'throw new \RuntimeException( "boom" );' );
		$first = FirstRun::activate( $this->dir, true, true, array() );
		$this->assertNotSame( '', $first['error'] );
		$this->assertStringNotContainsString( 'boom', $first['error'] );
		$this->assertTrue( SchemaState::may_self_initialise(), 'the schema is half made, so the browser may finish it' );
		unlink( $this->dir . '/2026999912_t.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->writeMigration( '2026999912' );
		$again = FirstRun::activate( $this->dir, true, null, array() );
		$this->assertSame( '', $again['error'] );
		$this->assertSame( array( '2026999912' ), $again['applied'] );
		$this->assertFalse( SchemaState::may_self_initialise() );
	}

	public function test_REQ_056_a_site_with_a_schema_is_not_fresh(): void {
		update_option( Runner::OPTION_VERSION, '2026111101', false );
		$this->assertFalse( SchemaState::is_fresh() );
	}

	public function test_REQ_056_the_system_check_names_each_problem_and_its_fix(): void {
		$this->assertSame( array(), SystemCheck::problems( '8.3.0', '6.5', array( 'mbstring', 'json' ), false ) );
		$old = SystemCheck::problems( '8.1.0', '6.0', array( 'mbstring', 'json' ), false );
		$this->assertSame( 'versions', $old[0]['id'] );
		$this->assertStringContainsString( '8.1.0', $old[0]['message'] );
		$this->assertStringContainsString( 'host', $old[0]['fix'] );
		$missing = SystemCheck::problems( '8.3.0', '6.5', array( 'json' ), false );
		$this->assertSame( 'ext-mbstring', $missing[0]['id'] );
		$sqlite = SystemCheck::problems( '8.3.0', '6.5', array( 'mbstring', 'json' ), true );
		$this->assertSame( 'sqlite', $sqlite[0]['id'] );
		$this->assertStringContainsString( 'MySQL or MariaDB', $sqlite[0]['message'] );
		foreach ( array_merge( $old, $missing, $sqlite ) as $problem ) {
			$this->assertNotSame( '', $problem['fix'] );
		}
	}

	public function test_REQ_056_the_notice_is_silent_when_nothing_is_wrong(): void {
		$this->assertSame( '', SetupNotice::html( array(), '', true ) );
	}
}
