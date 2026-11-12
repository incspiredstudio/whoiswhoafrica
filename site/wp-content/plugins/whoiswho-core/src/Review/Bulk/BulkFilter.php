<?php
/**
 * The filter of a bulk unpublish: source, import batch, entity type, state, origin and a name search. It always restricts to the
 * two published states: a bulk unpublish includes published profiles only (REQ-193).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Bulk;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;

/**
 * Bulk filter.
 */
final class BulkFilter {

	/**
	 * Filter key => state-table column.
	 */
	private const COLUMNS = array(
		'source' => 'source',
		'batch'  => 'import_batch',
		'type'   => 'entity_type',
		'state'  => 'pub_state',
		'origin' => 'origin',
	);

	/**
	 * Keep only known keys with non-empty string values.
	 *
	 * @param array<string, mixed> $raw Raw filter.
	 * @return array<string, string>
	 */
	public static function clean( array $raw ): array {
		$clean = array();
		foreach ( array_merge( array_keys( self::COLUMNS ), array( 'name' ) ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) && '' !== trim( $raw[ $key ] ) ) {
				$clean[ $key ] = trim( $raw[ $key ] );
			}
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
	 * The FROM and WHERE clause with its placeholders.
	 *
	 * @param array<string, string> $filter Clean filter.
	 * @return array{0: string, 1: array<int, string>}
	 */
	private function from_where( array $filter ): array {
		global $wpdb;
		$published = array( PubState::PUBLISHED_PENDING, PubState::PUBLISHED_REVIEWED );
		$parts     = array( 's.pub_state IN (%s,%s)' );
		$args      = $published;
		foreach ( self::COLUMNS as $key => $column ) {
			if ( isset( $filter[ $key ] ) && 'pub_state' !== $column ) {
				$parts[] = 's.' . $column . ' = %s';
				$args[]  = $filter[ $key ];
			}
		}
		if ( isset( $filter['state'] ) ) {
			// A chosen state can only narrow the published pair.
			$parts[] = 's.pub_state = %s';
			$args[]  = $filter['state'];
		}
		$from = 'FROM ' . StateRepository::table() . ' s';
		if ( isset( $filter['name'] ) ) {
			$from   .= ' INNER JOIN ' . $wpdb->posts . ' p ON p.ID = s.profile_id';
			$parts[] = 'p.post_title LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $filter['name'] ) . '%';
		}
		return array( $from . ' WHERE ' . implode( ' AND ', $parts ), $args );
	}
}
