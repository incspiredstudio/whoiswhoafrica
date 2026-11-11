<?php
/**
 * The story type: a fixed list of six values, kept as terms of one taxonomy on core posts (REQ-027).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

/**
 * Story types.
 */
final class StoryTypes {

	public const TAXONOMY = 'wiw_story_type';

	public const TYPES = array( 'interview', 'feature', 'spotlight', 'opinion', 'announcement', 'news' );

	/**
	 * Register the taxonomy on core posts.
	 */
	public static function register(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( 'post' ),
			array(
				'labels'             => array(
					'name'          => __( 'Story types', 'whoiswho' ),
					'singular_name' => __( 'Story type', 'whoiswho' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'hierarchical'       => false,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				'meta_box_cb'        => false,
				'rewrite'            => array( 'slug' => 'stories/type' ),
				'capabilities'       => array(
					'manage_terms' => 'do_not_allow',
					'edit_terms'   => 'do_not_allow',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Make sure the six terms exist.
	 */
	public static function ensure_terms(): void {
		foreach ( self::TYPES as $slug ) {
			if ( ! term_exists( $slug, self::TAXONOMY ) ) {
				wp_insert_term( ucfirst( $slug ), self::TAXONOMY, array( 'slug' => $slug ) );
			}
		}
	}

	/**
	 * Whether a value is one of the six types.
	 *
	 * @param string $slug Type slug.
	 */
	public static function is_valid( string $slug ): bool {
		return in_array( $slug, self::TYPES, true );
	}

	/**
	 * The story's type slug, or an empty string when it has none or an unknown one.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function of( int $post_id ): string {
		$terms = wp_get_object_terms( $post_id, self::TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( ! is_array( $terms ) ) {
			return '';
		}
		foreach ( $terms as $slug ) {
			if ( self::is_valid( (string) $slug ) ) {
				return (string) $slug;
			}
		}
		return '';
	}
}
