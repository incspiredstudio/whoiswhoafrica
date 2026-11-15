<?php
/**
 * Staff UI phase 2 on the Awards screens (REQ-275, REQ-202, REQ-266): the Nominations list and the Award categories tables are
 * shared staff list tables. Bulk actions repeat the single actions through the same services, so the capability is checked and
 * the audit row written per item; row actions show only what the user may do; the change history has no checkboxes. Needs the
 * WordPress test suite and the real awards tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\CategoriesScreen;
use WhoIsWho\Core\Awards\CategoriesTable;
use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Awards\NominationHistoryTable;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Awards\NominationsTable;
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
use WhoIsWho\Tests\WpTestCase;

final class AwardsStaffUiTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES, AwardsTables::CHANGES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function as_role( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	private function profile( string $name ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		return $id;
	}

	private function category( string $title = 'Digital Pioneer Ui' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => 'technology-innovation',
				'title'      => $title,
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function nomination( int $profile_id, int $category_id ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id'     => $category_id,
				'profile_id'      => $profile_id,
				'nominator_name'  => 'Nora Nominator',
				'nominator_email' => 'nora.nominator@example.test',
				'created_at'      => '2026-11-12 10:00:00',
				'updated_at'      => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function audit_count( string $action, int $id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s AND subject_id = %s", $action, (string) $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * A bulk request for a table.
	 *
	 * @param string               $bulk   The table's bulk action.
	 * @param string               $action Bulk action key.
	 * @param int[]                $ids    Ids.
	 * @param array<string, mixed> $extra  More values.
	 */
	private function bulk( string $bulk, string $action, array $ids, array $extra = array() ): Request {
		return new Request(
			array_merge(
				array(
					'_wpnonce'              => ActionGuard::nonce_for( $bulk ),
					ListTable::FIELD_ACTION => $action,
					ListTable::FIELD_IDS    => $ids,
				),
				$extra
			)
		);
	}

	public function test_REQ_275_nominations_bulk_set_stage_runs_the_service_per_item_and_audits_each(): void {
		$cat = $this->category();
		$a   = $this->nomination( $this->profile( 'Ui Stage Alpha Zq' ), $cat );
		$b   = $this->nomination( $this->profile( 'Ui Stage Beta Zq' ), $cat );
		$this->as_role( 'wiw_editor' );
		$table = new NominationsTable();

		$ask = $table->process_bulk( $this->bulk( NominationsTable::BULK, 'stage', array( $a, $b ), array( 'value' => 'winner' ) ) );
		$this->assertSame( ListTable::NEEDS_CONFIRM, $ask['status'], 'the shared stage is chosen in the confirmation step first' );
		$this->assertSame( 'nominated', NominationService::for_site()->find( $a )['stage'] );

		$done = $table->process_bulk(
			$this->bulk(
				NominationsTable::BULK,
				'stage',
				array( $a, $b ),
				array(
					'value'                  => 'winner',
					ListTable::FIELD_CONFIRM => '1',
				)
			)
		);
		$this->assertSame( 2, $done['done'] );
		$this->assertSame( '2 stages saved.', $table->notice_for( $done )['text'] );
		foreach ( array( $a, $b ) as $id ) {
			$this->assertSame( 'winner', NominationService::for_site()->find( $id )['stage'] );
			$this->assertSame( 1, $this->audit_count( 'nomination.stage', $id ), 'one audit row per nomination' );
		}

		$bad = $table->process_bulk(
			$this->bulk(
				NominationsTable::BULK,
				'stage',
				array( $a ),
				array(
					'value'                  => 'finalist',
					ListTable::FIELD_CONFIRM => '1',
				)
			)
		);
		$this->assertSame( 1, $bad['skipped'], 'the service refuses an unknown stage, as for the single control' );
	}

	public function test_REQ_275_nominations_bulk_remove_is_confirmed_and_audits_each(): void {
		$cat = $this->category();
		$a   = $this->nomination( $this->profile( 'Ui Remove Alpha Zq' ), $cat );
		$b   = $this->nomination( $this->profile( 'Ui Remove Beta Zq' ), $cat );
		$this->as_role( 'wiw_editor' );
		$table = new NominationsTable();
		$html  = $table->html( new Request( array() ) );
		$this->assertStringContainsString( '<option value="remove" data-wiw-destructive="1">', $html );
		$this->assertStringNotContainsString( 'Nora', $html, 'the nominator is never on the list' );

		$this->assertSame( ListTable::NEEDS_CONFIRM, $table->process_bulk( $this->bulk( NominationsTable::BULK, 'remove', array( $a, $b ) ) )['status'] );
		$this->assertNotNull( NominationService::for_site()->find( $a ), 'nothing is removed before the confirmation' );
		$done = $table->process_bulk( $this->bulk( NominationsTable::BULK, 'remove', array( $a, $b ), array( ListTable::FIELD_CONFIRM => '1' ) ) );
		$this->assertSame( 2, $done['done'] );
		foreach ( array( $a, $b ) as $id ) {
			$this->assertNull( NominationService::for_site()->find( $id ) );
			$this->assertSame( 1, $this->audit_count( 'nomination.remove', $id ) );
		}
	}

	public function test_REQ_275_nominations_bulk_refuses_without_the_capability(): void {
		$id = $this->nomination( $this->profile( 'Ui Refuse Zq' ), $this->category() );
		$this->as_role( 'wiw_reviewer' );
		$table  = new NominationsTable();
		$result = $table->process_bulk(
			$this->bulk(
				NominationsTable::BULK,
				'status',
				array( $id ),
				array(
					'value'                  => 'live',
					ListTable::FIELD_CONFIRM => '1',
				)
			)
		);
		$this->assertSame( ListTable::REFUSED_CAP, $result['status'] );
		$this->assertSame( 'not_live', NominationService::for_site()->find( $id )['status'] );
		$this->assertSame( array(), $table->allowed_bulk_actions() );
	}

	public function test_REQ_275_nominations_row_actions_and_checkboxes_follow_the_capability(): void {
		$id = $this->nomination( $this->profile( 'Ui Rows Zq' ), $this->category() );
		$this->as_role( 'wiw_editor' );
		$html = ( new NominationsTable() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'name="ids[]" value="' . $id . '"', $html );
		$this->assertStringContainsString( '>Details<', $html );
		$this->assertStringContainsString( 'wiw_confirm=remove', $html );
		$this->assertStringContainsString( 'form="wiw-nomination-stage-' . $id . '"', $html, 'the inline stage chooser posts through its own form' );
		$this->assertSame( 0, substr_count( substr( $html, 0, (int) strpos( $html, '</form>' ) ), '<form id=' ), 'no control form sits inside the bulk form' );

		$this->as_role( 'wiw_reviewer' );
		$html = ( new NominationsTable() )->html( new Request( array() ) );
		$this->assertStringNotContainsString( 'name="ids[]"', $html );
		$this->assertStringNotContainsString( '>Details<', $html );
		$this->assertStringNotContainsString( 'wiw_confirm=remove', $html );
	}

	public function test_REQ_275_categories_bulk_archive_audits_each_and_skips_unchanged(): void {
		$a = $this->category( 'Ui Archive Alpha' );
		$b = $this->category( 'Ui Archive Beta' );
		$this->as_role( 'wiw_editor' );
		$table = new CategoriesTable();
		$done  = $table->process_bulk( $this->bulk( CategoriesTable::BULK, 'archive', array( $a, $b ) ) );
		$this->assertSame( 2, $done['done'] );
		foreach ( array( $a, $b ) as $id ) {
			$this->assertSame( 1, (int) CategoryService::for_site()->find( $id )['archived'] );
			$this->assertSame( 1, $this->audit_count( 'award_category.archive', $id ) );
		}
		$again = $table->process_bulk( $this->bulk( CategoriesTable::BULK, 'archive', array( $a ) ) );
		$this->assertSame( 1, $again['skipped'] );
		$this->assertSame( '0 archived, 1 skipped (already archived).', $table->notice_for( $again )['text'] );
		$this->assertSame( 1, $this->audit_count( 'award_category.archive', $a ), 'nothing is logged for an unchanged category' );
	}

	public function test_REQ_275_categories_bulk_remove_skips_a_category_with_nominations(): void {
		$used = $this->category( 'Ui Remove Used' );
		$free = $this->category( 'Ui Remove Free' );
		$this->nomination( $this->profile( 'Ui Used Zq' ), $used );
		$this->as_role( 'wiw_editor' );
		$table = new CategoriesTable();
		$this->assertSame( ListTable::NEEDS_CONFIRM, $table->process_bulk( $this->bulk( CategoriesTable::BULK, 'remove', array( $used, $free ) ) )['status'] );
		$done = $table->process_bulk( $this->bulk( CategoriesTable::BULK, 'remove', array( $used, $free ), array( ListTable::FIELD_CONFIRM => '1' ) ) );
		$this->assertSame( 1, $done['done'] );
		$this->assertSame( 1, $done['skipped'] );
		$this->assertNull( CategoryService::for_site()->find( $free ) );
		$this->assertNotNull( CategoryService::for_site()->find( $used ) );
		$this->assertSame( 1, $this->audit_count( 'award_category.remove', $free ) );
		$this->assertSame( 1, $this->audit_count( 'award_category.remove_refused', $used ) );
	}

	public function test_REQ_275_categories_bulk_refuses_without_the_capability_and_hides_row_actions(): void {
		$id = $this->category( 'Ui Hidden' );
		$this->as_role( 'wiw_reviewer' );
		$table = new CategoriesTable( 'technology-innovation' );
		$this->assertSame( ListTable::REFUSED_CAP, $table->process_bulk( $this->bulk( CategoriesTable::BULK, 'archive', array( $id ) ) )['status'] );
		$this->assertSame( 0, (int) CategoryService::for_site()->find( $id )['archived'] );
		$html = $table->html( new Request( array() ) );
		$this->assertStringContainsString( 'Ui Hidden', $html );
		$this->assertStringNotContainsString( 'name="ids[]"', $html );
		$this->assertStringNotContainsString( '>Rename<', $html );
		$this->assertStringNotContainsString( 'op=archive', $html );
	}

	public function test_REQ_275_categories_screen_shows_one_table_per_group_with_row_actions(): void {
		$id = $this->category( 'Ui Screen Cat' );
		$this->as_role( 'wiw_editor' );
		$html = ( new CategoriesScreen( CategoryService::for_site() ) )->html();
		$this->assertSame( count( AwardsTables::GROUPS ), substr_count( $html, 'class="wiw-list-form"' ) );
		$this->assertStringContainsString( 'name="ids[]" value="' . $id . '"', $html );
		$this->assertStringContainsString( '>Rename<', $html );
		$this->assertStringContainsString( 'op=archive', $html );
		$this->assertStringContainsString( 'wiw_confirm=remove', $html );
		$this->assertStringContainsString( 'name="op" value="add"', $html, 'each group keeps its Add category form' );

		$rename = ( new CategoriesScreen( CategoryService::for_site() ) )->html( '', new Request( array( 'rename' => (string) $id ) ) );
		$this->assertStringContainsString( 'name="op" value="rename"', $rename );
		$this->assertStringContainsString( 'value="Ui Screen Cat"', $rename );
	}

	public function test_REQ_275_nomination_history_has_no_checkboxes_or_bulk_actions(): void {
		$id = $this->nomination( $this->profile( 'Ui History Zq' ), $this->category() );
		$this->as_role( 'wiw_editor' );
		NominationService::for_site()->set_stage( $id, 'winner' );
		$html = ( new NominationHistoryTable( NominationService::for_site()->history( $id ) ) )->html( new Request( array() ) );
		$this->assertStringContainsString( 'Winner', $html );
		$this->assertStringNotContainsString( 'type="checkbox"', $html );
		$this->assertStringNotContainsString( 'wiw-bulk-select', $html );
		$this->assertStringNotContainsString( 'class="row-actions"', $html );
	}
}
