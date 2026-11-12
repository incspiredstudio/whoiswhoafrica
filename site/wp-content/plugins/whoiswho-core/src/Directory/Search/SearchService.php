<?php
/**
 * The one search query service (REQ-008, REQ-254, REQ-183, REQ-208, REQ-218). The header panel and the search page both call
 * search(), so the same query gives the same results. It returns a Profiles group and a Stories group and a count that equals
 * the number of results in the two groups. Profiles are ordered by an exact name match, then a name prefix, then FULLTEXT
 * relevance, then name; stories by relevance, then newest first. Nothing else influences the order: no payment, label,
 * customer or sponsored input. Every row is checked again against the read models, so a profile or story that is not public
 * never appears even if a stale index row is left behind. An empty query and a query with no match are different states. A
 * visitor who asks too often gets a rate-limited state and no results.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Search;

use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Forms\TransientStore;
use WhoIsWho\Core\Review\StatePolicy;

/**
 * Search service.
 */
final class SearchService {

	public const STATE_EMPTY        = 'empty';
	public const STATE_NONE         = 'none';
	public const STATE_RESULTS      = 'results';
	public const STATE_RATE_LIMITED = 'rate_limited';

	public const MAX_LENGTH = 80;
	public const PER_GROUP  = 20;
	public const FORM       = 'search';

	/**
	 * Rate limiter, or null for none (tests, CLI).
	 *
	 * @var RateLimiter|null
	 */
	private ?RateLimiter $limiter;

	/**
	 * Constructor.
	 *
	 * @param RateLimiter|null $limiter Per-IP limiter.
	 */
	public function __construct( ?RateLimiter $limiter = null ) {
		$this->limiter = $limiter;
	}

	/**
	 * The service for this site: at most 60 searches per visitor address in ten minutes.
	 */
	public static function for_site(): self {
		return new self(
			new RateLimiter(
				new TransientStore(),
				wp_salt( 'auth' ),
				array(
					self::FORM => array(
						'ip'      => array( 60, 600 ),
						'subject' => array( 60, 600 ),
					),
				) + RateLimiter::LIMITS
			)
		);
	}

	/**
	 * Search profiles and stories.
	 *
	 * @param string $query Text the visitor typed.
	 * @param string $ip    Visitor IP, for the rate limit.
	 * @return array{state: string, query: string, profiles: array<int, array<string, mixed>>, stories: array<int, array<string, mixed>>, count: int}
	 */
	public function search( string $query, string $ip = '' ): array {
		$query = trim( mb_substr( trim( wp_strip_all_tags( $query ) ), 0, self::MAX_LENGTH ) );
		if ( '' === $query ) {
			return $this->reply( self::STATE_EMPTY, $query, array(), array() );
		}
		if ( null !== $this->limiter && ! $this->limiter->allow( self::FORM, $ip ) ) {
			return $this->reply( self::STATE_RATE_LIMITED, $query, array(), array() );
		}
		$profiles = $this->profiles( $query );
		$stories  = $this->stories( $query );
		return $this->reply( array() === $profiles && array() === $stories ? self::STATE_NONE : self::STATE_RESULTS, $query, $profiles, $stories );
	}

	/**
	 * Build the reply; the count is the two groups added up.
	 *
	 * @param string                           $state    State.
	 * @param string                           $query    Cleaned query.
	 * @param array<int, array<string, mixed>> $profiles Profiles.
	 * @param array<int, array<string, mixed>> $stories  Stories.
	 * @return array{state: string, query: string, profiles: array<int, array<string, mixed>>, stories: array<int, array<string, mixed>>, count: int}
	 */
	private function reply( string $state, string $query, array $profiles, array $stories ): array {
		return array(
			'state'    => $state,
			'query'    => $query,
			'profiles' => $profiles,
			'stories'  => $stories,
			'count'    => count( $profiles ) + count( $stories ),
		);
	}

	/**
	 * The Profiles group.
	 *
	 * @param string $query Cleaned query.
	 * @return array<int, array<string, mixed>>
	 */
	private function profiles( string $query ): array {
		global $wpdb;
		$key  = strtolower( remove_accents( $query ) );
		$like = $wpdb->esc_like( $key );
		$any  = '%' . $wpdb->esc_like( $query ) . '%';
		$sql  = 'SELECT item_id, MATCH(name, organisation, title, industry, bio) AGAINST (%s IN NATURAL LANGUAGE MODE) AS rel'
			. ' FROM ' . SearchIndexer::table()
			. ' WHERE item_type = %s AND (MATCH(name, organisation, title, industry, bio) AGAINST (%s IN NATURAL LANGUAGE MODE) OR name_sort LIKE %s OR name LIKE %s OR organisation LIKE %s)'
			. ' ORDER BY (name_sort = %s) DESC, (name_sort LIKE %s) DESC, rel DESC, name_sort ASC, item_id ASC LIMIT %d';
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( $sql, $query, SearchIndexer::ITEM_TYPE, $query, '%' . $like . '%', $any, $any, $key, $like . '%', self::PER_GROUP ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name; every value is prepared.
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$profile = PublicProfile::find_by_id( (int) $row['item_id'] );
			if ( null === $profile || ! $profile->policy->in_search ) {
				continue;
			}
			$out[] = array(
				'id'          => $profile->id,
				'name'        => $profile->name,
				'url'         => $profile->url,
				'entity_type' => $profile->entity_type,
				'pending'     => $profile->policy->label,
				'label'       => $profile->policy->label ? StatePolicy::PENDING_LABEL : '',
			);
		}
		return $out;
	}

	/**
	 * The Stories group.
	 *
	 * @param string $query Cleaned query.
	 * @return array<int, array<string, mixed>>
	 */
	private function stories( string $query ): array {
		global $wpdb;
		$any  = '%' . $wpdb->esc_like( $query ) . '%';
		$sql  = 'SELECT i.item_id, MATCH(i.name, i.organisation, i.title, i.industry, i.bio) AGAINST (%s IN NATURAL LANGUAGE MODE) AS rel'
			. ' FROM ' . SearchIndexer::table() . ' i INNER JOIN ' . $wpdb->posts . ' p ON p.ID = i.item_id'
			. ' WHERE i.item_type = %s AND (MATCH(i.name, i.organisation, i.title, i.industry, i.bio) AGAINST (%s IN NATURAL LANGUAGE MODE) OR i.name LIKE %s)'
			. ' ORDER BY rel DESC, p.post_date_gmt DESC, i.item_id DESC LIMIT %d';
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( $sql, $query, 'story', $query, $any, self::PER_GROUP ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table names; every value is prepared.
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$story = PublicStory::find( (int) $row['item_id'] );
			if ( null === $story ) {
				continue;
			}
			$sponsor = $story['sponsor'];
			$out[]   = array(
				'id'           => (int) $row['item_id'],
				'title'        => (string) $story['title'],
				'url'          => (string) $story['url'],
				'type'         => (string) $story['type'],
				'sponsored'    => null !== $sponsor,
				'sponsor_name' => null !== $sponsor ? (string) $sponsor['name'] : '',
			);
		}
		return $out;
	}
}
