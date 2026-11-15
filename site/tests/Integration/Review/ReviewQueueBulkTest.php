<?php
/**
 * Bulk actions and row actions on the Review queue (REQ-275, REQ-084, REQ-222). Bulk approve runs the same path as the item
 * screen for each selected profile (checklist, review service, state machine) and writes one audit row per profile; bulk reject
 * needs the shared reason; revision rows have no checkbox. Needs the WordPress test suite. Plain TestCase: the machine opens real
 * transactions on real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Admin\ReviewQueueTable;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ReviewQueueBulkTest extends TestCase {

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
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
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
		$this->posts = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function reviewer(): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'bulkrev' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => 'wiw_reviewer',
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		wp_set_current_user( $id );
		return $id;
	}

	private function pending( string $name ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'draft',
			)
		);
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		( new StateMachine() )->enter(
			$id,
			PubState::PUBLISHED_PENDING,
			array(
				'entity_type' => 'organisation',
				'origin'      => 'manual',
			)
		);
		return $id;
	}

	/**
	 * Audit rows for a profile and action.
	 *
	 * @param int $id Profile.
	 * @return array<int, array<string, mixed>>
	 */
	private function audit( int $id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT actor_id, new_value FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'profile.state_change' AND subject_id = %s", (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * A bulk request with a valid nonce.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	private function request( array $values ): Request {
		return new Request( array_merge( array( '_wpnonce' => ActionGuard::nonce_for( ReviewQueueTable::BULK ) ), $values ) );
	}

	public function test_REQ_275_bulk_approve_goes_through_the_state_machine_with_audit_per_item(): void {
		$actor  = $this->reviewer();
		$a      = $this->pending( 'Bulk Approve Alpha Qzx' );
		$b      = $this->pending( 'Bulk Approve Beta Qzx' );
		$before = array( count( $this->audit( $a ) ), count( $this->audit( $b ) ) );
		$values = array(
			ListTable::FIELD_ACTION => 'approve',
			ListTable::FIELD_IDS    => array( $a, $b ),
		);

		$table = new ReviewQueueTable();
		$this->assertSame( ListTable::NEEDS_CONFIRM, $table->process_bulk( $this->request( $values ) )['status'], 'the checklist step comes first' );
		$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->get( $a )['pub_state'] );

		$values[ ListTable::FIELD_CONFIRM ] = '1';
		$partial                            = $table->process_bulk( $this->request( array_merge( $values, array( 'c1' => '1' ) ) ) );
		$this->assertSame( 0, $partial['done'], 'the whole checklist is needed, as on the item screen' );
		$this->assertSame( 2, $partial['skipped'] );
		$this->assertStringContainsString( 'checklist', $partial['reasons'][0] );

		$result = $table->process_bulk(
			$this->request(
				array_merge(
					$values,
					array(
						'c1' => '1',
						'c2' => '1',
						'c3' => '1',
						'c4' => '1',
						'c5' => '1',
					)
				)
			)
		);
		$this->assertSame( 2, $result['done'] );
		$this->assertSame( '2 approved.', $table->notice_for( $result )['text'] );
		foreach ( array( $a, $b ) as $i => $id ) {
			$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->get( $id )['pub_state'] );
			$rows = $this->audit( $id );
			$this->assertCount( $before[ $i ] + 1, $rows, 'one audit row per item' );
			$this->assertSame( $actor, (int) end( $rows )['actor_id'] );
		}

		$again = $table->process_bulk( $this->request( array_merge( $values, array_fill_keys( array( 'c1', 'c2', 'c3', 'c4', 'c5' ), '1' ) ) ) );
		$this->assertSame( 2, $again['skipped'], 'an approved profile is skipped, not approved twice' );
	}

	public function test_REQ_275_bulk_reject_needs_the_shared_reason(): void {
		$this->reviewer();
		$a      = $this->pending( 'Bulk Reject Alpha Qzx' );
		$values = array(
			ListTable::FIELD_ACTION  => 'reject',
			ListTable::FIELD_IDS     => array( $a ),
			ListTable::FIELD_CONFIRM => '1',
		);
		$table  = new ReviewQueueTable();
		$this->assertSame( 1, $table->process_bulk( $this->request( $values ) )['skipped'] );
		$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->get( $a )['pub_state'] );

		$result = $table->process_bulk( $this->request( array_merge( $values, array( 'reason' => 'Out of scope for the directory' ) ) ) );
		$this->assertSame( 1, $result['done'] );
		$this->assertSame( PubState::UNPUBLISHED, ( new StateRepository() )->get( $a )['pub_state'] );
	}

	public function test_REQ_275_bulk_is_refused_without_the_review_capability(): void {
		$a             = $this->pending( 'Bulk Refused Alpha Qzx' );
		$id            = wp_insert_user(
			array(
				'user_login' => 'bulksub' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'subscriber',
			)
		);
		$this->users[] = $id;
		wp_set_current_user( $id );
		$result = ( new ReviewQueueTable() )->process_bulk(
			$this->request(
				array(
					ListTable::FIELD_ACTION => 'assign',
					ListTable::FIELD_IDS    => array( $a ),
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'] );
	}

	public function test_REQ_275_queue_shows_row_actions_and_checkboxes_for_profile_items(): void {
		$this->reviewer();
		$a    = $this->pending( 'Bulk Row Alpha Qzx' );
		$html = ( new ReviewQueueTable() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'name="ids[]" value="' . $a . '"', $html );
		$this->assertStringContainsString( '>Review<', $html );
		$this->assertStringContainsString( '>Approve<', $html );
		$this->assertStringContainsString( 'wiw_confirm=approve', $html, 'Approve opens the checklist step for that row' );
		$this->assertStringContainsString( '<option value="approve" data-wiw-step="1">Approve</option>', $html );
		$this->assertStringContainsString( '<th scope="col" id=\'kind\'', $html, 'the Kind heading stays' );
	}
}
