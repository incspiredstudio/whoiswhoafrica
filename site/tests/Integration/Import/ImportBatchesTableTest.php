<?php
/**
 * The Import history as a shared list table (REQ-275, REQ-237): bulk Undo selected repeats the single Undo through the same
 * service (destructive, so confirmed first), with the import.undo audit row per batch; a batch that is not applied is skipped;
 * the bulk action refuses without wiw_run_imports; the row actions (Open, Undo) show only to a holder of it. Needs the WordPress
 * test suite. Plain TestCase: real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Import\Admin\ImportBatchesTable;
use WhoIsWho\Core\Import\Admin\ImportScreen;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class ImportBatchesTableTest extends TestCase {

	private const PREFIX = 'batch-bulk-undo-';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		$this->clean();
	}

	protected function tearDown(): void {
		$this->clean();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function clean(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . BatchRepository::table() . ' WHERE batch_id LIKE %s', self::PREFIX . '%' ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
	}

	private function as_role( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'ibt' . $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		wp_set_current_user( 0 );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * A batch row with no records; returns its row id.
	 *
	 * @param string $suffix Batch id suffix.
	 * @param string $status Status.
	 */
	private function batch( string $suffix, string $status ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			BatchRepository::table(),
			array(
				'batch_id'          => self::PREFIX . $suffix,
				'run_id'            => 'run-1',
				'contract_version'  => '1.0',
				'file_hash'         => 'sha256:abc',
				'record_count'      => 0,
				'status'            => $status,
				'progress_cursor'   => 0,
				'created_by'        => 1,
				'created_at'        => gmdate( 'Y-m-d H:i:s' ),
				'updated_at'        => gmdate( 'Y-m-d H:i:s' ),
				'settings_snapshot' => '{}',
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Undo audit rows for a batch.
	 *
	 * @param string $suffix Batch id suffix.
	 * @return array<int, array<string, mixed>>
	 */
	private function audit( string $suffix ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT actor_id FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'import.undo' AND subject_id = %s ORDER BY id", self::PREFIX . $suffix ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * A bulk request.
	 *
	 * @param int[]                $ids    Row ids.
	 * @param array<string, mixed> $extra  More values.
	 */
	private function request( array $ids, array $extra = array() ): Request {
		return new Request(
			array_merge(
				array(
					'_wpnonce'              => ActionGuard::nonce_for( ImportBatchesTable::BULK ),
					ListTable::FIELD_ACTION => 'undo',
					ListTable::FIELD_IDS    => $ids,
				),
				$extra
			)
		);
	}

	public function test_REQ_275_import_bulk_undo_refuses_without_the_capability(): void {
		$id = $this->batch( 'a', 'applied' );
		$this->as_role( 'wiw_editor' );
		$result = ( new ImportBatchesTable() )->process_bulk( $this->request( array( $id ), array( ListTable::FIELD_CONFIRM => '1' ) ) );
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'] );
		$this->assertSame( 'applied', ( new BatchRepository() )->get( self::PREFIX . 'a' )['status'] );
	}

	public function test_REQ_275_import_bulk_undo_runs_the_service_per_batch_with_audit(): void {
		$admin  = $this->as_role( 'administrator' );
		$a      = $this->batch( 'a', 'applied' );
		$b      = $this->batch( 'b', 'applied' );
		$c      = $this->batch( 'c', 'dry_run' );
		$before = array(
			'a' => count( $this->audit( 'a' ) ),
			'b' => count( $this->audit( 'b' ) ),
		);
		$table  = new ImportBatchesTable();
		$this->assertSame( ListTable::NEEDS_CONFIRM, $table->process_bulk( $this->request( array( $a, $b, $c ) ) )['status'], 'destructive: confirmed first' );
		$this->assertSame( 'applied', ( new BatchRepository() )->get( self::PREFIX . 'a' )['status'] );

		$result = $table->process_bulk( $this->request( array( $a, $b, $c ), array( ListTable::FIELD_CONFIRM => '1' ) ) );
		$this->assertSame( 2, $result['done'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertSame( '2 undone, 1 skipped (Only an applied batch can be undone.).', $table->notice_for( $result )['text'] );
		foreach ( array( 'a', 'b' ) as $suffix ) {
			$this->assertSame( 'undone', ( new BatchRepository() )->get( self::PREFIX . $suffix )['status'] );
			$rows = $this->audit( $suffix );
			$this->assertCount( $before[ $suffix ] + 1, $rows, 'one audit row per batch (the audit log is append-only, so earlier runs stay)' );
			$this->assertSame( (string) $admin, (string) end( $rows )['actor_id'] );
		}
		$this->assertSame( 'dry_run', ( new BatchRepository() )->get( self::PREFIX . 'c' )['status'] );
		$this->assertSame( self::PREFIX . 'a', ( new ImportBatchesTable() )->name_for( $a ) );
	}

	public function test_REQ_275_import_history_row_actions_need_the_capability(): void {
		$this->as_role( 'administrator' );
		$id   = $this->batch( 'a', 'applied' );
		$item = ( new BatchRepository() )->get( self::PREFIX . 'a' );
		$this->assertIsArray( $item );
		$actions = ( new ImportBatchesTable() )->visible_row_actions( $item );
		$this->assertArrayHasKey( 'wiw-open', $actions );
		$this->assertArrayHasKey( 'wiw-undo', $actions );
		$this->assertStringContainsString( ListTable::STEP_ACTION . '=undo', $actions['wiw-undo'], 'the row Undo opens the confirmation step' );

		$html = ( new ImportScreen() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'wiw-list-table', $html );
		$this->assertStringContainsString( 'name="ids[]" value="' . $id . '"', $html );
		$this->assertStringContainsString( 'Undo selected', $html );

		$this->as_role( 'wiw_editor' );
		$this->assertSame( array(), ( new ImportBatchesTable() )->visible_row_actions( $item ) );
	}
}
