<?php
/**
 * The only way public code reads stories (REQ-027, REQ-029, REQ-257, REQ-208, REQ-265). Published stories only, an allowlisted
 * set of fields, linked profiles that are public, and a related event only while it is published. Draft, scheduled, private,
 * password-protected and trashed stories return nothing to a visitor; staff may ask for a preview.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Stories\LinkedProfiles;
use WhoIsWho\Core\Content\Stories\MetaSponsoredSource;
use WhoIsWho\Core\Content\Stories\ReadingTime;
use WhoIsWho\Core\Content\Stories\StoryTypes;

/**
 * Public story read model.
 */
final class PublicStory {

	public const INDUSTRY_TAXONOMY = 'wiw_industry';
	public const PER_PAGE          = 12;

	/**
	 * One story, or null when it is not public.
	 *
	 * @param int|string $key     Post ID or slug.
	 * @param bool       $preview Ask for a staff preview of an unpublished story; ignored unless the user can edit it.
	 * @return array<string, mixed>|null
	 */
	public static function find( $key, bool $preview = false ): ?array {
		$post = is_int( $key ) ? get_post( $key ) : self::by_slug( (string) $key );
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
			return null;
		}
		$is_preview = false;
		if ( ! self::is_public( $post ) ) {
			if ( ! $preview || 'trash' === $post->post_status || ! current_user_can( 'edit_post', $post->ID ) ) {
				return null;
			}
			$is_preview = true;
		}
		$story             = self::card( $post );
		$story['body']     = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
		$story['preview']  = $is_preview;
		$story['profiles'] = self::linked_profiles( $post->ID );
		$story['event']    = self::related_event( $post->ID );
		$story['sponsor']  = self::sponsor( $post->ID );
		$story['share']    = array(
			'title'       => $story['title'],
			'description' => $story['lede'],
			'image'       => $story['cover'],
			'label'       => null !== $story['sponsor'] ? $story['sponsor']['label'] : null,
		);
		$story['seo']      = array(
			'title'       => (string) get_post_meta( $post->ID, 'wiw_seo_title', true ),
			'description' => (string) get_post_meta( $post->ID, 'wiw_seo_description', true ),
		);
		$others            = self::list(
			array(
				'per_page' => 4,
				'exclude'  => $post->ID,
			)
		);
		$story['others']   = array_slice( $others['items'], 0, 3 );
		return $story;
	}

	/**
	 * A list of stories: newest first, Featured (sticky) first. Placements never change the order.
	 *
	 * The slot story for the surface (home or stories) comes back separately in `slot`; it never moves other items.
	 *
	 * @param array{type?: string, industry?: string, page?: int, per_page?: int, exclude?: int, surface?: string} $args Filters.
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int, page: int, error: bool, slot: array<string, mixed>|null}
	 */
	public static function list( array $args ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 50, (int) ( $args['per_page'] ?? self::PER_PAGE ) ) );
		$type     = (string) ( $args['type'] ?? '' );
		if ( '' !== $type && ! StoryTypes::is_valid( $type ) ) {
			return array(
				'items' => array(),
				'total' => 0,
				'pages' => 0,
				'page'  => $page,
				'error' => true,
				'slot'  => null,
			);
		}
		$ids    = self::ids( $type, (string) ( $args['industry'] ?? '' ), (int) ( $args['exclude'] ?? 0 ) );
		$sticky = array_map( 'intval', (array) get_option( 'sticky_posts', array() ) );
		$first  = array_values( array_intersect( $ids, $sticky ) );
		$rest   = array_values( array_diff( $ids, $sticky ) );
		$ids    = array_merge( $first, $rest );
		$total  = count( $ids );
		$items  = array();
		foreach ( array_slice( $ids, ( $page - 1 ) * $per_page, $per_page ) as $id ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post ) {
				$items[] = self::card( $post );
			}
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
			'page'  => $page,
			'error' => false,
			'slot'  => self::slot( (string) ( $args['surface'] ?? '' ) ),
		);
	}

	/**
	 * The sponsored source: Commerce supplies one through the filter; the default reads the story's sponsor fields.
	 */
	public static function source(): SponsoredSource {
		$source = apply_filters( 'wiw_sponsored_source', new MetaSponsoredSource() );
		return $source instanceof SponsoredSource ? $source : new MetaSponsoredSource();
	}

	/**
	 * Today's date (Y-m-d, site time) for placement windows.
	 */
	private static function today(): string {
		$today = apply_filters( 'wiw_story_today', wp_date( 'Y-m-d' ) );
		return is_string( $today ) ? $today : wp_date( 'Y-m-d' );
	}

	/**
	 * The one sponsored story filling the slot on a surface today: a public story (never an event) whose placement covers the
	 * surface and whose dates include today. Newest story wins a tie. Null when none.
	 *
	 * @param string $surface home or stories.
	 * @return array<string, mixed>|null
	 */
	private static function slot( string $surface ): ?array {
		if ( ! in_array( $surface, array( 'home', 'stories' ), true ) ) {
			return null;
		}
		$ids = self::source()->story_ids( $surface );
		rsort( $ids );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post && 'post' === $post->post_type && self::is_public( $post ) && null !== self::sponsor( $id, $surface ) ) {
				return self::card( $post );
			}
		}
		return null;
	}

	/**
	 * Published stories that link a public profile.
	 *
	 * @param int $profile_id Profile ID.
	 * @param int $limit      Maximum stories, newest first; 0 for all.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_profile( int $profile_id, int $limit = 0 ): array {
		if ( ! isset( LinkedProfiles::choices()[ $profile_id ] ) ) {
			return array();
		}
		$out = array();
		foreach ( self::ids( '', '', 0 ) as $id ) {
			$linked = get_post_meta( $id, LinkedProfiles::META, true );
			if ( is_array( $linked ) && in_array( $profile_id, array_map( 'intval', $linked ), true ) ) {
				$post = get_post( $id );
				if ( $post instanceof \WP_Post ) {
					$out[] = self::card( $post );
					if ( $limit > 0 && count( $out ) >= $limit ) {
						break;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Published stories in an industry.
	 *
	 * @param string $industry Industry term slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_industry( string $industry ): array {
		return self::list(
			array(
				'industry' => $industry,
				'per_page' => 50,
			)
		)['items'];
	}

	/**
	 * Ids of public stories, newest first.
	 *
	 * @param string $type     Story type slug or empty.
	 * @param string $industry Industry slug or empty.
	 * @param int    $exclude  A story to leave out.
	 * @return array<int, int>
	 */
	private static function ids( string $type, string $industry, int $exclude ): array {
		$tax = array();
		if ( '' !== $type ) {
			$tax[] = array(
				'taxonomy' => StoryTypes::TAXONOMY,
				'field'    => 'slug',
				'terms'    => array( $type ),
			);
		}
		if ( '' !== $industry ) {
			if ( ! taxonomy_exists( self::INDUSTRY_TAXONOMY ) ) {
				return array();
			}
			$tax[] = array(
				'taxonomy' => self::INDUSTRY_TAXONOMY,
				'field'    => 'slug',
				'terms'    => array( $industry ),
			);
		}
		$query = new \WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'has_password'        => false,
				'fields'              => 'ids',
				'nopaging'            => true,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'orderby'             => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'post__not_in'        => $exclude > 0 ? array( $exclude ) : array(),
				'tax_query'           => $tax, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- small, filtered list.
			)
		);
		return array_map( 'intval', $query->posts );
	}

	/**
	 * A story by slug.
	 *
	 * @param string $slug Slug.
	 */
	private static function by_slug( string $slug ): ?\WP_Post {
		$found = get_posts(
			array(
				'name'           => sanitize_title( $slug ),
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'future', 'pending' ),
				'posts_per_page' => 1,
			)
		);
		return $found[0] ?? null;
	}

	/**
	 * Whether a visitor may see the story.
	 *
	 * @param \WP_Post $post Post.
	 */
	private static function is_public( \WP_Post $post ): bool {
		return 'publish' === $post->post_status && '' === $post->post_password;
	}

	/**
	 * The fields a list card and the story share.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	private static function card( \WP_Post $post ): array {
		$industries = wp_get_object_terms( $post->ID, self::INDUSTRY_TAXONOMY, array( 'fields' => 'names' ) );
		$author     = get_the_author_meta( 'display_name', (int) $post->post_author );
		$cover      = get_the_post_thumbnail_url( $post->ID, 'large' );
		return array(
			'id'              => $post->ID,
			'slug'            => $post->post_name,
			'url'             => (string) get_permalink( $post ),
			'title'           => get_the_title( $post ),
			'lede'            => wp_strip_all_tags( $post->post_excerpt ),
			'date'            => get_post_time( 'c', true, $post ),
			'author'          => (string) $author,
			'type'            => StoryTypes::of( $post->ID ),
			'industries'      => is_array( $industries ) ? array_values( $industries ) : array(),
			'cover'           => is_string( $cover ) ? $cover : '',
			'reading_minutes' => ReadingTime::minutes( $post->post_content ),
			'featured'        => is_sticky( $post->ID ),
			'sponsor'         => self::sponsor( $post->ID ),
			'preview'         => false,
		);
	}

	/**
	 * Linked profiles, keeping only public ones.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array{id: int, name: string}>
	 */
	private static function linked_profiles( int $post_id ): array {
		$linked = get_post_meta( $post_id, LinkedProfiles::META, true );
		if ( ! is_array( $linked ) ) {
			return array();
		}
		$public = LinkedProfiles::choices();
		$out    = array();
		foreach ( array_map( 'intval', $linked ) as $id ) {
			if ( isset( $public[ $id ] ) ) {
				$out[] = array(
					'id'   => $id,
					'name' => $public[ $id ],
				);
			}
		}
		return $out;
	}

	/**
	 * The related event, only while it is published.
	 *
	 * @param int $post_id Post ID.
	 * @return array{id: int, title: string, url: string}|null
	 */
	private static function related_event( int $post_id ): ?array {
		$event_id = (int) get_post_meta( $post_id, 'related_event_id', true );
		if ( $event_id <= 0 ) {
			return null;
		}
		$event = get_post( $event_id );
		if ( ! $event instanceof \WP_Post || EventPostType::TYPE !== $event->post_type || 'publish' !== $event->post_status ) {
			return null;
		}
		return array(
			'id'    => $event->ID,
			'title' => get_the_title( $event ),
			'url'   => (string) get_permalink( $event ),
		);
	}

	/**
	 * The sponsor label and an active flag, only between the placement's start and end dates (inclusive). After the end date
	 * the label and slot are gone and the story stays. This read model never uses the label to order anything.
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $surface When given, the placement must also cover this surface.
	 * @return array{name: string, label: string, active: bool}|null
	 */
	private static function sponsor( int $post_id, ?string $surface = null ): ?array {
		$placement = self::source()->placement( $post_id );
		if ( null === $placement || '' === $placement['sponsor'] ) {
			return null;
		}
		if ( null !== $surface && ! in_array( $placement['placement'], array( $surface, 'both' ), true ) ) {
			return null;
		}
		$today = self::today();
		if ( ( '' !== $placement['start'] && $today < $placement['start'] ) || ( '' !== $placement['end'] && $today > $placement['end'] ) ) {
			return null;
		}
		return array(
			'name'   => $placement['sponsor'],
			'label'  => 'Sponsored by ' . $placement['sponsor'],
			'active' => true,
		);
	}
}
