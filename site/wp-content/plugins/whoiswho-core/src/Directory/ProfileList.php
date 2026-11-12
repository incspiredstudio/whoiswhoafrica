<?php
/**
 * The public list query service (REQ-007, REQ-218, REQ-208, REQ-009): the five directory lists with their filters, sort and
 * pagination, and the industry counts. It is the only way a template reads a list of profiles, so no template runs its own
 * query on the profile type. Only profiles whose state allows lists (StatePolicy::in_lists, read from the state table on every
 * call) and whose post is published are returned; a pending-review profile never appears. Sorting never uses a commercial signal.
 *
 * Filters arrive as slugs of the controlled-list terms (industry, country, city, profession, org_type). A filter that does not
 * apply to the list (profession on Businesses, organisation type on People) is ignored, so a shared URL never errors.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Review\StatePolicy;

/**
 * Profile list queries.
 */
final class ProfileList {

	public const PER_PAGE = 16;

	public const SORT_NAME    = 'name';
	public const SORT_UPDATED = 'updated';

	/**
	 * The five lists and the entity types each one holds.
	 *
	 * @var array<string, string[]>
	 */
	private const LISTS = array(
		'all'           => array(),
		'people'        => array( 'individual' ),
		'entrepreneurs' => array( 'entrepreneur' ),
		'businesses'    => array( 'organisation' ),
		'institutions'  => array( 'institution' ),
	);

	/**
	 * Filter => taxonomy, and the lists each applies to (empty = all lists).
	 *
	 * @var array<string, array{0: string, 1: string[]}>
	 */
	private const FILTERS = array(
		'industry'   => array( Taxonomies::INDUSTRY, array() ),
		'country'    => array( Taxonomies::PLACE, array() ),
		'city'       => array( Taxonomies::PLACE, array() ),
		'profession' => array( Taxonomies::PROFESSION, array( 'people', 'entrepreneurs' ) ),
		'org_type'   => array( Taxonomies::ORG_TYPE, array( 'businesses', 'institutions' ) ),
	);

	/**
	 * The list names.
	 *
	 * @return string[]
	 */
	public static function lists(): array {
		return array_keys( self::LISTS );
	}

	/**
	 * Filters that apply to a list.
	 *
	 * @param string $list_name List name.
	 * @return string[]
	 */
	public static function filters_for( string $list_name ): array {
		$out = array();
		foreach ( self::FILTERS as $name => list( , $lists ) ) {
			if ( array() === $lists || in_array( $list_name, $lists, true ) ) {
				$out[] = $name;
			}
		}
		return $out;
	}

	/**
	 * Run a list query.
	 *
	 * @param string               $list_name   One of lists(); an unknown name is treated as "all".
	 * @param array<string, mixed> $params Filters (slugs), "sort" (name or updated) and "page".
	 * @return array{profiles: PublicProfile[], total: int, page: int, pages: int, per_page: int, applied: array<string, string>}
	 */
	public function query( string $list_name, array $params = array() ): array {
		global $wpdb;
		$list_name = isset( self::LISTS[ $list_name ] ) ? $list_name : 'all';
		$page      = max( 1, (int) ( $params['page'] ?? 1 ) );
		$sort      = self::SORT_UPDATED === ( $params['sort'] ?? '' ) ? self::SORT_UPDATED : self::SORT_NAME;
		$applied   = array();

		list( $where, $args ) = $this->base_where( self::LISTS[ $list_name ] );
		foreach ( self::filters_for( $list_name ) as $name ) {
			$slug = isset( $params[ $name ] ) && is_string( $params[ $name ] ) ? sanitize_title( $params[ $name ] ) : '';
			if ( '' === $slug ) {
				continue;
			}
			$tt_ids           = $this->term_taxonomy_ids( $name, $slug );
			$applied[ $name ] = $slug;
			if ( array() === $tt_ids ) {
				$where .= ' AND 1 = 0';
				continue;
			}
			$where .= ' AND EXISTS (SELECT 1 FROM ' . $wpdb->term_relationships . ' tr WHERE tr.object_id = p.ID AND tr.term_taxonomy_id IN (' . implode( ',', array_fill( 0, count( $tt_ids ), '%d' ) ) . '))';
			$args   = array_merge( $args, $tt_ids );
		}

		$from  = ' FROM ' . $wpdb->posts . ' p INNER JOIN ' . StateRepository::table() . ' s ON s.profile_id = p.ID';
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*)' . $from . $where, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- fixed names; values prepared.
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page  = min( $page, $pages );
		$order = self::SORT_UPDATED === $sort ? 'p.post_modified_gmt DESC, p.post_title ASC, p.ID ASC' : 'p.post_title ASC, p.ID ASC';

		$ids = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT p.ID' . $from . $where . ' ORDER BY ' . $order . ' LIMIT %d OFFSET %d', array_merge( $args, array( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- fixed names and order clause; values prepared.
		);

		$profiles = array();
		foreach ( $ids as $id ) {
			$profile = PublicProfile::find_by_id( (int) $id );
			if ( null !== $profile && $profile->policy->in_lists ) {
				$profiles[] = $profile;
			}
		}
		return array(
			'profiles' => $profiles,
			'total'    => $total,
			'page'     => $page,
			'pages'    => $pages,
			'per_page' => self::PER_PAGE,
			'applied'  => $applied,
		);
	}

	/**
	 * Industry counts of listed profiles, for the industries index: one row per industry term, empty industries included.
	 *
	 * @return array<int, array{term_id: int, slug: string, name: string, count: int}>
	 */
	public function industry_counts(): array {
		global $wpdb;
		list( $where, $args ) = $this->base_where( array() );
		$sql                  = 'SELECT tt.term_id, COUNT(DISTINCT p.ID) FROM ' . $wpdb->posts . ' p INNER JOIN ' . StateRepository::table() . ' s ON s.profile_id = p.ID'
			. ' INNER JOIN ' . $wpdb->term_relationships . ' tr ON tr.object_id = p.ID INNER JOIN ' . $wpdb->term_taxonomy . ' tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s'
			. $where . ' GROUP BY tt.term_id';
		$rows                 = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( Taxonomies::INDUSTRY ), $args ) ), ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed names; values prepared.
		$counts               = array();
		foreach ( $rows as $row ) {
			$counts[ (int) $row[0] ] = (int) $row[1];
		}
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::INDUSTRY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$out[] = array(
				'term_id' => (int) $term->term_id,
				'slug'    => (string) $term->slug,
				'name'    => (string) $term->name,
				'count'   => $counts[ (int) $term->term_id ] ?? 0,
			);
		}
		return $out;
	}

	/**
	 * The shared WHERE clause: profile type, published post, a state whose policy allows lists, and the entity types of the list.
	 *
	 * @param string[] $types Entity types; empty for all.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function base_where( array $types ): array {
		$states = array_values(
			array_filter(
				PubState::all(),
				static fn ( string $state ): bool => StatePolicy::for_state( $state )->in_lists
			)
		);
		$where  = ' WHERE p.post_type = %s AND p.post_status = %s AND s.pub_state IN (' . implode( ',', array_fill( 0, count( $states ), '%s' ) ) . ')';
		$args   = array_merge( array( ProfileType::POST_TYPE, 'publish' ), $states );
		if ( array() !== $types ) {
			$where .= ' AND s.entity_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')';
			$args   = array_merge( $args, $types );
		}
		return array( $where, $args );
	}

	/**
	 * Term taxonomy ids for a filter slug. A country includes its cities; the city filter takes only city terms and the country
	 * filter only country or body terms.
	 *
	 * @param string $filter Filter name.
	 * @param string $slug   Term slug.
	 * @return int[]
	 */
	private function term_taxonomy_ids( string $filter, string $slug ): array {
		$taxonomy = self::FILTERS[ $filter ][0];
		$term     = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $term instanceof \WP_Term ) {
			return array();
		}
		if ( Taxonomies::PLACE === $taxonomy ) {
			$kind = (string) get_term_meta( $term->term_id, Taxonomies::META_KIND, true );
			if ( ( 'country' === $filter ) === ( Taxonomies::KIND_CITY === $kind ) ) {
				return array();
			}
		}
		$ids = array( (int) $term->term_taxonomy_id );
		if ( 'country' === $filter ) {
			$children = get_term_children( $term->term_id, $taxonomy );
			foreach ( is_array( $children ) ? $children : array() as $child_id ) {
				$child = get_term( (int) $child_id, $taxonomy );
				if ( $child instanceof \WP_Term ) {
					$ids[] = (int) $child->term_taxonomy_id;
				}
			}
		}
		return $ids;
	}
}
