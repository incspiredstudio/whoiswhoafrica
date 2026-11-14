<?php
/**
 * How sample content is recognised: post meta and term meta `_wiw_sample` = 1 (the same marker the content:find-sample command
 * looks for), a stable key per item so a second run finds what the first one made, and a small registry for the pieces that live
 * outside posts and terms (award categories and nominations, and the settings the seeder changed). Demo comments carry the same
 * markers as comment meta. A finished demo post or comment also keeps a fingerprint, so removal can tell an item a person edited
 * and keep it. REQ-208, REQ-052.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli\Sample;

/**
 * Sample marker.
 */
final class SampleMarker {

	public const META     = '_wiw_sample';
	public const KEY_META = '_wiw_sample_key';
	public const REGISTRY = 'wiw_sample_registry';

	public const HASH_META  = '_wiw_demo_hash';
	public const STAMP_META = '_wiw_demo_stamp';

	/**
	 * Mark a post as sample content under a key.
	 *
	 * @param int    $post_id Post.
	 * @param string $key     Stable key.
	 */
	public static function mark_post( int $post_id, string $key ): void {
		update_post_meta( $post_id, self::META, '1' );
		update_post_meta( $post_id, self::KEY_META, $key );
	}

	/**
	 * Remember how a finished demo post looks, so a later edit by a person can be told apart (REQ-208): a fingerprint of its text
	 * and the time it was last changed.
	 *
	 * @param int $post_id Post.
	 */
	public static function seal_post( int $post_id ): void {
		clean_post_cache( $post_id );
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		update_post_meta( $post_id, self::HASH_META, self::fingerprint( $post ) );
		update_post_meta( $post_id, self::STAMP_META, $post->post_modified_gmt );
	}

	/**
	 * A fingerprint of a post's text.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function fingerprint( \WP_Post $post ): string {
		return md5( $post->post_title . "\n" . $post->post_content . "\n" . $post->post_excerpt . "\n" . $post->post_name );
	}

	/**
	 * Mark a comment as sample content under a key, with a fingerprint of its text.
	 *
	 * @param int    $comment_id Comment.
	 * @param string $key        Stable key.
	 */
	public static function mark_comment( int $comment_id, string $key ): void {
		update_comment_meta( $comment_id, self::META, '1' );
		update_comment_meta( $comment_id, self::KEY_META, $key );
		$comment = get_comment( $comment_id );
		if ( $comment instanceof \WP_Comment ) {
			update_comment_meta( $comment_id, self::HASH_META, self::comment_fingerprint( $comment ) );
		}
	}

	/**
	 * A fingerprint of a comment's text and author name.
	 *
	 * @param \WP_Comment $comment Comment.
	 */
	public static function comment_fingerprint( \WP_Comment $comment ): string {
		return md5( $comment->comment_content . "\n" . $comment->comment_author );
	}

	/**
	 * The sample comment a key made, or null.
	 *
	 * @param string $key Stable key.
	 */
	public static function find_comment( string $key ): ?int {
		$found = get_comments(
			array(
				'status'     => 'all',
				'number'     => 1,
				'fields'     => 'ids',
				'meta_key'   => self::KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a one-off setup lookup.
				'meta_value' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
			)
		);
		return is_array( $found ) && array() !== $found ? (int) $found[0] : null;
	}

	/**
	 * Take the marker off a post a person has made their own, so it is no longer treated as demo content.
	 *
	 * @param int $post_id Post.
	 */
	public static function unmark_post( int $post_id ): void {
		foreach ( array( self::META, self::KEY_META, self::HASH_META, self::STAMP_META ) as $key ) {
			delete_post_meta( $post_id, $key );
		}
	}

	/**
	 * Take the marker off a comment a person has changed.
	 *
	 * @param int $comment_id Comment.
	 */
	public static function unmark_comment( int $comment_id ): void {
		foreach ( array( self::META, self::KEY_META, self::HASH_META ) as $key ) {
			delete_comment_meta( $comment_id, $key );
		}
	}

	/**
	 * Mark a term as sample content under a key.
	 *
	 * @param int    $term_id Term.
	 * @param string $key     Stable key.
	 */
	public static function mark_term( int $term_id, string $key ): void {
		update_term_meta( $term_id, self::META, '1' );
		update_term_meta( $term_id, self::KEY_META, $key );
	}

	/**
	 * The post a key made, in any status, or null.
	 *
	 * @param string $key  Stable key.
	 * @param string $type Post type.
	 */
	public static function find_post( string $key, string $type ): ?int {
		$found = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => get_post_stati(),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a one-off command line lookup.
				'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
			)
		);
		return array() === $found ? null : (int) $found[0];
	}

	/**
	 * The term a key made, or null.
	 *
	 * @param string $key      Stable key.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function find_term( string $key, string $taxonomy ): ?int {
		$found = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
				'meta_key'   => self::KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a one-off command line lookup.
				'meta_value' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
			)
		);
		return is_array( $found ) && array() !== $found ? (int) $found[0] : null;
	}

	/**
	 * Every post marked as sample content.
	 *
	 * @return int[]
	 */
	public static function all_post_ids(): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => array_values( get_post_types() ),
					// 'any' would skip types hidden from search, such as profiles and events.
																			'post_status' => get_post_stati(),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a one-off command line lookup.
					'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
				)
			)
		);
	}

	/**
	 * Every term marked as sample content, as term id => taxonomy.
	 *
	 * @param string[] $taxonomies Taxonomies to look in.
	 * @return array<int, string>
	 */
	public static function all_terms( array $taxonomies ): array {
		$out = array();
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$ids = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
					'meta_key'   => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a one-off command line lookup.
					'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
				)
			);
			foreach ( is_array( $ids ) ? $ids : array() as $id ) {
				$out[ (int) $id ] = $taxonomy;
			}
		}
		return $out;
	}

	/**
	 * The registry of what lives outside posts and terms.
	 *
	 * @return array<string, mixed>
	 */
	public static function registry(): array {
		$stored = get_option( self::REGISTRY, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Save the registry.
	 *
	 * @param array<string, mixed> $registry Registry.
	 */
	public static function save_registry( array $registry ): void {
		update_option( self::REGISTRY, $registry, false );
	}
}
