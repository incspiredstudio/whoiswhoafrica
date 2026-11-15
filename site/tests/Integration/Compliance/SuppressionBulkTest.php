<?php
/**
 * Bulk Remove and the row action on the Suppression list (REQ-275, REQ-207). Bulk Remove takes one shared reason and removes each
 * entry through the Suppression service, which checks wiw_edit_suppression and writes one audit row per entry; a user who may
 * only view is refused, and gets no checkboxes, no row actions and no form. Plain TestCase: real suppression table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Admin\Suppression\SuppressionTable;
use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class SuppressionBulkTest extends TestCase {

	/**
	 * Users created.
	 *
	 * @var int[]
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$wpdb->query( 'DELETE FROM ' . Suppression::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Suppression::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function as_role( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'supbulk' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		wp_set_current_user( $id );
		return $id;
	}

	private function service(): Suppression {
		return new Suppression( AuditLogger::for_site() );
	}

	private function audit_count( int $id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'suppression.remove' AND subject_id = %s AND reason = %s", (string) $id, 'Entered in error' ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * A bulk request with a valid nonce.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	private function request( array $values ): Request {
		return new Request( array_merge( array( '_wpnonce' => ActionGuard::nonce_for( SuppressionTable::BULK ) ), $values ) );
	}

	public function test_REQ_275_suppression_bulk_remove_needs_the_shared_reason_and_audits_each_entry(): void {
		$this->as_role( 'administrator' );
		$a      = $this->service()->add_manual( 'Bulk Remove Alpha Qzx', 'individual', array(), 'Asked' );
		$b      = $this->service()->add_manual( 'Bulk Remove Beta Qzx', 'organisation', array(), 'Legal' );
		$before = array(
			$a => $this->audit_count( $a ),
			$b => $this->audit_count( $b ),
		);
		$table  = new SuppressionTable();
		$values = array(
			ListTable::FIELD_ACTION => 'remove',
			ListTable::FIELD_IDS    => array( $a, $b ),
		);
		$this->assertSame( ListTable::NEEDS_CONFIRM, $table->process_bulk( $this->request( $values ) )['status'], 'remove is confirmed with the reason first' );
		$values[ ListTable::FIELD_CONFIRM ] = '1';
		$this->assertSame( 2, $table->process_bulk( $this->request( $values ) )['skipped'], 'no reason, nothing removed' );
		$this->assertNotNull( $this->service()->get( $a ) );

		$result = $table->process_bulk( $this->request( array_merge( $values, array( 'reason' => 'Entered in error' ) ) ) );
		$this->assertSame( '2 removed.', $table->notice_for( $result )['text'], 'the notice carries a count, not the names' );
		foreach ( array( $a, $b ) as $id ) {
			$this->assertNull( $this->service()->get( $id ) );
			$this->assertSame( $before[ $id ] + 1, $this->audit_count( $id ), 'one audit row per entry, with the reason' );
		}
	}

	public function test_REQ_275_suppression_bulk_is_refused_for_a_viewer(): void {
		$this->as_role( 'administrator' );
		$a = $this->service()->add_manual( 'Bulk Viewer Qzx', 'individual', array(), 'Asked' );
		$this->as_role( 'wiw_editor' );
		$result = ( new SuppressionTable() )->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION  => 'remove',
					ListTable::FIELD_IDS     => array( $a ),
					ListTable::FIELD_CONFIRM => '1',
					'reason'                 => 'Entered in error',
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'] );
		$this->assertNotNull( $this->service()->get( $a ) );
	}

	public function test_REQ_275_suppression_row_actions_and_checkboxes_only_for_the_editor_of_the_list(): void {
		$this->as_role( 'administrator' );
		$a    = $this->service()->add_manual( 'Row Action Qzx', 'individual', array(), 'Asked' );
		$html = ( new SuppressionTable() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'name="ids[]" value="' . $a . '"', $html );
		$this->assertStringContainsString( 'wiw_confirm=remove', $html );

		foreach ( array( 'wiw_reviewer', 'wiw_editor' ) as $role ) {
			$this->as_role( $role );
			$html = ( new SuppressionTable() )->html( new Request( array() ) );
			$this->assertStringContainsString( 'Row Action Qzx', $html, "{$role} sees the entry" );
			$this->assertStringNotContainsString( 'name="ids[]"', $html, "{$role} gets no checkboxes" );
			$this->assertStringNotContainsString( 'wiw_confirm=remove', $html, "{$role} gets no row actions" );
			$this->assertStringNotContainsString( '<form', $html, "{$role} gets no form" );
		}
	}
}
