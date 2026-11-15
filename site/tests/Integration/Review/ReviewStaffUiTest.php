<?php
/**
 * Staff UI phase 2 on the Review screens (REQ-275, REQ-193, REQ-084, REQ-222): Recent bulk actions is a read-only shared staff
 * list table whose one row action is Restore this batch; the Review item, Duplicates and Bulk unpublish forms use the staff form
 * kit with their field names, nonces and handlers unchanged. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Admin\BulkBatchesTable;
use WhoIsWho\Core\Review\Admin\ReviewItemScreen;
use WhoIsWho\Core\Review\Bulk\BulkRepository;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\WpTestCase;

final class ReviewStaffUiTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$wpdb->query( 'DELETE FROM ' . BulkRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function as_role( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	public function test_REQ_275_recent_bulk_actions_have_no_checkboxes_and_restore_needs_the_capability(): void {
		( new BulkRepository() )->create( 'uiqwv0001', array( 'source' => 'x' ), 'Ui source withdrawn', 3, 1 );
		( new BulkRepository() )->update( 'uiqwv0001', array( 'status' => BulkRepository::DONE ) );
		$this->as_role( 'wiw_reviewer' );
		$html = ( new BulkBatchesTable() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'Ui source withdrawn', $html );
		$this->assertStringContainsString( '>Restore this batch<', $html );
		$this->assertStringContainsString( 'batch=uiqwv0001', $html );
		$this->assertStringNotContainsString( 'type="checkbox"', $html );
		$this->assertStringNotContainsString( 'wiw-bulk-select', $html );

		$this->as_role( 'wiw_editor' );
		$this->assertStringNotContainsString( 'batch=uiqwv0001', ( new BulkBatchesTable() )->html( new Request( array() ) ), 'no Restore without wiw_bulk_unpublish' );
	}

	public function test_REQ_275_review_item_forms_use_the_kit_and_keep_their_fields(): void {
		$id = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Ui Review Item Qwv',
			)
		);
		( new StateMachine() )->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$this->as_role( 'wiw_reviewer' );
		$html = ( new ReviewItemScreen() )->html( new Request( array( 'profile_id' => (string) $id ) ) );
		$this->assertStringContainsString( 'class="wiw-card"', $html );
		foreach ( array_keys( ReviewItemScreen::checklist() ) as $key ) {
			$this->assertStringContainsString( 'name="' . $key . '" value="1"', $html );
		}
		$this->assertStringContainsString( 'name="action" value="' . ReviewItemScreen::APPROVE . '"', $html );
		$this->assertStringContainsString( 'name="action" value="' . ReviewItemScreen::REJECT . '"', $html );
		$this->assertStringContainsString( 'name="reason_code"', $html );
		$this->assertStringContainsString( 'required="required"', $html, 'a reject reason is still required' );
		global $wpdb;
		wp_delete_post( $id, true );
		$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
