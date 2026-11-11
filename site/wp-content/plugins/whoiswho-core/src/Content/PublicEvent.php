<?php
/**
 * The only way public code reads events (REQ-262, REQ-264, REQ-265). Published events only; upcoming or past is computed from
 * the end date, or the start date when there is no end. A past event stays published but has no registration link.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content;

use WhoIsWho\Core\Content\Events\EventFields;
use WhoIsWho\Core\Content\Events\EventPostType;

/**
 * Public event read model.
 */
final class PublicEvent {

	/**
	 * One event, or null when it is not published.
	 *
	 * @param int|string $key Post ID or slug.
	 * @return array<string, mixed>|null
	 */
	public static function find( $key ): ?array {
		$post = is_int( $key ) ? get_post( $key ) : self::by_slug( (string) $key );
		if ( ! $post instanceof \WP_Post || ! self::is_public( $post ) ) {
			return null;
		}
		$event            = self::card( $post );
		$event['body']    = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
		$event['stories'] = self::stories_for( $post->ID );
		return $event;
	}

	/**
	 * Events: upcoming soonest first, then past newest first.
	 *
	 * @param array{city?: string, industry?: string} $args Filters.
	 * @return array{items: array<int, array<string, mixed>>, total: int, error: bool}
	 */
	public static function list( array $args ): array {
		$city     = trim( (string) ( $args['city'] ?? '' ) );
		$industry = (string) ( $args['industry'] ?? '' );
		$cards    = array();
		foreach ( self::posts( $industry ) as $post ) {
			$card = self::card( $post );
			if ( '' !== $city && 0 !== strcasecmp( $city, (string) $card['city'] ) ) {
				continue;
			}
			$cards[] = $card;
		}
		$upcoming = array_values( array_filter( $cards, static fn ( array $c ): bool => true === $c['upcoming'] ) );
		$past     = array_values( array_filter( $cards, static fn ( array $c ): bool => true !== $c['upcoming'] ) );
		usort( $upcoming, static fn ( array $a, array $b ): int => strcmp( (string) $a['start'], (string) $b['start'] ) );
		usort( $past, static fn ( array $a, array $b ): int => strcmp( (string) $b['start'], (string) $a['start'] ) );
		$items = array_merge( $upcoming, $past );
		return array(
			'items' => $items,
			'total' => count( $items ),
			'error' => false,
		);
	}

	/**
	 * The next few upcoming events, soonest first.
	 *
	 * @param int $count How many.
	 * @return array<int, array<string, mixed>>
	 */
	public static function next_upcoming( int $count = 3 ): array {
		$upcoming = array_filter( self::list( array() )['items'], static fn ( array $c ): bool => true === $c['upcoming'] );
		return array_slice( array_values( $upcoming ), 0, max( 0, $count ) );
	}

	/**
	 * The cities of published events, for the filter, in alphabetical order.
	 *
	 * @return array<int, string>
	 */
	public static function cities(): array {
		$cities = array();
		foreach ( self::list( array() )['items'] as $item ) {
			if ( '' !== (string) $item['city'] ) {
				$cities[ (string) $item['city'] ] = (string) $item['city'];
			}
		}
		sort( $cities );
		return $cities;
	}

	/**
	 * Published events, optionally in an industry.
	 *
	 * @param string $industry Industry slug or empty.
	 * @return array<int, \WP_Post>
	 */
	private static function posts( string $industry ): array {
		$args = array(
			'post_type'           => EventPostType::TYPE,
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => -1,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		);
		if ( '' !== $industry ) {
			if ( ! taxonomy_exists( PublicStory::INDUSTRY_TAXONOMY ) ) {
				return array();
			}
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- small, filtered list.
				array(
					'taxonomy' => PublicStory::INDUSTRY_TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( $industry ),
				),
			);
		}
		return get_posts( $args );
	}

	/**
	 * An event by slug.
	 *
	 * @param string $slug Slug.
	 */
	private static function by_slug( string $slug ): ?\WP_Post {
		$found = get_posts(
			array(
				'name'           => sanitize_title( $slug ),
				'post_type'      => EventPostType::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);
		return $found[0] ?? null;
	}

	/**
	 * Whether a visitor may see the event.
	 *
	 * @param \WP_Post $post Post.
	 */
	private static function is_public( \WP_Post $post ): bool {
		return EventPostType::TYPE === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password;
	}

	/**
	 * The fields a card and the page share.
	 *
	 * @param \WP_Post $post Event.
	 * @return array<string, mixed>
	 */
	private static function card( \WP_Post $post ): array {
		$start    = EventFields::value( $post->ID, 'start' );
		$end      = EventFields::value( $post->ID, 'end' );
		$edge     = '' !== $end ? $end : $start;
		$now      = current_time( 'Y-m-d H:i' );
		$upcoming = '' !== $edge && strcmp( $edge, $now ) >= 0;
		$link     = EventFields::value( $post->ID, 'registration_url' );
		$cover    = get_the_post_thumbnail_url( $post->ID, 'large' );
		$terms    = wp_get_object_terms( $post->ID, PublicStory::INDUSTRY_TAXONOMY, array( 'fields' => 'names' ) );
		return array(
			'id'               => $post->ID,
			'slug'             => $post->post_name,
			'url'              => (string) get_permalink( $post ),
			'title'            => get_the_title( $post ),
			'summary'          => wp_strip_all_tags( $post->post_excerpt ),
			'start'            => $start,
			'end'              => $end,
			'month'            => '' !== $start ? substr( $start, 0, 7 ) : '',
			'upcoming'         => $upcoming,
			'past'             => ! $upcoming,
			'online'           => '1' === EventFields::value( $post->ID, 'online' ),
			'venue'            => EventFields::value( $post->ID, 'venue' ),
			'city'             => EventFields::value( $post->ID, 'city' ),
			'country'          => EventFields::value( $post->ID, 'country' ),
			'organiser'        => EventFields::value( $post->ID, 'organiser' ),
			'price_note'       => EventFields::value( $post->ID, 'price_note' ),
			'registration_url' => $upcoming && '' !== $link ? $link : null,
			'cover'            => is_string( $cover ) ? $cover : '',
			'industries'       => is_array( $terms ) ? array_values( $terms ) : array(),
		);
	}

	/**
	 * Published stories that link this event, read through PublicStory.
	 *
	 * @param int $event_id Event ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function stories_for( int $event_id ): array {
		$out = array();
		foreach ( PublicStory::list( array( 'per_page' => 50 ) )['items'] as $story ) {
			if ( (int) get_post_meta( (int) $story['id'], 'related_event_id', true ) === $event_id ) {
				$out[] = $story;
			}
		}
		return $out;
	}
}
