<?php
/**
 * How sample content is recognised: post meta and term meta `_wiw_sample` = 1 (the same marker the content:find-sample command
 * looks for), a stable key per item so a second run finds what the first one made, and a small registry for the pieces that live
 * outside posts and terms (award categories and nominations, and the settings the seeder changed). REQ-208, REQ-052.
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
					'post_type'      => 'any',
					'post_status'    => get_post_stati(),
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
