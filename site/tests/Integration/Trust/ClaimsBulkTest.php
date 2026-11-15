<?php
/**
 * Bulk actions and row actions on the Claims list (REQ-275, REQ-252). Bulk Approve and Reject run ClaimDecision for each selected
 * claim, which checks wiw_review_claims and writes one audit row per claim; Reject needs the shared reason; a decided claim has no
 * checkbox; a user without the capability is refused and sees no row actions. Plain TestCase: the state machine opens real
 * transactions on real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Trust\Claim;
use WhoIsWho\Core\Trust\ClaimRepository;
use WhoIsWho\Core\Trust\Admin\ClaimsTable;
use WhoIsWho\Tests\TestCase;

final class ClaimsBulkTest extends TestCase {

	/**
	 * Posts created.
	 *
	 * @var int[]
	 */
	private array $posts = array();

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
		$wpdb->query( 'DELETE FROM ' . ClaimRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		$wpdb->query( 'DELETE FROM ' . ClaimRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function as_role( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'claimbulk' . wp_generate_password( 6, false ),
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

	/**
	 * A claim on a fresh profile.
	 *
	 * @param string $name Profile name.
	 */
	private function claim( string $name ): int {
		global $wpdb;
		$profile = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'draft',
			)
		);
		$this->assertIsInt( $profile );
		$this->posts[] = $profile;
		$wpdb->delete( StateRepository::table(), array( 'profile_id' => $profile ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a stale row from an earlier run.
		( new StateMachine() )->enter(
			$profile,
			PubState::PUBLISHED_PENDING,
			array(
				'entity_type' => 'organisation',
				'origin'      => 'manual',
			)
		);
		return ( new ClaimRepository() )->create(
			array(
				'profile_id'   => $profile,
				'relationship' => 'representative',
				'name'         => 'Claimant Qzx',
				'role'         => 'Director',
				'work_email'   => 'claimant@example.test',
				'phone'        => '+234 800 000 0000',
				'evidence'     => 'Staff page.',
				'consent'      => true,
			)
		)->id;
	}

	/**
	 * Audit rows for a claim and action.
	 *
	 * @param int    $id     Claim.
	 * @param string $action Audit action.
	 */
	private function audit_count( int $id, string $action ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND subject_id = %s", $action, (string) $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * A bulk request with a valid nonce.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	private function request( array $values ): Request {
		return new Request( array_merge( array( '_wpnonce' => ActionGuard::nonce_for( ClaimsTable::BULK ) ), $values ) );
	}

	public function test_REQ_275_claims_bulk_approve_audits_each_item(): void {
		$this->as_role( 'wiw_editor' );
		$a      = $this->claim( 'Claims Bulk Alpha Qzx' );
		$b      = $this->claim( 'Claims Bulk Beta Qzx' );
		$before = array(
			$a => $this->audit_count( $a, 'claim.approve' ),
			$b => $this->audit_count( $b, 'claim.approve' ),
		);
		$table  = new ClaimsTable();
		$result = $table->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'approve',
					ListTable::FIELD_IDS    => array( $a, $b ),
				)
			)
		);
		$this->assertSame( 2, $result['done'] );
		$this->assertSame( '2 approved.', $table->notice_for( $result )['text'] );
		foreach ( array( $a, $b ) as $id ) {
			$this->assertSame( Claim::APPROVED, ( new ClaimRepository() )->find( $id )->status );
			$this->assertSame( $before[ $id ] + 1, $this->audit_count( $id, 'claim.approve' ), 'one audit row per claim' );
		}
		$again = $table->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'approve',
					ListTable::FIELD_IDS    => array( $a ),
				)
			)
		);
		$this->assertSame( 1, $again['skipped'], 'a decided claim is skipped' );
		$this->assertSame( 'Only a new claim can be decided.', $again['reasons'][0] );
	}

	public function test_REQ_275_claims_bulk_reject_audits_each_item_and_needs_the_shared_reason(): void {
		$this->as_role( 'wiw_editor' );
		$a      = $this->claim( 'Claims Reject Alpha Qzx' );
		$b      = $this->claim( 'Claims Reject Beta Qzx' );
		$before = array(
			$a => $this->audit_count( $a, 'claim.reject' ),
			$b => $this->audit_count( $b, 'claim.reject' ),
		);
		$values = array(
			ListTable::FIELD_ACTION => 'reject',
			ListTable::FIELD_IDS    => array( $a, $b ),
		);
		$table  = new ClaimsTable();
		$this->assertSame( ListTable::NEEDS_CONFIRM, $table->process_bulk( $this->request( $values ) )['status'], 'reject is confirmed with the reason first' );

		$values[ ListTable::FIELD_CONFIRM ] = '1';
		$this->assertSame( 2, $table->process_bulk( $this->request( $values ) )['skipped'], 'no reason, nothing rejected' );
		$this->assertSame( Claim::NEW, ( new ClaimRepository() )->find( $a )->status );

		$result = $table->process_bulk( $this->request( array_merge( $values, array( 'reason' => 'Not connected to the organisation' ) ) ) );
		$this->assertSame( 2, $result['done'] );
		foreach ( array( $a, $b ) as $id ) {
			$claim = ( new ClaimRepository() )->find( $id );
			$this->assertSame( Claim::REJECTED, $claim->status );
			$this->assertSame( 'Not connected to the organisation', $claim->reason );
			$this->assertSame( $before[ $id ] + 1, $this->audit_count( $id, 'claim.reject' ) );
		}
	}

	public function test_REQ_275_claims_bulk_is_refused_without_the_capability(): void {
		$this->as_role( 'wiw_editor' );
		$a      = $this->claim( 'Claims Refused Qzx' );
		$before = $this->audit_count( $a, 'claim.approve' );
		$this->as_role( 'wiw_reviewer' );
		$result = ( new ClaimsTable() )->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'approve',
					ListTable::FIELD_IDS    => array( $a ),
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'] );
		$this->assertSame( Claim::NEW, ( new ClaimRepository() )->find( $a )->status );
		$this->assertSame( $before, $this->audit_count( $a, 'claim.approve' ) );
	}

	public function test_REQ_275_claims_row_actions_follow_the_capability_and_decided_claims_have_no_checkbox(): void {
		$this->as_role( 'wiw_editor' );
		$open    = $this->claim( 'Claims Rows Open Qzx' );
		$decided = $this->claim( 'Claims Rows Decided Qzx' );
		( new ClaimsTable() )->approve_one( $decided );

		$html = ( new ClaimsTable( 'all' ) )->html( new Request( array() ) );
		$this->assertStringContainsString( 'name="ids[]" value="' . $open . '"', $html );
		$this->assertStringNotContainsString( 'name="ids[]" value="' . $decided . '"', $html, 'a decided claim has no checkbox' );
		$this->assertStringContainsString( '>View<', $html );
		$this->assertStringContainsString( 'wiw_confirm=reject', $html, 'Reject opens the reason step for that row' );
		$this->assertStringContainsString( '<option value="reject" class="wiw-destructive" data-wiw-destructive="1" data-wiw-step="1">Reject</option>', $html );

		$item = array( 'claim' => ( new ClaimRepository() )->find( $open ) );
		$this->assertArrayHasKey( 'wiw-approve', ( new ClaimsTable() )->visible_row_actions( $item ) );
		$this->as_role( 'wiw_reviewer' );
		$this->assertSame( array(), ( new ClaimsTable() )->visible_row_actions( $item ), 'no row actions without wiw_review_claims' );
		$this->assertSame( array(), ( new ClaimsTable() )->allowed_bulk_actions() );
	}
}
