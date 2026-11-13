<?php
/**
 * Stories in the search index (REQ-254, REQ-208, REQ-027, REQ-262, REQ-182). Needs the WordPress test suite and the real table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Search;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Search\StoryIndexer;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryFlags;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Core\Directory\Search\SearchIndexer;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class StorySearchIndexTest extends TestCase {

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
		StoryTypes::register();
		StoryTypes::ensure_terms();
		StoryFields::register_meta();
		EventPostType::register();
		( new StoryIndexer() )->register();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( SearchIndexer::table(), array( 'item_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		parent::tearDown();
	}

	/**
	 * Create a post.
	 *
	 * @param array<string, mixed> $args Overrides.
	 */
	private function story( array $args = array() ): int {
		$id            = (int) wp_insert_post(
			array_merge(
				array(
					'post_title'   => 'Searchable Story',
					'post_content' => 'Body text about harbour logistics.',
					'post_status'  => 'publish',
				),
				$args
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * The story row, or null.
	 *
	 * @param int $id Post id.
	 * @return array<string, mixed>|null
	 */
	private function row( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . SearchIndexer::table() . ' WHERE item_type = %s AND item_id = %d', StoryIndexer::ITEM_TYPE, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	public function test_REQ_254_published_story_is_indexed(): void {
		$id  = $this->story( array( 'post_title' => 'Quayside Pioneers' ) );
		$row = $this->row( $id );
		$this->assertNotNull( $row );
		$this->assertSame( 'Quayside Pioneers', $row['name'] );
		$this->assertStringContainsString( 'harbour logistics', (string) $row['bio'] );
		$this->assertSame( (string) get_permalink( $id ), $row['url'] );
		$this->assertSame( 'story', $row['item_type'] );
	}

	public function test_REQ_254_draft_scheduled_and_trashed_stories_are_not_indexed(): void {
		$draft = $this->story( array( 'post_status' => 'draft' ) );
		$sched = $this->story(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + 86400 ),
			)
		);
		$this->assertNull( $this->row( $draft ) );
		$this->assertNull( $this->row( $sched ) );

		$live = $this->story();
		$this->assertNotNull( $this->row( $live ) );
		wp_update_post(
			array(
				'ID'          => $live,
				'post_status' => 'draft',
			)
		);
		$this->assertNull( $this->row( $live ), 'unpublishing removes the row at once' );

		wp_update_post(
			array(
				'ID'          => $live,
				'post_status' => 'publish',
			)
		);
		$this->assertNotNull( $this->row( $live ) );
		wp_trash_post( $live );
		$this->assertNull( $this->row( $live ), 'trashing removes the row' );

		wp_untrash_post( $live );
		wp_publish_post( $live );
		$this->assertNotNull( $this->row( $live ) );
		wp_delete_post( $live, true );
		$this->assertNull( $this->row( $live ), 'deleting removes the row' );
	}

	public function test_REQ_254_password_protected_story_is_not_indexed(): void {
		$id = $this->story( array( 'post_password' => 'secret' ) );
		$this->assertNull( $this->row( $id ) );
	}

	public function test_REQ_254_editing_a_published_story_refreshes_the_row(): void {
		$id = $this->story();
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Renamed Story',
			)
		);
		$this->assertSame( 'Renamed Story', $this->row( $id )['name'] ?? '' );
	}

	public function test_REQ_262_events_are_not_in_search(): void {
		$id = $this->story(
			array(
				'post_type'  => EventPostType::TYPE,
				'post_title' => 'Annual Summit',
			)
		);
		$this->assertNull( $this->row( $id ) );
		$this->assertSame( 0, ( new StoryIndexer() )->upsert( $id ) ? 1 : 0 );
	}

	public function test_REQ_182_indexed_story_carries_sponsored_flag(): void {
		$plain = $this->story();
		$this->assertSame( '0', (string) $this->row( $plain )['sponsored'] );

		$sponsored = $this->story();
		foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $filter ) {
			remove_filter( $filter, array( StoryFlags::class, 'guard_meta' ), 1 ); // Only the fields are needed here; StoryFlagsTest covers the guard.
		}
		update_post_meta( $sponsored, 'wiw_sponsor_name', 'Acme Freight' );
		update_post_meta( $sponsored, 'wiw_sponsor_placement', 'both' );
		( new StoryIndexer() )->upsert( $sponsored );
		$this->assertSame( '1', (string) $this->row( $sponsored )['sponsored'] );
	}

	public function test_REQ_254_rebuild_covers_stories_and_drops_stale_rows(): void {
		global $wpdb;
		$live = $this->story();
		$wpdb->delete( SearchIndexer::table(), array( 'item_id' => $live ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$draft = $this->story( array( 'post_status' => 'draft' ) );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SearchIndexer::table(),
			array(
				'item_type' => StoryIndexer::ITEM_TYPE,
				'item_id'   => $draft,
				'name'      => 'Stale',
				'bio'       => '',
			)
		);
		( new StoryIndexer() )->rebuild();
		$this->assertNotNull( $this->row( $live ) );
		$this->assertNull( $this->row( $draft ) );
	}
}
