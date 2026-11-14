<?php
/**
 * The filter of a bulk unpublish: exactly the Profiles list's filter set (source, import batch, entity type, state, origin, the name
 * search and the "Needs classification" view), built by the same query code, so the bulk count is the list's count of published
 * profiles. It always restricts to the two published states: a bulk unpublish includes published profiles only (REQ-193).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Bulk;

use WhoIsWho\Core\Directory\Admin\ProfilesQuery;
use WhoIsWho\Core\Directory\State\PubState;

/**
 * Bulk filter.
 */
final class BulkFilter {

	/**
	 * The filter keys: the Profiles list's filters, its name search and its "Needs classification" view.
	 */
	public const KEYS = array( 'source', 'batch', 'type', 'state', 'origin', 'name', ProfilesQuery::CLASSIFICATION );

	/**
	 * Keep only known keys with non-empty string values.
	 *
	 * @param array<string, mixed> $raw Raw filter.
	 * @return array<string, string>
	 */
	public static function clean( array $raw ): array {
		$clean = array();
		foreach ( self::KEYS as $key ) {
			if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) && '' !== trim( $raw[ $key ] ) ) {
				$clean[ $key ] = trim( $raw[ $key ] );
			}
		}
		if ( isset( $clean[ ProfilesQuery::CLASSIFICATION ] ) && ProfilesQuery::NEEDS !== $clean[ ProfilesQuery::CLASSIFICATION ] ) {
			unset( $clean[ ProfilesQuery::CLASSIFICATION ] );
		}
		return $clean;
	}

	/**
	 * Count the published profiles that match.
	 *
	 * @param array<string, string> $filter Clean filter.
	 */
	public function count( array $filter ): int {
		global $wpdb;
		list( $from_where, $args ) = $this->from_where( $filter );
		$sql                       = 'SELECT COUNT(*) ' . $from_where;
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table and column names; values prepared.
	}

	/**
	 * Matching profile ids after a cursor, in id order.
	 *
	 * @param array<string, string> $filter Clean filter.
	 * @param int                   $after  Return ids greater than this.
	 * @param int                   $limit  Maximum ids.
	 * @return int[]
	 */
	public function ids( array $filter, int $after, int $limit ): array {
		global $wpdb;
		list( $from_where, $args ) = $this->from_where( $filter );
		$sql                       = 'SELECT s.profile_id ' . $from_where . ' AND s.profile_id > %d ORDER BY s.profile_id ASC LIMIT %d';
		$args[]                    = $after;
		$args[]                    = max( 1, $limit );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table and column names; values prepared.
	}

	/**
	 * Titles of the first few matches, for the confirmation.
	 *
	 * @param array<string, string> $filter Clean filter.
	 * @param int                   $limit  How many.
	 * @return string[]
	 */
	public function first_names( array $filter, int $limit = 5 ): array {
		$names = array();
		foreach ( $this->ids( $filter, 0, $limit ) as $id ) {
			$names[] = get_the_title( $id );
		}
		return $names;
	}

	/**
	 * The FROM and WHERE clause with its placeholders: the Profiles list's own filter (ProfilesQuery::from_where, so the "Needs
	 * classification" view, the name search and every other filter mean the same here), narrowed to the two published states.
	 *
	 * @param array<string, string> $filter Clean filter.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function from_where( array $filter ): array {
		list( $from_where, $args ) = ( new ProfilesQuery() )->from_where( $filter );
		$args[]                    = PubState::PUBLISHED_PENDING;
		$args[]                    = PubState::PUBLISHED_REVIEWED;
		return array( ltrim( $from_where ) . ' AND s.pub_state IN (%s,%s)', $args );
	}
}
