<?php
/**
 * The profile search index (REQ-254, REQ-208, REQ-216, REQ-079). Needs the WordPress test suite and the real state tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Search;

use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Search\SearchIndexer;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Log\LogSink;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class SearchIndexTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( SearchIndexer::table(), array( 'item_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		parent::tearDown();
	}

	/**
	 * Create a profile in a state.
	 *
	 * @param string $name  Name.
	 * @param string $state Target state.
	 */
	private function profile( string $name, string $state ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$start         = in_array( $state, array( PubState::IMPORTED_NOT_PUBLIC, PubState::REJECTED ), true ) ? PubState::IMPORTED_NOT_PUBLIC : PubState::PUBLISHED_PENDING;
		$machine->enter( $id, $start, array( 'entity_type' => 'individual' ) );
		$store = new ProfileFields();
		$store->set( $id, 'current_title', 'Chief Fixture Officer' );
		( new ProvenanceRepository() )->record(
			$id,
			array(
				'field'        => 'current_title',
				'source_url'   => 'https://example.org/src',
				'retrieved_at' => '2026-11-10T09:00:00Z',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
		wp_update_post( array( 'ID' => $id ) );
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		} elseif ( PubState::REJECTED === $state ) {
			$machine->transition( $id, PubState::REJECTED, 'test' );
		} elseif ( PubState::UNPUBLISHED === $state ) {
			$machine->transition( $id, PubState::UNPUBLISHED, 'test' );
		} elseif ( PubState::REMOVED_SUPPRESSED === $state ) {
			$machine->transition( $id, PubState::REMOVED_SUPPRESSED, 'test' );
		}
		return $id;
	}

	/**
	 * Indexed item ids among ours.
	 *
	 * @return int[]
	 */
	private function indexed(): array {
		global $wpdb;
		$ids = array_map( 'intval', (array) $wpdb->get_col( 'SELECT item_id FROM ' . SearchIndexer::table() . ' ORDER BY item_id' ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		return array_values( array_intersect( $ids, $this->posts ) );
	}

	public function test_REQ_254_index_never_contains_non_public_profiles(): void {
		$rows = array();
		foreach ( PubState::all() as $state ) {
			$rows[ $state ] = $this->profile( 'Search ' . $state, $state );
		}
		$expected = array( $rows[ PubState::PUBLISHED_REVIEWED ], $rows[ PubState::PUBLISHED_PENDING ] );
		sort( $expected );
		$this->assertSame( $expected, $this->indexed() );
	}

	public function test_REQ_208_unpublish_removes_profile_from_index_in_same_step(): void {
		$id = $this->profile( 'Search Unpublish', PubState::PUBLISHED_REVIEWED );
		$this->assertSame( array( $id ), $this->indexed() );
		( new StateMachine() )->transition( $id, PubState::UNPUBLISHED, 'test' );
		$this->assertSame( array(), $this->indexed() );
	}

	public function test_REQ_208_bulk_unpublish_and_suppress_remove_index_rows(): void {
		$a = $this->profile( 'Search Bulk', PubState::PUBLISHED_REVIEWED );
		$b = $this->profile( 'Search Suppress', PubState::PUBLISHED_REVIEWED );
		$this->assertSame( array( $a, $b ), $this->indexed() );
		( new StateMachine() )->transition( $a, PubState::UNPUBLISHED, 'Bulk reason', null, 'batch-1' );
		( new StateMachine() )->transition( $b, PubState::REMOVED_SUPPRESSED, 'Suppression' );
		$this->assertSame( array(), $this->indexed() );
	}

	public function test_REQ_216_pending_review_profile_is_indexed_with_pending_flag(): void {
		global $wpdb;
		$pending  = $this->profile( 'Search Pending', PubState::PUBLISHED_PENDING );
		$reviewed = $this->profile( 'Search Reviewed', PubState::PUBLISHED_REVIEWED );
		$flags    = $wpdb->get_results( 'SELECT item_id, pending, title FROM ' . SearchIndexer::table() . ' WHERE item_id IN (' . $pending . ',' . $reviewed . ')', OBJECT_K ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame( '1', $flags[ $pending ]->pending );
		$this->assertSame( '0', $flags[ $reviewed ]->pending );
		$this->assertSame( 'Chief Fixture Officer', $flags[ $pending ]->title );
	}

	public function test_REQ_254_rebuild_matches_incremental_index(): void {
		global $wpdb;
		$a = $this->profile( 'Search Rebuild A', PubState::PUBLISHED_REVIEWED );
		$this->profile( 'Search Rebuild B', PubState::UNPUBLISHED );
		$c      = $this->profile( 'Search Rebuild C', PubState::PUBLISHED_PENDING );
		$fetch  = static fn (): array => (array) $wpdb->get_results( 'SELECT * FROM ' . SearchIndexer::table() . ' WHERE item_id IN (' . implode( ',', array( $a, $c ) ) . ') ORDER BY item_id', ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$before = $fetch();
		$wpdb->query( 'DELETE FROM ' . SearchIndexer::table() . ' WHERE item_id = ' . $a ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		( new SearchIndexer() )->rebuild();
		$this->assertSame( $before, $fetch() );
		$this->assertSame( array( $a, $c ), $this->indexed() );
	}

	public function test_REQ_079_removal_is_recorded_in_the_purge_log(): void {
		$lines = array();
		$sink  = new class( $lines ) implements LogSink {
			/** @var string[] */
			private array $lines;

			/** @param string[] $lines Lines. */
			public function __construct( array &$lines ) {
				$this->lines = &$lines;
			}

			/** @param string $line Line. */
			public function write( string $line ): void {
				$this->lines[] = $line;
			}
		};
		$id    = $this->profile( 'Search Logged', PubState::PUBLISHED_REVIEWED );
		$index = new SearchIndexer( new Logger( $sink, new PiiCatalogue() ) );
		$this->assertTrue( $index->remove( $id ) );
		$this->assertFalse( $index->remove( $id ), 'A second removal drops nothing and logs nothing.' );
		$this->assertCount( 1, $lines );
		$this->assertStringContainsString( 'search_removed', $lines[0] );
	}
}
