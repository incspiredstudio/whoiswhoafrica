<?php
/**
 * Reads of the profile state table, and writes of every column except pub_state (REQ-123, REQ-242). Writing pub_state is
 * StateWriter's job, and only Review\StateMachine may use StateWriter (an architecture test enforces it).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\State;

/**
 * State table access.
 */
class StateRepository {

	public const TABLE = 'wiw_profile_state';

	/**
	 * Columns a caller may set through update(); pub_state is deliberately absent.
	 */
	public const WRITABLE = array(
		'entity_type',
		'tier',
		'origin',
		'source',
		'import_batch',
		'content_hash',
		'importer_id',
		'reviewer_id',
		'reviewed_at',
		'claimed',
		'claimed_at',
		'assigned_user_id',
	);

	/**
	 * The state column name, also the filter key that selects profiles by publication state in count() and ids(). Readers use this constant so no
	 * array in their code has a literal pub_state key, which the architecture rule reserves for the writer.
	 */
	public const STATE_COLUMN = 'pub_state';

	/**
	 * Entity types accepted by the contract.
	 */
	public const ENTITY_TYPES = array( 'individual', 'entrepreneur', 'organisation', 'institution' );

	/**
	 * Origins.
	 */
	public const ORIGINS = array( 'import', 'customer', 'complimentary', 'nominee', 'manual' );

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * One profile's row.
	 *
	 * @param int $profile_id Profile post id.
	 * @return array<string, mixed>|null
	 */
	public function get( int $profile_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE profile_id = %d', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * The state of one profile, or null when it has no row.
	 *
	 * @param int $profile_id Profile post id.
	 */
	public function pub_state( int $profile_id ): ?string {
		$row = $this->get( $profile_id );
		return null === $row ? null : (string) $row['pub_state'];
	}

	/**
	 * Count rows matching a filter on the indexed columns.
	 *
	 * @param array<string, string> $filter Any of pub_state, entity_type, origin, source, import_batch.
	 */
	public function count( array $filter = array() ): int {
		global $wpdb;
		list( $where, $args ) = $this->where( $filter );
		$sql                  = 'SELECT COUNT(*) FROM ' . self::table() . $where;
		return (int) $wpdb->get_var( array() === $args ? $sql : $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table and column names; values prepared.
	}

	/**
	 * Profile ids matching a filter, in id order.
	 *
	 * @param array<string, string> $filter Any of pub_state, entity_type, origin, source, import_batch.
	 * @param int                   $limit  Maximum ids.
	 * @param int                   $after  Return ids greater than this.
	 * @return int[]
	 */
	public function ids( array $filter = array(), int $limit = 100, int $after = 0 ): array {
		global $wpdb;
		list( $where, $args ) = $this->where( $filter );
		$where               .= ( '' === $where ? ' WHERE' : ' AND' ) . ' profile_id > %d';
		$args[]               = $after;
		$args[]               = max( 1, $limit );
		$sql                  = 'SELECT profile_id FROM ' . self::table() . $where . ' ORDER BY profile_id ASC LIMIT %d';
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table and column names; values prepared.
	}

	/**
	 * Change columns other than pub_state.
	 *
	 * @param int                  $profile_id Profile post id.
	 * @param array<string, mixed> $values     Column => value; only WRITABLE columns.
	 * @throws \InvalidArgumentException For pub_state or an unknown column.
	 */
	public function update( int $profile_id, array $values ): void {
		global $wpdb;
		foreach ( array_keys( $values ) as $column ) {
			if ( ! in_array( $column, self::WRITABLE, true ) ) {
				throw new \InvalidArgumentException( 'Column cannot be written through the repository.' );
			}
		}
		$this->validate( $values );
		$values['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$wpdb->update( self::table(), $values, array( 'profile_id' => $profile_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Validate the enumerated columns.
	 *
	 * @param array<string, mixed> $values Values.
	 * @throws \InvalidArgumentException On an unknown entity type or origin.
	 */
	protected function validate( array $values ): void {
		if ( isset( $values['entity_type'] ) && ! in_array( $values['entity_type'], self::ENTITY_TYPES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown entity type.' );
		}
		if ( isset( $values['origin'] ) && ! in_array( $values['origin'], self::ORIGINS, true ) ) {
			throw new \InvalidArgumentException( 'Unknown origin.' );
		}
	}

	/**
	 * Build a WHERE clause from a filter.
	 *
	 * @param array<string, string> $filter Filter.
	 * @return array{0: string, 1: array<int, string>}
	 */
	private function where( array $filter ): array {
		$parts = array();
		$args  = array();
		foreach ( array( 'pub_state', 'entity_type', 'origin', 'source', 'import_batch' ) as $column ) {
			if ( isset( $filter[ $column ] ) && '' !== $filter[ $column ] ) {
				$parts[] = $column . ' = %s';
				$args[]  = $filter[ $column ];
			}
		}
		return array( array() === $parts ? '' : ' WHERE ' . implode( ' AND ', $parts ), $args );
	}
}
