<?php
/**
 * The winners query (REQ-246, REQ-033, REQ-198): Live winners of every edition, newest first, filtered by year, group, category,
 * winner type and country, searched by name, category or citation, sorted Newest first or Name A to Z, twelve a page and grouped
 * by year. The filters read from and write back to the address (a plain query string), so a filtered view can be shared and the
 * back button works. It reads only PublicNominee, so a Not live nomination or an unreviewed profile never appears.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Winners query.
 */
final class WinnersQuery {

	public const PER_PAGE = 12;
	public const SORTS    = array( 'newest', 'name' );
	public const TYPES    = array( 'person', 'organisation' );

	/**
	 * Entity types that count as a person winner; every other type is an organisation.
	 */
	private const PERSON_TYPES = array( 'individual', 'entrepreneur' );

	/**
	 * Clean raw query values into filter parameters. Unknown values are dropped, never trusted.
	 *
	 * @param array<string, mixed> $raw Query values (from the request layer).
	 * @return array{year: int, group: string, category: int, type: string, country: string, q: string, sort: string, page: int}
	 */
	public static function params( array $raw ): array {
		$text  = static fn ( string $key ): string => isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( (string) $raw[ $key ] ) : '';
		$year  = (int) $text( 'year' );
		$group = $text( 'group' );
		$type  = $text( 'type' );
		$sort  = $text( 'sort' );
		return array(
			'year'     => $year >= 1900 && $year <= 2200 ? $year : 0,
			'group'    => isset( AwardsTables::GROUPS[ $group ] ) ? $group : '',
			'category' => max( 0, (int) $text( 'category' ) ),
			'type'     => in_array( $type, self::TYPES, true ) ? $type : '',
			'country'  => sanitize_title( $text( 'country' ) ),
			'q'        => mb_substr( sanitize_text_field( $text( 'q' ) ), 0, 100 ),
			'sort'     => in_array( $sort, self::SORTS, true ) ? $sort : 'newest',
			'page'     => max( 1, (int) $text( 'page' ) ),
		);
	}

	/**
	 * The filter values of the current address, read through the request layer.
	 *
	 * @param Request|null $request Request; the current one by default.
	 * @return array<string, string>
	 */
	public static function from_request( ?Request $request = null ): array {
		$request = $request ?? Request::from_globals();
		$out     = array();
		foreach ( array( 'year', 'group', 'category', 'type', 'country', 'q', 'sort', 'page' ) as $key ) {
			$out[ $key ] = $request->string( $key );
		}
		return $out;
	}

	/**
	 * The query string for a set of parameters, leaving out defaults, so the address round-trips.
	 *
	 * @param array<string, mixed> $params Parameters from params().
	 */
	public static function query_string( array $params ): string {
		$params = self::params( $params );
		$out    = array();
		foreach ( array( 'year', 'group', 'category', 'type', 'country', 'q', 'sort', 'page' ) as $key ) {
			$value = $params[ $key ];
			if ( '' === $value || 0 === $value || ( 'sort' === $key && 'newest' === $value ) || ( 'page' === $key && 1 === $value ) ) {
				continue;
			}
			$out[ $key ] = $value;
		}
		return http_build_query( $out, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Run the query.
	 *
	 * @param array<string, mixed> $raw Query values.
	 * @return array{items: PublicNominee[], by_year: array<int, PublicNominee[]>, total: int, pages: int, page: int, params: array<string, mixed>, years: array<int, int>, groups: array<string, string>, categories: array<int, string>, countries: array<string, string>}
	 */
	public function run( array $raw ): array {
		$params = self::params( $raw );
		$all    = array();
		foreach ( PublicWinner::all( 5000 ) as $winner ) {
			$all[] = array(
				'winner'  => $winner,
				'type'    => in_array( $winner->profile->entity_type, self::PERSON_TYPES, true ) ? 'person' : 'organisation',
				'country' => $this->country_of( $winner->profile->id ),
			);
		}
		$matches = static fn ( array $row, bool $with_year ): bool => self::matches( $row, $params, $with_year );
		$rows    = array_values( array_filter( $all, static fn ( array $row ): bool => $matches( $row, true ) ) );

		// The year facet counts winners under every other filter, so it shows what choosing a year would give.
		$years = array();
		foreach ( $all as $row ) {
			if ( $matches( $row, false ) ) {
				$year           = $row['winner']->year;
				$years[ $year ] = ( $years[ $year ] ?? 0 ) + 1;
			}
		}
		krsort( $years );

		$groups     = array();
		$categories = array();
		$countries  = array();
		foreach ( $all as $row ) {
			$groups[ $row['winner']->group_key ]       = $row['winner']->group;
			$categories[ $row['winner']->category_id ] = $row['winner']->category;
			if ( '' !== $row['country']['slug'] ) {
				$countries[ $row['country']['slug'] ] = $row['country']['name'];
			}
		}
		asort( $categories );
		asort( $countries );

		if ( 'name' === $params['sort'] ) {
			usort( $rows, static fn ( array $a, array $b ): int => strcasecmp( $a['winner']->name, $b['winner']->name ) );
		}
		$total = count( $rows );
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page  = min( $params['page'], $pages );
		$items = array_map( static fn ( array $row ): PublicNominee => $row['winner'], array_slice( $rows, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE ) );

		$by_year = array();
		if ( 'newest' === $params['sort'] ) {
			foreach ( $items as $item ) {
				$by_year[ $item->year ][] = $item;
			}
		}
		$params['page'] = $page;
		return array(
			'items'      => $items,
			'by_year'    => $by_year,
			'total'      => $total,
			'pages'      => $pages,
			'page'       => $page,
			'params'     => $params,
			'years'      => $years,
			'groups'     => $groups,
			'categories' => $categories,
			'countries'  => $countries,
		);
	}

	/**
	 * Whether a row passes the filters and the search.
	 *
	 * @param array<string, mixed> $row       Row: winner, type, country.
	 * @param array<string, mixed> $params    Parameters.
	 * @param bool                 $with_year Apply the year filter.
	 */
	private static function matches( array $row, array $params, bool $with_year ): bool {
		/** Winner. @var PublicNominee $winner */
		$winner = $row['winner'];
		if ( $with_year && $params['year'] > 0 && $winner->year !== $params['year'] ) {
			return false;
		}
		if ( '' !== $params['group'] && $winner->group_key !== $params['group'] ) {
			return false;
		}
		if ( $params['category'] > 0 && $winner->category_id !== $params['category'] ) {
			return false;
		}
		if ( '' !== $params['type'] && $row['type'] !== $params['type'] ) {
			return false;
		}
		if ( '' !== $params['country'] && $row['country']['slug'] !== $params['country'] ) {
			return false;
		}
		if ( '' !== $params['q'] ) {
			$haystack = mb_strtolower( $winner->name . ' ' . $winner->category . ' ' . $winner->citation );
			foreach ( (array) preg_split( '/\s+/', mb_strtolower( $params['q'] ) ) as $word ) {
				if ( '' !== $word && false === mb_strpos( $haystack, $word ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * The country of a profile, from its places: a country term, or the parent country of a city.
	 *
	 * @param int $profile_id Profile.
	 * @return array{slug: string, name: string}
	 */
	private function country_of( int $profile_id ): array {
		$terms = wp_get_object_terms( $profile_id, Taxonomies::PLACE );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$kind = (string) get_term_meta( $term->term_id, Taxonomies::META_KIND, true );
			if ( Taxonomies::KIND_COUNTRY === $kind ) {
				return array(
					'slug' => $term->slug,
					'name' => $term->name,
				);
			}
			if ( Taxonomies::KIND_CITY === $kind && $term->parent > 0 ) {
				$parent = get_term( $term->parent, Taxonomies::PLACE );
				if ( $parent instanceof \WP_Term ) {
					return array(
						'slug' => $parent->slug,
						'name' => $parent->name,
					);
				}
			}
		}//end foreach
		return array(
			'slug' => '',
			'name' => '',
		);
	}
}
