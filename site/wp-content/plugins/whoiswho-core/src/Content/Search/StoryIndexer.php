<?php
/**
 * Keeps story rows in the search table in step with publication (REQ-254, REQ-208, REQ-027, REQ-182). A row exists only while
 * the story is public: published, not password-protected, read through PublicStory. Unpublishing, scheduling, trashing or
 * deleting removes the row in the same request, so there is no delay on the origin. Events and every other post type are
 * never indexed. A sponsored story keeps its flag so a result can show the label.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Search;

use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Directory\Search\SearchIndexer;

/**
 * Story indexer.
 */
final class StoryIndexer {

	public const ITEM_TYPE = 'story';

	/**
	 * Listen to post changes.
	 */
	public function register(): void {
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'wp_after_insert_post', array( $this, 'on_after_insert' ), 20, 2 );
		add_action( 'before_delete_post', array( $this, 'on_delete' ) );
	}

	/**
	 * A status change: add the row on publish, remove it on anything else.
	 *
	 * @param string   $to   New status.
	 * @param string   $from Old status.
	 * @param \WP_Post $post Post.
	 */
	public function on_transition( string $to, string $from, \WP_Post $post ): void {
		if ( 'post' !== $post->post_type || $to === $from ) {
			return;
		}
		$this->upsert( $post->ID );
	}

	/**
	 * After a save (terms and fields are stored by now): refresh the row.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public function on_after_insert( int $post_id, \WP_Post $post ): void {
		if ( 'post' === $post->post_type ) {
			$this->upsert( $post_id );
		}
	}

	/**
	 * A story is being deleted.
	 *
	 * @param int $post_id Post id.
	 */
	public function on_delete( int $post_id ): void {
		if ( 'post' === get_post_type( $post_id ) ) {
			$this->remove( $post_id );
		}
	}

	/**
	 * Add or refresh a story row; the row is removed instead unless the read model says the story is public.
	 *
	 * @param int $post_id Post id.
	 * @return bool True when a row is present afterwards.
	 */
	public function upsert( int $post_id ): bool {
		global $wpdb;
		if ( 'post' !== get_post_type( $post_id ) ) {
			return false;
		}
		clean_post_cache( $post_id );
		$story = PublicStory::find( $post_id );
		if ( null === $story ) {
			$this->remove( $post_id );
			return false;
		}
		$ok = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'REPLACE INTO ' . SearchIndexer::table() . ' (item_type, item_id, entity_type, name, organisation, title, industry, bio, url, pending, name_sort, sponsored) VALUES (%s, %d, %s, %s, %s, %s, %s, %s, %s, %d, %s, %d)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
				self::ITEM_TYPE,
				$post_id,
				(string) $story['type'],
				(string) $story['title'],
				'',
				'',
				implode( ', ', array_map( 'strval', (array) $story['industries'] ) ),
				trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $story['lede'] . ' ' . (string) $story['body'] ) ) ),
				(string) $story['url'],
				0,
				strtolower( remove_accents( (string) $story['title'] ) ),
				null !== $story['sponsor'] ? 1 : 0
			)
		);
		return false !== $ok;
	}

	/**
	 * Remove a story's row.
	 *
	 * @param int $post_id Post id.
	 */
	public function remove( int $post_id ): void {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SearchIndexer::table(),
			array(
				'item_type' => self::ITEM_TYPE,
				'item_id'   => $post_id,
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * Rebuild the story rows: every published story is upserted and every other story row is dropped.
	 *
	 * @return int Story rows present afterwards.
	 */
	public function rebuild(): int {
		global $wpdb;
		$keep = array();
		foreach ( get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		) as $id ) {
			if ( $this->upsert( (int) $id ) ) {
				$keep[ (int) $id ] = true;
			}
		}
		$present = $wpdb->get_col( $wpdb->prepare( 'SELECT item_id FROM ' . SearchIndexer::table() . ' WHERE item_type = %s', self::ITEM_TYPE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		foreach ( (array) $present as $id ) {
			if ( ! isset( $keep[ (int) $id ] ) ) {
				$this->remove( (int) $id );
			}
		}
		return count( $keep );
	}
}
