<?php
/**
 * The bulk unpublish interface on the Profiles list (REQ-193, REQ-244). Needs the WordPress test suite.
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
use WhoIsWho\Core\Review\Admin\BulkUnpublishPanel;
use WhoIsWho\Core\Review\Bulk\BulkRepository;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\WpTestCase;

final class BulkUnpublishPanelTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
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
		$this->posts = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function profile( string $name, string $source ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter(
			$id,
			PubState::PUBLISHED_PENDING,
			array(
				'entity_type' => 'organisation',
				'source'      => $source,
			)
		);
		return $id;
	}

	private function render_as( string $role, array $filters ): string {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		ob_start();
		( new BulkUnpublishPanel() )->render( $filters );
		return (string) ob_get_clean();
	}

	public function test_REQ_193_bulk_control_hidden_without_capability(): void {
		$this->profile( 'Panel Qwv One', 'panel-qwv' );
		$this->assertSame( '', $this->render_as( 'wiw_editor', array( 'source' => 'panel-qwv' ) ), 'An Editor without the capability sees nothing.' );
		$this->assertStringContainsString( 'Bulk unpublish', $this->render_as( 'wiw_reviewer', array( 'source' => 'panel-qwv' ) ) );
		$this->assertStringContainsString( 'Bulk unpublish', $this->render_as( 'administrator', array( 'source' => 'panel-qwv' ) ) );
	}

	public function test_REQ_193_confirmation_shows_count_and_first_five_names(): void {
		for ( $i = 1; $i <= 7; $i++ ) {
			$this->profile( 'Panel Qwv ' . $i, 'panel-qwv-list' );
		}
		$html = $this->render_as( 'wiw_reviewer', array( 'source' => 'panel-qwv-list' ) );
		$this->assertStringContainsString( '7 published profiles match the filter.', $html );
		$this->assertStringContainsString( 'unpublish 7 profiles, starting with: Panel Qwv 1, Panel Qwv 2, Panel Qwv 3, Panel Qwv 4, Panel Qwv 5.', $html );
		$this->assertStringNotContainsString( 'Panel Qwv 6,', $html );
		$this->assertStringContainsString( 'name="confirmed" value="7"', $html );
		$this->assertStringContainsString( 'required="required"', $html, 'The reason is mandatory.' );
	}

	public function test_REQ_193_recent_actions_list_offers_restore_this_batch(): void {
		( new BulkRepository() )->create( 'recentqwv0001', array( 'source' => 'x' ), 'Source withdrawn', 3, 1 );
		$html = $this->render_as( 'wiw_reviewer', array() );
		$this->assertStringContainsString( 'Recent bulk actions', $html );
		$this->assertStringContainsString( 'Source withdrawn', $html );
		$this->assertStringNotContainsString( 'batch=recentqwv0001', $html, 'A queued batch has nothing to restore yet.' );
		( new BulkRepository() )->update( 'recentqwv0001', array( 'status' => BulkRepository::DONE ) );
		$this->assertStringContainsString( 'batch=recentqwv0001', $this->render_as( 'wiw_reviewer', array() ) );
		global $wpdb;
		$wpdb->delete( BulkRepository::table(), array( 'batch_ref' => 'recentqwv0001' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function test_REQ_193_handlers_refuse_a_missing_reason_and_a_user_without_the_capability(): void {
		$this->profile( 'Panel Qwv Solo', 'panel-qwv-solo' );
		$reviewer = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		$editor   = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$values   = array(
			'f_source'  => 'panel-qwv-solo',
			'reason'    => '',
			'confirmed' => 1,
		);
		$this->assertSame( 'refused', BulkUnpublishPanel::start( new Request( $values ), $reviewer ), 'No reason.' );
		$values['reason'] = 'Because';
		$this->assertSame( 'refused', BulkUnpublishPanel::start( new Request( $values ), $editor ), 'No capability.' );
		$values['confirmed'] = 9;
		$this->assertSame( 'refused', BulkUnpublishPanel::start( new Request( $values ), $reviewer ), 'The count changed.' );
		$this->assertSame( 'refused', BulkUnpublishPanel::restore( new Request( array( 'batch' => 'nope' ) ), $reviewer ) );
	}
}
