<?php
/**
 * The parts of the demo content an administrator can choose on the Setup screen (REQ-208, REQ-056), what each part needs, which
 * seeder steps make it, and which demo items belong to it. A part that needs another part brings it along (Awards and Home page
 * curation need Profiles; Comments need Stories), and removing a part first removes the parts that need it. Plain lists, plus
 * one read-only query for the items of a part.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Platform\Cli\Sample\SampleMarker;
use WhoIsWho\Core\Platform\Cli\Sample\SampleSeeder;

/**
 * Demo parts.
 */
final class DemoParts {

	/**
	 * Every part, in the order the screen lists them and the import makes them.
	 */
	public const PARTS = array( 'profiles', 'stories', 'comments', 'events', 'awards', 'curation', 'pages', 'settings' );

	/**
	 * What each part needs.
	 */
	public const NEEDS = array(
		'comments' => array( 'stories' ),
		'awards'   => array( 'profiles' ),
		'curation' => array( 'profiles', 'stories' ),
	);

	/**
	 * The seeder steps that make each part.
	 */
	private const STEPS = array(
		'profiles' => array( 'terms', 'profiles', 'images' ),
		'stories'  => array( 'terms', 'stories', 'images' ),
		'comments' => array( 'comments' ),
		'events'   => array( 'terms', 'events', 'images' ),
		'awards'   => array( 'awards' ),
		'curation' => array( 'curation' ),
		'pages'    => array( 'pages' ),
		'settings' => array( 'settings' ),
	);

	/**
	 * Key prefixes (SampleMarker::KEY_META) of the posts each part makes.
	 */
	private const PREFIXES = array(
		'profiles' => array( 'profile:', 'image:profile:' ),
		'stories'  => array( 'story:', 'image:story:' ),
		'events'   => array( 'event:', 'image:event:' ),
		'pages'    => array( 'page:' ),
	);

	/**
	 * Plain-language names.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'profiles' => __( 'Profiles', 'whoiswho' ),
			'stories'  => __( 'Stories', 'whoiswho' ),
			'comments' => __( 'Comments', 'whoiswho' ),
			'events'   => __( 'Events', 'whoiswho' ),
			'awards'   => __( 'Awards', 'whoiswho' ),
			'curation' => __( 'Home page curation', 'whoiswho' ),
			'pages'    => __( 'Pages (About, FAQ, legal and so on)', 'whoiswho' ),
			'settings' => __( 'Settings', 'whoiswho' ),
		);
	}

	/**
	 * One line on what each part adds.
	 *
	 * @return array<string, string>
	 */
	public static function descriptions(): array {
		return array(
			'profiles' => __( 'Sample profiles for the directory, published as reviewed.', 'whoiswho' ),
			'stories'  => __( 'Invented stories with cover pictures.', 'whoiswho' ),
			'comments' => __( 'A few invented reader comments on each story: some approved, some waiting for moderation.', 'whoiswho' ),
			'events'   => __( 'Invented upcoming events.', 'whoiswho' ),
			'awards'   => __( 'Invented award categories, nominees and winners.', 'whoiswho' ),
			'curation' => __( 'Chooses which profiles and stories the home page shows.', 'whoiswho' ),
			'pages'    => __( 'The Home, About, FAQ, Awards and draft legal pages. The demo Home page becomes your front page.', 'whoiswho' ),
			'settings' => __( 'Switches on Nominations so the Nominate links show.', 'whoiswho' ),
		);
	}

	/**
	 * Why a part needs another, for the note shown when a box is ticked for you.
	 *
	 * @return array<string, string>
	 */
	public static function reasons(): array {
		return array(
			'comments' => __( 'Comments need Stories: every comment belongs to a story.', 'whoiswho' ),
			'awards'   => __( 'Awards need Profiles: the nominees and winners are profiles.', 'whoiswho' ),
			'curation' => __( 'Home page curation needs Profiles and Stories: it chooses which of them the home page shows.', 'whoiswho' ),
		);
	}

	/**
	 * The chosen parts plus everything they need, in PARTS order. Unknown names are dropped.
	 *
	 * @param string[] $parts Chosen parts.
	 * @return string[]
	 */
	public static function closure( array $parts ): array {
		$chosen = array_values( array_intersect( array_map( 'strval', $parts ), self::PARTS ) );
		$queue  = $chosen;
		while ( array() !== $queue ) {
			$part = (string) array_shift( $queue );
			foreach ( self::NEEDS[ $part ] ?? array() as $need ) {
				if ( ! in_array( $need, $chosen, true ) ) {
					$chosen[] = $need;
					$queue[]  = $need;
				}
			}
		}
		return array_values( array_intersect( self::PARTS, $chosen ) );
	}

	/**
	 * The parts that need a part, directly or through another, in PARTS order.
	 *
	 * @param string $part Part.
	 * @return string[]
	 */
	public static function dependents( string $part ): array {
		$out = array();
		foreach ( self::PARTS as $other ) {
			if ( $other !== $part && in_array( $part, self::closure( array( $other ) ), true ) ) {
				$out[] = $other;
			}
		}
		return $out;
	}

	/**
	 * The seeder steps for some parts (with their needs), in the seeder's order, each once.
	 *
	 * @param string[] $parts Parts.
	 * @return string[]
	 */
	public static function steps( array $parts ): array {
		$steps = array();
		foreach ( self::closure( $parts ) as $part ) {
			$steps = array_merge( $steps, self::STEPS[ $part ] );
		}
		return array_values( array_intersect( SampleSeeder::STEPS, array_unique( $steps ) ) );
	}

	/**
	 * Parse a comma-separated list of parts sent by the screen.
	 *
	 * @param string $value For example "profiles,awards".
	 * @return string[] Known parts only, in PARTS order.
	 */
	public static function parse( string $value ): array {
		$names = array_filter( array_map( 'trim', explode( ',', strtolower( $value ) ) ) );
		return array_values( array_intersect( self::PARTS, $names ) );
	}

	/**
	 * The part a demo post belongs to, from its key, or null.
	 *
	 * @param string $key SampleMarker key.
	 */
	public static function part_of_key( string $key ): ?string {
		foreach ( self::PREFIXES as $part => $prefixes ) {
			foreach ( $prefixes as $prefix ) {
				if ( str_starts_with( $key, $prefix ) ) {
					return $part;
				}
			}
		}
		return null;
	}

	/**
	 * The demo posts of a part, as post id => key. Attachments come last, so their posts are handled first.
	 *
	 * @param string $part Part.
	 * @return array<int, string>
	 */
	public static function post_keys( string $part ): array {
		global $wpdb;
		$out = array();
		foreach ( self::PREFIXES[ $part ] ?? array() as $prefix ) {
			$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a short read of the demo markers on the setup screen.
				$wpdb->prepare(
					"SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->postmeta} s ON s.post_id = m.post_id AND s.meta_key = %s AND s.meta_value = '1' WHERE m.meta_key = %s AND m.meta_value LIKE %s ORDER BY m.post_id ASC",
					SampleMarker::META,
					SampleMarker::KEY_META,
					$wpdb->esc_like( $prefix ) . '%'
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$out[ (int) $row['post_id'] ] = (string) $row['meta_value'];
			}
		}
		$images = array_filter( $out, static fn ( string $key ): bool => str_starts_with( $key, 'image:' ) );
		return array_diff_key( $out, $images ) + $images;
	}

	/**
	 * The demo comments, as comment id => key.
	 *
	 * @return array<int, string>
	 */
	public static function comment_keys(): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- as above.
			$wpdb->prepare(
				"SELECT m.comment_id, m.meta_value FROM {$wpdb->commentmeta} m INNER JOIN {$wpdb->commentmeta} s ON s.comment_id = m.comment_id AND s.meta_key = %s AND s.meta_value = '1' WHERE m.meta_key = %s ORDER BY m.comment_id ASC",
				SampleMarker::META,
				SampleMarker::KEY_META
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row['comment_id'] ] = (string) $row['meta_value'];
		}
		return $out;
	}

	/**
	 * How many demo items of a part are on the site now (the main items, not their pictures).
	 *
	 * @param string $part Part.
	 */
	public static function count( string $part ): int {
		$registry = SampleMarker::registry();
		switch ( $part ) {
			case 'comments':
				return count( self::comment_keys() );
			case 'awards':
				return count( (array) ( $registry['categories'] ?? array() ) ) + count( (array) ( $registry['nominations'] ?? array() ) );
			case 'curation':
				return count( array_filter( array_keys( (array) ( $registry['prev'] ?? array() ) ), static fn ( $key ): bool => str_starts_with( (string) $key, 'curation:' ) ) );
			case 'settings':
				return array_key_exists( 'nominations', (array) ( $registry['prev'] ?? array() ) ) ? 1 : 0;
			default:
				return count( array_filter( self::post_keys( $part ), static fn ( string $key ): bool => ! str_starts_with( $key, 'image:' ) ) );
		}
	}
}
