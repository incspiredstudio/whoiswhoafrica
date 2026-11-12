<?php
/**
 * The review queue and tiles (REQ-084, REQ-222, REQ-239, REQ-244). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Admin\ReviewQueue;
use WhoIsWho\Core\Review\Admin\ReviewQueueScreen;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class ReviewQueueTest extends WpTestCase {

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

	/**
	 * Make a profile in the imported or pending state.
	 *
	 * @param string               $name   Name.
	 * @param string               $type   Entity type.
	 * @param string               $state  imported, pending or reviewed.
	 * @param array<string, mixed> $values Extra state columns.
	 */
	private function profile( string $name, string $type, string $state, array $values = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, 'imported' === $state ? PubState::IMPORTED_NOT_PUBLIC : PubState::PUBLISHED_PENDING, array( 'entity_type' => $type ) );
		if ( 'reviewed' === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		if ( array() !== $values ) {
			( new StateRepository() )->update( $id, $values );
		}
		return $id;
	}

	/**
	 * Names of the queue items this test made, in queue order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function mine(): array {
		$ids = $this->posts;
		return array_values( array_filter( ( new ReviewQueue() )->items( 1 )['items'], static fn ( array $i ): bool => in_array( $i['profile_id'], $ids, true ) ) );
	}

	public function test_REQ_084_queue_lists_flagged_changes_and_pending_revisions(): void {
		$new     = $this->profile( 'Q New Zxk', 'organisation', 'imported' );
		$claim   = $this->profile( 'Q Claim Zxk', 'individual', 'pending', array( 'claimed' => 1 ) );
		$sub     = $this->profile( 'Q Sub Zxk', 'individual', 'pending', array( 'origin' => 'customer' ) );
		$rev     = $this->profile( 'Q Rev Zxk', 'organisation', 'reviewed', array( 'claimed' => 1 ) );
		$flag    = $this->profile( 'Q Flag Zxk', 'organisation', 'reviewed' );
		$done    = $this->profile( 'Q Done Zxk', 'organisation', 'reviewed' );
		$vers    = new VersionRepository();
		$rev_id  = $vers->append( $rev, 5, 'revision', '', array( 'a' => 1 ), array( 'a' => 2 ), 'pending' );
		$flag_id = $vers->append( $flag, 0, 'import', '', array( 'a' => 1 ), array( 'a' => 3 ), 'pending' );
		$old     = $vers->append( $done, 5, 'revision', '', array( 'a' => 1 ), array( 'a' => 2 ), 'pending' );
		$vers->append( $done, 6, 'decision', '', array(), array(), 'approved', $old );

		$kinds = array();
		foreach ( $this->mine() as $item ) {
			$kinds[ $item['profile_id'] . ':' . $item['version_id'] ] = $item['kind'];
		}
		$this->assertSame( ReviewQueue::KIND_NEW, $kinds[ $new . ':0' ] );
		$this->assertSame( ReviewQueue::KIND_CLAIM, $kinds[ $claim . ':0' ] );
		$this->assertSame( ReviewQueue::KIND_SUBMISSION, $kinds[ $sub . ':0' ] );
		$this->assertSame( ReviewQueue::KIND_REVISION, $kinds[ $rev . ':' . $rev_id ] );
		$this->assertSame( ReviewQueue::KIND_FLAGGED, $kinds[ $flag . ':' . $flag_id ] );
		$this->assertCount( 5, $kinds, 'A reviewed profile with no open revision, and a decided revision, are not in the queue.' );
	}

	public function test_REQ_222_queue_default_order(): void {
		$person = $this->profile( 'Q Person Zxk', 'individual', 'imported' );
		$org    = $this->profile( 'Q Org Zxk', 'organisation', 'imported' );
		$pilot  = $this->profile( 'Q Pilot Zxk', 'individual', 'imported', array( 'tier' => 'T1' ) );
		$claim  = $this->profile( 'Q Claim Zxk', 'individual', 'pending', array( 'claimed' => 1 ) );

		$this->assertSame( array( $claim, $pilot, $org, $person ), array_column( $this->mine(), 'profile_id' ) );
	}

	public function test_REQ_222_queue_marks_pilot_and_assignment(): void {
		$pilot = $this->profile( 'Q Pilot2 Zxk', 'organisation', 'imported', array( 'tier' => 'T1' ) );
		( new ReviewQueue() )->assign( $pilot, 77 );
		$item = $this->mine()[0];
		$this->assertTrue( $item['pilot'] );
		$this->assertSame( 77, $item['assignee'] );
	}

	public function test_REQ_239_review_tiles_show_counts_by_state(): void {
		$before = ( new ReviewQueue() )->tiles();
		$this->profile( 'Tile A Zxk', 'organisation', 'imported' );
		$this->profile( 'Tile B Zxk', 'organisation', 'pending' );
		$this->profile( 'Tile C Zxk', 'organisation', 'reviewed' );
		$after = ( new ReviewQueue() )->tiles();
		$this->assertSame( $before['imported'] + 1, $after['imported'] );
		$this->assertSame( $before['pending'] + 1, $after['pending'] );
		$this->assertSame( $before['reviewed'] + 1, $after['reviewed'] );
		$this->assertSame( $before['published'] + 2, $after['published'] );
		$this->assertSame( array( 'imported', 'pending', 'reviewed', 'published' ), array_keys( $after ), 'No target or capacity figure.' );

		foreach ( array( 'wiw_reviewer', 'wiw_editor', 'administrator' ) as $role ) {
			$user = self::factory()->user->create( array( 'role' => $role ) );
			$this->assertTrue( user_can( $user, ReviewQueueScreen::TILES_CAP ), $role );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) ) );
		ob_start();
		( new ReviewQueueScreen() )->tiles();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'data-tile="pending"', $html );
	}

	public function test_REQ_244_queue_denies_without_capability(): void {
		$registry = new ScreenRegistry();
		ReviewQueueScreen::register( $registry );
		$registry->hook();
		$users               = AuthorisationMatrix::users( self::factory(), 'wiw_review_records', 'wiw_run_imports' );
		$users['subscriber'] = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		AuthorisationMatrix::assert_statuses(
			$this,
			ReviewQueueScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 200,
				'editor'        => 200,
				'administrator' => 200,
				'subscriber'    => 403,
			)
		);
		wp_set_current_user( $users['subscriber'] );
		ob_start();
		( new ReviewQueueScreen() )->tiles();
		$this->assertSame( '', (string) ob_get_clean(), 'No tiles without the dashboard capability.' );
	}
}
