<?php
/**
 * Bulk actions and row actions on the Requests and reports list (REQ-275, REQ-270). Bulk Resolve, Dismiss, Close and Escalate
 * run the single action for each selected item, which checks its capability and writes one audit row per item; an item of
 * another kind is skipped; closed items have no checkbox; a user without the capability is refused and sees no row actions.
 * Plain TestCase: real support tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Admin\Requests\RequestsScreen;
use WhoIsWho\Core\Compliance\Admin\Requests\RequestsTable;
use WhoIsWho\Core\Compliance\Removals\RemovalDecisions;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class RequestsBulkTest extends TestCase {

	private const PROBE = 'wiw_requests_bulk_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function tearDown(): void {
		global $wpdb;
		wp_set_current_user( 0 );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function login( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		wp_set_current_user( $id );
		return $id;
	}

	private function table(): RequestsTable {
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00' );
		return new RequestsTable( array(), new RequestsScreen( new SupportItems( new DueDates( array() ), 'test-key' ), RemovalDecisions::for_site(), new StateMachine( null, $audit ), $audit ) );
	}

	private function item( string $type ): int {
		return (int) ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create(
			array(
				'type'    => $type,
				'name'    => 'Requester',
				'email'   => 'requester@example.test',
				'message' => 'Details.',
			)
		)['id'];
	}

	private function audit_count( int $id, string $action ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . self::PROBE . ' WHERE action = %s AND subject_id = %s', $action, (string) $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
	}

	/**
	 * A bulk request with a valid nonce.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	private function request( array $values ): Request {
		return new Request( array_merge( array( '_wpnonce' => ActionGuard::nonce_for( RequestsTable::BULK ) ), $values ) );
	}

	public function test_REQ_275_requests_bulk_resolve_audits_each_report_and_skips_other_kinds(): void {
		$this->login( 'wiw_editor' );
		$a       = $this->item( ItemType::REPORT );
		$b       = $this->item( ItemType::REPORT );
		$removal = $this->item( ItemType::REMOVAL );
		$table   = $this->table();
		$result  = $table->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'resolve',
					ListTable::FIELD_IDS    => array( $a, $b, $removal ),
				)
			)
		);
		$this->assertSame( 2, $result['done'] );
		$this->assertSame( 1, $result['skipped'], 'a removal is not a report' );
		$this->assertSame( '2 resolved, 1 skipped (That is not the right kind of item.).', $table->notice_for( $result )['text'] );
		foreach ( array( $a, $b ) as $id ) {
			$this->assertSame( ItemStatus::RESOLVED, ( new SupportItems() )->get( $id )['status'] );
			$this->assertSame( 1, $this->audit_count( $id, 'report.resolved' ), 'one audit row per item' );
		}
		$this->assertSame( ItemStatus::NEW, ( new SupportItems() )->get( $removal )['status'] );
	}

	public function test_REQ_275_requests_bulk_dismiss_is_confirmed_and_close_and_escalate_audit_each_item(): void {
		$this->login( 'wiw_editor' );
		$report = $this->item( ItemType::REPORT );
		$data   = $this->item( ItemType::DATA_REQUEST );
		$other  = $this->item( ItemType::DATA_REQUEST );
		$table  = $this->table();
		$values = array(
			ListTable::FIELD_ACTION => 'dismiss',
			ListTable::FIELD_IDS    => array( $report ),
		);
		$this->assertSame( ListTable::NEEDS_CONFIRM, $table->process_bulk( $this->request( $values ) )['status'], 'dismiss is destructive' );
		$this->assertSame( 1, $table->process_bulk( $this->request( array_merge( $values, array( ListTable::FIELD_CONFIRM => '1' ) ) ) )['done'] );
		$this->assertSame( ItemStatus::DECLINED, ( new SupportItems() )->get( $report )['status'] );
		$this->assertSame( 1, $this->audit_count( $report, 'report.dismissed' ) );

		$closed = $table->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'close',
					ListTable::FIELD_IDS    => array( $data ),
				)
			)
		);
		$this->assertSame( 1, $closed['done'] );
		$this->assertSame( 1, $this->audit_count( $data, 'data_request.closed' ) );

		$escalated = $table->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'escalate',
					ListTable::FIELD_IDS    => array( $other, $data ),
				)
			)
		);
		$this->assertSame( 1, $escalated['done'] );
		$this->assertSame( 1, $escalated['skipped'], 'a closed item cannot be escalated' );
		$this->assertSame( ItemStatus::IN_PROGRESS, ( new SupportItems() )->get( $other )['status'] );
		$this->assertSame( 1, $this->audit_count( $other, 'request.escalated' ) );
	}

	public function test_REQ_275_requests_bulk_is_refused_without_the_capability(): void {
		$this->login( 'wiw_editor' );
		$a = $this->item( ItemType::REPORT );
		$this->login( 'wiw_reviewer' );
		$result = $this->table()->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'escalate',
					ListTable::FIELD_IDS    => array( $a ),
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'] );
		$this->assertSame( 0, $this->audit_count( $a, 'request.escalated' ) );
	}

	public function test_REQ_275_requests_row_actions_follow_the_capability_and_closed_items_have_no_checkbox(): void {
		$this->login( 'wiw_editor' );
		$open   = $this->item( ItemType::REPORT );
		$closed = $this->item( ItemType::REPORT );
		$this->table()->resolve_one( $closed );
		$html = $this->table()->html( new Request( array() ) );
		$this->assertStringContainsString( 'name="ids[]" value="' . $open . '"', $html );
		$this->assertStringNotContainsString( 'name="ids[]" value="' . $closed . '"', $html, 'a closed item has no checkbox' );
		$this->assertStringContainsString( 'wiw_confirm=dismiss', $html );
		$this->assertStringContainsString( '>Open<', $html );

		$item    = ( new SupportItems() )->get( $open );
		$item    = array_merge( (array) $item, array( 'kind' => 'Report' ) );
		$actions = $this->table()->visible_row_actions( $item );
		$this->assertArrayHasKey( 'wiw-resolve', $actions );
		$this->assertArrayNotHasKey( 'wiw-close', $actions, 'Close is for data requests only' );

		$user = wp_get_current_user();
		$user->add_cap( RequestsScreen::CAP_DISPUTES, false );
		$this->assertArrayNotHasKey( 'wiw-resolve', $this->table()->visible_row_actions( $item ), 'deciding a report needs wiw_handle_disputes' );
		$this->assertArrayNotHasKey( 'resolve', $this->table()->allowed_bulk_actions() );

		$this->login( 'wiw_reviewer' );
		$this->assertSame( array(), $this->table()->visible_row_actions( $item ), 'no row actions without wiw_handle_requests' );
	}
}
