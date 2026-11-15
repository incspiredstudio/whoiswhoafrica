<?php
/**
 * Bulk Unpublish selected and the row actions on the Profiles list (REQ-275, REQ-209, REQ-091). The bulk action repeats the
 * single Unpublish through the same review service, confirmed first, with one audit row per profile; a profile that is not public
 * is skipped. Row actions show only what the user may do. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Admin\ProfilesTable;
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
use WhoIsWho\Tests\TestCase;

final class ProfilesBulkUnpublishTest extends TestCase {

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

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'pbulk' . wp_generate_password( 6, false ),
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

	private function profile( string $name, string $state ): int {
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
			$state,
			array(
				'entity_type' => 'organisation',
				'origin'      => 'manual',
				'source'      => 'pbulk-qzx',
			)
		);
		return $id;
	}

	/**
	 * State-change audit rows for a profile.
	 *
	 * @param int $id Profile.
	 * @return array<int, array<string, mixed>>
	 */
	private function audit( int $id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT actor_id, reason FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'profile.state_change' AND subject_id = %s", (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	public function test_REQ_275_bulk_unpublish_goes_through_the_review_service_with_audit_per_item(): void {
		$actor   = $this->user( 'wiw_editor' );
		$live_a  = $this->profile( 'Pbulk Live Alpha Qzx', PubState::PUBLISHED_PENDING );
		$live_b  = $this->profile( 'Pbulk Live Beta Qzx', PubState::PUBLISHED_PENDING );
		$private = $this->profile( 'Pbulk Private Qzx', PubState::IMPORTED_NOT_PUBLIC );
		$before  = array_map( fn ( int $id ): int => count( $this->audit( $id ) ), array( $live_a, $live_b, $private ) );
		$values  = array(
			'_wpnonce'              => ActionGuard::nonce_for( ProfilesTable::BULK ),
			ListTable::FIELD_ACTION => 'unpublish',
			ListTable::FIELD_IDS    => array( $live_a, $live_b, $private ),
		);
		$table   = new ProfilesTable();
		$this->assertSame( ListTable::NEEDS_CONFIRM, $table->process_bulk( new Request( $values ) )['status'], 'destructive: confirmed first' );
		$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->get( $live_a )['pub_state'] );

		$result = $table->process_bulk( new Request( array_merge( $values, array( ListTable::FIELD_CONFIRM => '1' ) ) ) );
		$this->assertSame( 2, $result['done'] );
		$this->assertSame( 1, $result['skipped'], 'a profile that is not public is skipped' );
		$this->assertSame( '2 unpublished, 1 skipped (Only a published profile can be unpublished.).', $table->notice_for( $result )['text'] );
		foreach ( array( $live_a, $live_b ) as $i => $id ) {
			$this->assertSame( PubState::UNPUBLISHED, ( new StateRepository() )->get( $id )['pub_state'] );
			$rows = $this->audit( $id );
			$this->assertCount( $before[ $i ] + 1, $rows, 'one audit row per profile' );
			$this->assertSame( $actor, (int) end( $rows )['actor_id'] );
			$this->assertSame( 'Unpublished from the Profiles list', end( $rows )['reason'], 'the same reason as the single row action' );
		}
		$this->assertCount( $before[2], $this->audit( $private ) );
	}

	public function test_REQ_275_reviewer_without_unpublish_sees_no_bulk_and_no_unpublish_row_action(): void {
		$this->user( 'wiw_reviewer' );
		$this->profile( 'Pbulk Row Qzx', PubState::PUBLISHED_PENDING );
		$html = ( new ProfilesTable( array( 'source' => 'pbulk-qzx' ) ) )->html( new Request( array() ) );
		$this->assertStringContainsString( 'Pbulk Row Qzx', $html );
		$this->assertStringNotContainsString( 'Unpublish selected', $html );
		$this->assertStringNotContainsString( 'action=wiw_unpublish_profile', $html );
		$this->assertStringNotContainsString( '>Edit<', $html, 'a reviewer cannot edit fields' );
		$this->assertStringContainsString( '>View<', $html );

		$this->user( 'wiw_editor' );
		$html = ( new ProfilesTable( array( 'source' => 'pbulk-qzx' ) ) )->html( new Request( array() ) );
		$this->assertStringContainsString( 'Unpublish selected', $html );
		$this->assertStringContainsString( 'data-wiw-destructive="1"', $html );
		$this->assertStringContainsString( '>Edit<', $html );
		$this->assertStringContainsString( 'source=pbulk-qzx', $html, 'the filters come back after a bulk action' );
	}
}
