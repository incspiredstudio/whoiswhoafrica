<?php
/**
 * The Required plugins list as a shared list table (REQ-275, REQ-056): bulk Install selected and Activate selected repeat the
 * single Install and Activate through the same installer, with the audit row per plugin; they refuse without the screen
 * capability; the row actions show only what the user may do. The per-plugin buttons keep posting their own forms. Needs the
 * WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Plugins\PluginInstaller;
use WhoIsWho\Core\Platform\Plugins\RequiredPlugins;
use WhoIsWho\Core\Platform\Plugins\RequiredPluginsScreen;
use WhoIsWho\Core\Platform\Plugins\RequiredPluginsTable;
use WhoIsWho\Tests\TestCase;

final class RequiredPluginsBulkTest extends TestCase {

	/**
	 * The fake installed-plugin list.
	 *
	 * @var array<string, mixed>
	 */
	private array $installed = array();

	/**
	 * Active plugin files in the fake.
	 *
	 * @var string[]
	 */
	private array $active = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		$this->installed = array(
			'fluent-smtp/fluent-smtp.php'     => array(),
			'wpdiscuz/class.WpdiscuzCore.php' => array(),
		);
		$this->active    = array();
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function as_role( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'rpb' . $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		wp_set_current_user( 0 );
		wp_set_current_user( $id );
		return $id;
	}

	private function table(): RequiredPluginsTable {
		$plugins   = new RequiredPlugins(
			fn (): array => $this->installed,
			fn ( string $file ): bool => in_array( $file, $this->active, true ),
			static fn (): bool => false
		);
		$installer = new PluginInstaller(
			$plugins,
			null,
			null,
			null,
			function ( string $file ) {
				$this->active[] = $file;
				return null;
			}
		);
		return new RequiredPluginsTable( $plugins, $installer );
	}

	/**
	 * Audit rows for a plugin action.
	 *
	 * @param string $action Action.
	 * @return array<int, array<string, mixed>>
	 */
	private function audit( string $action ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT actor_id, subject_id FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s ORDER BY id", $action ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * The list ids of plugins by slug.
	 *
	 * @param string ...$slugs Slugs.
	 * @return int[]
	 */
	private function ids( string ...$slugs ): array {
		return array_map( static fn ( string $slug ): int => RequiredPluginsTable::id_for( $slug ), $slugs );
	}

	public function test_REQ_275_plugins_bulk_refuses_without_the_capability(): void {
		$this->as_role( 'wiw_editor' );
		$result = $this->table()->process_bulk(
			new Request(
				array(
					'_wpnonce'              => ActionGuard::nonce_for( RequiredPluginsTable::BULK ),
					ListTable::FIELD_ACTION => 'activate',
					ListTable::FIELD_IDS    => $this->ids( 'fluent-smtp' ),
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'] );
		$this->assertSame( array(), $this->active );
	}

	public function test_REQ_275_plugins_bulk_activate_runs_the_installer_per_plugin_with_audit(): void {
		$admin  = $this->as_role( 'administrator' );
		$before = count( $this->audit( 'plugins.activate' ) );
		$table  = $this->table();
		$this->assertArrayHasKey( 'activate', $table->allowed_bulk_actions() );
		$this->assertArrayHasKey( 'install', $table->allowed_bulk_actions() );
		$result = $table->process_bulk(
			new Request(
				array(
					'_wpnonce'              => ActionGuard::nonce_for( RequiredPluginsTable::BULK ),
					ListTable::FIELD_ACTION => 'activate',
					ListTable::FIELD_IDS    => array_merge( $this->ids( 'fluent-smtp', 'wpdiscuz' ), array( 999 ) ),
				)
			)
		);
		$this->assertSame( ListTable::DONE, $result['status'] );
		$this->assertSame( 2, $result['done'] );
		$this->assertSame( 1, $result['skipped'], 'an unknown row is skipped' );
		$this->assertSame( array( 'fluent-smtp/fluent-smtp.php', 'wpdiscuz/class.WpdiscuzCore.php' ), $this->active );
		$rows = array_slice( $this->audit( 'plugins.activate' ), $before );
		$this->assertCount( 2, $rows, 'one audit row per plugin' );
		$this->assertSame( array( 'fluent-smtp', 'wpdiscuz' ), array_column( $rows, 'subject_id' ) );
		$this->assertSame( (string) $admin, (string) $rows[0]['actor_id'] );
	}

	public function test_REQ_275_plugins_row_actions_need_the_capability_and_buttons_keep_their_forms(): void {
		$this->as_role( 'administrator' );
		$table = $this->table();
		$item  = array(
			'slug'   => 'wpdiscuz',
			'status' => RequiredPlugins::INSTALLED,
		);
		$this->assertArrayHasKey( 'wiw-activate', $table->visible_row_actions( $item ) );

		$html = RequiredPluginsScreen::html( new Request( array() ), new RequiredPlugins( fn (): array => array(), static fn (): bool => false, static fn (): bool => false ) );
		$this->assertStringContainsString( 'wiw-list-table', $html );
		$this->assertStringContainsString( 'name="ids[]"', $html, 'plugins can be selected for a bulk action' );
		$this->assertStringContainsString( 'Install selected', $html );
		$this->assertMatchesRegularExpression( '#<button type="submit" form="wiw-plugin-install-fluent-smtp"[^>]*>Install FluentSMTP</button>#', $html, 'the per-plugin button posts its own form' );
		$this->assertMatchesRegularExpression( '#<form method="post" id="wiw-plugin-install-fluent-smtp"#', $html );

		$this->as_role( 'wiw_editor' );
		$this->assertSame( array(), $table->visible_row_actions( $item ), 'no row actions without the capability' );
	}
}
