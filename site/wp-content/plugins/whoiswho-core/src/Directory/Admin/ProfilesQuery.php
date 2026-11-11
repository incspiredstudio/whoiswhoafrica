<?php
/**
 * The staff Profiles list query (REQ-091, REQ-242, REQ-208): profiles in any state, filtered and grouped by source, batch, type,
 * state, origin and name, each row with what a visitor sees now (page, gone or not public) taken from StatePolicy. The same
 * sanitised filter set is what the bulk unpublish host acts on, so the screen and the bulk action never disagree.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Review\StatePolicy;

/**
 * Profiles list query.
 */
final class ProfilesQuery {

	public const PER_PAGE = 50;

	/**
	 * Filters and the state-table column each uses.
	 *
	 * @var array<string, string>
	 */
	public const FILTERS = array(
		'source' => 's.source',
		'batch'  => 's.import_batch',
		'type'   => 's.entity_type',
		'state'  => 's.pub_state',
		'origin' => 's.origin',
	);

	/**
	 * The filter set from a request: only known keys, plain text, and "state" and "type" only when they name a real value.
	 *
	 * @param Request $request Request.
	 * @return array<string, string>
	 */
	public static function filters_from( Request $request ): array {
		$out = array();
		foreach ( array_keys( self::FILTERS ) as $key ) {
			$value = $request->string( $key );
			if ( '' !== $value ) {
				$out[ $key ] = $value;
			}
		}
		if ( isset( $out['state'] ) && ! PubState::is_valid( $out['state'] ) ) {
			unset( $out['state'] );
		}
		if ( isset( $out['type'] ) && ! in_array( $out['type'], StateRepository::ENTITY_TYPES, true ) ) {
			unset( $out['type'] );
		}
		if ( isset( $out['origin'] ) && ! in_array( $out['origin'], StateRepository::ORIGINS, true ) ) {
			unset( $out['origin'] );
		}
		$name = $request->string( 'name' );
		if ( '' !== $name ) {
			$out['name'] = $name;
		}
		$group = $request->string( 'group' );
		if ( isset( self::FILTERS[ $group ] ) ) {
			$out['group'] = $group;
		}
		return $out;
	}

	/**
	 * Rows for a filter set.
	 *
	 * @param array<string, string> $filters Output of filters_from().
	 * @param int                   $page    Page, from 1.
	 * @return array{rows: array<int, array<string, mixed>>, total: int, pages: int, groups: array<int, array{value: string, count: int}>}
	 */
	public function run( array $filters, int $page = 1 ): array {
		global $wpdb;
		list( $where, $args ) = $this->where( $filters );
		$from                 = ' FROM ' . $wpdb->posts . ' p INNER JOIN ' . StateRepository::table() . ' s ON s.profile_id = p.ID' . $where;
		$total                = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*)' . $from, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names; values prepared.
		$pages                = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page                 = max( 1, min( $page, $pages ) );
		$found                = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT p.ID, p.post_title, s.entity_type, s.pub_state, s.origin, s.source, s.import_batch, s.claimed, s.updated_at' . $from . ' ORDER BY p.post_title ASC, p.ID ASC LIMIT %d OFFSET %d', array_merge( $args, array( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE ) ) ), // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names; values prepared.
			ARRAY_A
		);
		$rows                 = array();
		foreach ( $found as $row ) {
			$status = StatePolicy::for_state( (string) $row['pub_state'] )->status;
			$rows[] = array(
				'id'           => (int) $row['ID'],
				'name'         => (string) $row['post_title'],
				'type'         => (string) $row['entity_type'],
				'state'        => (string) $row['pub_state'],
				'origin'       => (string) $row['origin'],
				'source'       => (string) $row['source'],
				'batch'        => (string) $row['import_batch'],
				'claimed'      => (bool) (int) $row['claimed'],
				'visitor'      => self::visitor_status( $status ),
				'visitor_http' => $status,
				'updated_at'   => (string) $row['updated_at'],
			);
		}
		return array(
			'rows'   => $rows,
			'total'  => $total,
			'pages'  => $pages,
			'groups' => $this->groups( $filters ),
		);
	}

	/**
	 * Profile ids for a filter set (what the bulk action host acts on), in id order.
	 *
	 * @param array<string, string> $filters Output of filters_from().
	 * @param int                   $limit   Maximum ids.
	 * @return int[]
	 */
	public function ids( array $filters, int $limit = 5000 ): array {
		global $wpdb;
		list( $where, $args ) = $this->where( $filters );
		$sql                  = 'SELECT p.ID FROM ' . $wpdb->posts . ' p INNER JOIN ' . StateRepository::table() . ' s ON s.profile_id = p.ID' . $where . ' ORDER BY p.ID ASC LIMIT %d';
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, array_merge( $args, array( max( 1, $limit ) ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names; values prepared.
	}

	/**
	 * What a visitor sees for an HTTP status.
	 *
	 * @param int $status HTTP status from the state policy.
	 */
	public static function visitor_status( int $status ): string {
		if ( 200 === $status ) {
			return 'page';
		}
		return 410 === $status ? 'gone' : 'not public';
	}

	/**
	 * Counts per value of the "group" column, under the other filters.
	 *
	 * @param array<string, string> $filters Filters.
	 * @return array<int, array{value: string, count: int}>
	 */
	private function groups( array $filters ): array {
		global $wpdb;
		$group = $filters['group'] ?? '';
		if ( ! isset( self::FILTERS[ $group ] ) ) {
			return array();
		}
		$column               = self::FILTERS[ $group ];
		list( $where, $args ) = $this->where( array_diff_key( $filters, array( $group => 1 ) ) );
		$sql                  = 'SELECT ' . $column . ' AS v, COUNT(*) AS n FROM ' . $wpdb->posts . ' p INNER JOIN ' . StateRepository::table() . ' s ON s.profile_id = p.ID' . $where . ' GROUP BY ' . $column . ' ORDER BY n DESC, v ASC LIMIT 200'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the column comes from a fixed list.
		$out                  = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders
			$out[] = array(
				'value' => (string) $row['v'],
				'count' => (int) $row['n'],
			);
		}
		return $out;
	}

	/**
	 * WHERE clause.
	 *
	 * @param array<string, string> $filters Filters.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function where( array $filters ): array {
		global $wpdb;
		$where = ' WHERE p.post_type = %s AND p.post_status NOT IN (\'trash\', \'auto-draft\')';
		$args  = array( ProfileType::POST_TYPE );
		foreach ( self::FILTERS as $key => $column ) {
			if ( isset( $filters[ $key ] ) && '' !== $filters[ $key ] ) {
				$where .= ' AND ' . $column . ' = %s';
				$args[] = $filters[ $key ];
			}
		}
		if ( isset( $filters['name'] ) && '' !== $filters['name'] ) {
			$where .= ' AND p.post_title LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $filters['name'] ) . '%';
		}
		return array( $where, $args );
	}
}
