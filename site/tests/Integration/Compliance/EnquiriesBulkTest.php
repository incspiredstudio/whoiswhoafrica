<?php
/**
 * Bulk actions and row actions on the Enquiries list (REQ-275, REQ-089). Bulk Assign to me and Set status run the single status
 * and owner change for each selected enquiry, which checks wiw_support_inbox and writes one audit row per item (ids and statuses
 * only); a user without the capability is refused and sees no row actions. Plain TestCase: real support tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Admin\EnquiriesScreen;
use WhoIsWho\Core\Compliance\Admin\EnquiriesTable;
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
use WhoIsWho\Core\Platform\Notes\NotesService;
use WhoIsWho\Tests\TestCase;

final class EnquiriesBulkTest extends TestCase {

	private const PROBE = 'wiw_enquiries_bulk_probe';

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

	private function table(): EnquiriesTable {
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00' );
		return new EnquiriesTable( array(), new EnquiriesScreen( new SupportItems( new DueDates( array() ), 'test-key' ), $audit, new NotesService( $audit ) ) );
	}

	private function enquiry(): int {
		return (int) ( new SupportItems( new DueDates( array() ), 'test-key' ) )->create(
			array(
				'type'    => ItemType::ENQUIRY,
				'name'    => 'Bulk Person',
				'email'   => 'bulk.person@example.test',
				'message' => 'Hello.',
				'source'  => 'contact',
			)
		)['id'];
	}

	/**
	 * Audit rows for an item.
	 *
	 * @param int $id Item.
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_rows( int $id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . " WHERE action = 'enquiry.update' AND subject_id = %s", (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
	}

	/**
	 * A bulk request with a valid nonce.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	private function request( array $values ): Request {
		return new Request( array_merge( array( '_wpnonce' => ActionGuard::nonce_for( EnquiriesTable::BULK ) ), $values ) );
	}

	public function test_REQ_275_enquiries_bulk_assign_and_status_audit_each_item(): void {
		$me    = $this->login( 'wiw_editor' );
		$a     = $this->enquiry();
		$b     = $this->enquiry();
		$table = $this->table();

		$assigned = $table->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'assign',
					ListTable::FIELD_IDS    => array( $a, $b ),
				)
			)
		);
		$this->assertSame( 2, $assigned['done'] );
		$this->assertSame( '2 assigned to you.', $table->notice_for( $assigned )['text'] );

		$values = array(
			ListTable::FIELD_ACTION => 'status',
			ListTable::FIELD_IDS    => array( $a, $b ),
		);
		$this->assertSame( ListTable::NEEDS_CONFIRM, $table->process_bulk( $this->request( $values ) )['status'], 'the status is chosen in the step' );
		$values[ ListTable::FIELD_CONFIRM ] = '1';
		$this->assertSame( 2, $table->process_bulk( $this->request( array_merge( $values, array( 'status' => 'bogus' ) ) ) )['skipped'] );
		$this->assertSame( 2, $table->process_bulk( $this->request( array_merge( $values, array( 'status' => ItemStatus::RESOLVED ) ) ) )['done'] );

		foreach ( array( $a, $b ) as $id ) {
			$item = ( new SupportItems() )->get( $id );
			$this->assertSame( ItemStatus::RESOLVED, $item['status'] );
			$this->assertSame( $me, (int) $item['owner_id'], 'Set status keeps the owner' );
			$rows = $this->audit_rows( $id );
			$this->assertCount( 2, $rows, 'one audit row per item for each action' );
			$this->assertStringNotContainsString( 'example.test', (string) wp_json_encode( $rows ), 'no personal data in the audit rows' );
		}
	}

	public function test_REQ_275_enquiries_bulk_is_refused_without_the_capability(): void {
		$this->login( 'wiw_editor' );
		$a = $this->enquiry();
		$this->login( 'wiw_reviewer' );
		$result = $this->table()->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'assign',
					ListTable::FIELD_IDS    => array( $a ),
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'] );
		$this->assertSame( array(), $this->audit_rows( $a ) );
	}

	public function test_REQ_275_enquiries_row_actions_follow_the_capability(): void {
		$this->login( 'wiw_editor' );
		$a    = $this->enquiry();
		$html = $this->table()->html( new Request( array() ) );
		$this->assertStringContainsString( 'name="ids[]" value="' . $a . '"', $html );
		$this->assertStringContainsString( '>Open<', $html );
		$this->assertStringContainsString( 'wiw_confirm=assign', $html );
		$this->assertStringContainsString( '<option value="status" data-wiw-step="1">Set status</option>', $html );

		$item = ( new SupportItems() )->get( $a );
		$this->login( 'wiw_reviewer' );
		$this->assertSame( array(), $this->table()->visible_row_actions( (array) $item ), 'no row actions without wiw_support_inbox' );
		$this->assertSame( array(), $this->table()->allowed_bulk_actions() );
	}
}
