<?php
/**
 * "Update the database" on a live site with no command line (REQ-056, REQ-240, REQ-051): Administrator only, nonce required, the
 * "I have a backup" box required, the waiting migrations applied through the locked runner, the result audited and told in plain
 * words. Activation still never migrates an existing production schema. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Setup\Checklist;
use WhoIsWho\Core\Platform\Setup\FirstRun;
use WhoIsWho\Core\Platform\Setup\SchemaState;
use WhoIsWho\Core\Platform\Setup\SetupScreen;
use WhoIsWho\Tests\WpTestCase;

final class SetupDatabaseUpdateTest extends WpTestCase {

	private string $dir = '';

	/**
	 * Schema options as they were, restored after each test (the database probe commits, so they would leak).
	 *
	 * @var array<string, mixed>
	 */
	private array $saved = array();

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	protected function setUp(): void {
		parent::setUp();
		foreach ( array( Runner::OPTION_VERSION, Runner::OPTION_APPLIED, SchemaState::OPTION_STARTED, SchemaState::OPTION_ERROR, SetupScreen::DB_RESULT ) as $option ) {
			$this->saved[ $option ] = get_option( $option );
		}
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$this->dir = sys_get_temp_dir() . '/wiw-dbupdate-' . uniqid();
		mkdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		delete_option( 'wiw_test_dbupdate_trace' );
		delete_option( SchemaState::OPTION_STARTED );
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*.php' ) ?: array() as $file ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( $this->saved as $option => $value ) {
			if ( false === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value, false );
			}
		}
		delete_option( 'wiw_test_dbupdate_trace' );
		// The server check commits mid-test, so the restore must be committed too or the rollback would undo it.
		self::commit_transaction();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function writeMigration( string $version ): void {
		$code = <<<PHP
<?php
return new class implements \WhoIsWho\Core\Platform\Migrations\MigrationInterface {
	public function version(): string { return '{$version}'; }
	public function description(): string { return 'test {$version}'; }
	public function is_contract(): bool { return false; }
	public function pii_columns(): array { return array(); }
	public function up(): void { update_option( 'wiw_test_dbupdate_trace', '{$version}', false ); }
};
PHP;
		file_put_contents( $this->dir . '/' . $version . '_t.php', $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Audit rows for an action.
	 *
	 * @param string $action Action.
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_rows( string $action ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s", $action ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	public function test_REQ_240_an_editor_is_refused_and_a_nonce_is_required(): void {
		$editor = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $editor );
		$this->assertSame(
			ActionGuard::REFUSED_CAPABILITY,
			ActionGuard::check(
				SetupScreen::ACTION_FIX,
				SetupScreen::CAP,
				new Request(
					array(
						'_wpnonce'                => ActionGuard::nonce_for( SetupScreen::ACTION_FIX ),
						'what'                    => 'database',
						SetupScreen::BACKUP_FIELD => '1',
					)
				)
			)
		);
		wp_set_current_user( $admin );
		$this->assertSame(
			ActionGuard::REFUSED_NONCE,
			ActionGuard::check(
				SetupScreen::ACTION_FIX,
				SetupScreen::CAP,
				new Request(
					array(
						'what'                    => 'database',
						SetupScreen::BACKUP_FIELD => '1',
					)
				)
			)
		);
		$this->assertNull( ActionGuard::check( SetupScreen::ACTION_FIX, SetupScreen::CAP, new Request( array( '_wpnonce' => ActionGuard::nonce_for( SetupScreen::ACTION_FIX ) ) ) ) );
	}

	public function test_REQ_056_a_live_site_needs_the_backup_box_ticked_first(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( SchemaState::may_self_initialise(), 'the site already has its database' );
		$this->writeMigration( '2026999921' );
		$result = SetupScreen::update_database( new Request( array( 'what' => 'database' ) ), $this->dir );
		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( 'I have a backup', $result['error'] );
		$this->assertFalse( get_option( 'wiw_test_dbupdate_trace' ), 'nothing ran' );
		$this->assertStringContainsString( 'Nothing was changed', (string) get_option( SetupScreen::DB_RESULT ) );
	}

	public function test_REQ_056_with_the_backup_confirmed_the_waiting_changes_are_applied_and_audited(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		// The test suite turns CREATE TABLE into temporary tables, which cannot carry triggers; the server check needs real ones.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->writeMigration( '2026999921' );
		$this->writeMigration( '2026999922' );
		$before = count( $this->audit_rows( 'setup.database_updated' ) );

		$result = SetupScreen::update_database(
			new Request(
				array(
					'what'                    => 'database',
					SetupScreen::BACKUP_FIELD => '1',
				)
			),
			$this->dir
		);
		$this->assertTrue( $result['ok'], $result['error'] );
		$this->assertSame( array( '2026999921', '2026999922' ), $result['applied'] );
		$this->assertSame( '2026999922', get_option( 'wiw_test_dbupdate_trace' ) );
		$this->assertSame( 'The database is up to date: 2 changes were applied.', get_option( SetupScreen::DB_RESULT ) );

		$rows = $this->audit_rows( 'setup.database_updated' );
		$this->assertCount( $before + 1, $rows, 'one audit entry' );
		$last = end( $rows );
		$this->assertSame( (string) $admin, (string) $last['actor_id'] );
		$this->assertStringContainsString( '2026999922', (string) $last['new_value'] );

		$again = SetupScreen::update_database( new Request( array( SetupScreen::BACKUP_FIELD => '1' ) ), $this->dir );
		$this->assertSame( array(), $again['applied'], 'safe to press again' );
		$this->assertStringContainsString( 'already up to date', (string) get_option( SetupScreen::DB_RESULT ) );
	}

	public function test_REQ_056_activation_still_never_migrates_an_existing_production_schema(): void {
		$this->writeMigration( '2026999921' );
		$result = FirstRun::activate( $this->dir, true, false, array() );
		$this->assertSame( array(), $result['applied'] );
		$this->assertFalse( get_option( 'wiw_test_dbupdate_trace' ) );
	}

	public function test_REQ_056_the_screen_shows_the_backup_warning_and_checkbox_and_no_deploy_words(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( Runner::OPTION_APPLIED, array_slice( (array) get_option( Runner::OPTION_APPLIED ), 0, -1 ), false );
		$item = Checklist::items()[0];
		$this->assertSame( 'database', $item['action'], (string) wp_json_encode( $item ) );
		$this->assertSame( 'Update the database', $item['action_label'] );
		$html = SetupScreen::html( new Request( array() ) );
		$this->assertStringContainsString( 'Take a backup in cPanel first.', $html );
		$this->assertStringContainsString( 'name="' . SetupScreen::BACKUP_FIELD . '"', $html );
		$this->assertStringContainsString( 'I have a backup', $html );
		$this->assertStringNotContainsString( 'deploy', $item['detail'] . $item['fix'] );
	}
}
