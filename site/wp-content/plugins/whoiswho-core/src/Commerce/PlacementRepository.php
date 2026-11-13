<?php
/**
 * Storage of sponsored placements in wiw_placements (REQ-021, REQ-205): one row per slot for a deal and a story, with the
 * sponsor name, the slot (Home, Stories or Both), start and end dates and an ended flag. The status is computed from the dates
 * elsewhere; nothing here refers to a profile or an award.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

/**
 * Placement repository.
 */
class PlacementRepository {

	public const TABLE = 'wiw_placements';

	public const SLOTS = array( 'home', 'stories', 'both' );

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		arrangement_id bigint(20) unsigned NOT NULL,
		story_id bigint(20) unsigned NOT NULL,
		sponsor_name varchar(190) NOT NULL,
		slot varchar(10) NOT NULL,
		starts_on date NOT NULL,
		ends_on date NOT NULL,
		ended tinyint(1) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY arrangement_id (arrangement_id),
		KEY story_id (story_id),
		KEY slot_dates (slot, starts_on, ends_on)';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Insert a placement.
	 *
	 * @param array<string, mixed> $fields Keys arrangement_id, story_id, sponsor_name, slot, starts_on, ends_on.
	 * @param \DateTimeImmutable   $at     Moment.
	 * @return int New id.
	 * @throws \InvalidArgumentException When a value is missing or invalid.
	 * @throws \RuntimeException         When the row cannot be stored.
	 */
	public function insert( array $fields, \DateTimeImmutable $at ): int {
		global $wpdb;
		$slot    = (string) ( $fields['slot'] ?? '' );
		$sponsor = trim( (string) ( $fields['sponsor_name'] ?? '' ) );
		$starts  = (string) ( $fields['starts_on'] ?? '' );
		$ends    = (string) ( $fields['ends_on'] ?? '' );
		if ( ! in_array( $slot, self::SLOTS, true ) ) {
			throw new \InvalidArgumentException( 'A slot is Home, Stories or Both.' );
		}
		if ( '' === $sponsor || (int) ( $fields['arrangement_id'] ?? 0 ) < 1 || (int) ( $fields['story_id'] ?? 0 ) < 1 ) {
			throw new \InvalidArgumentException( 'A placement needs a deal, a story and a sponsor name.' );
		}
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $starts ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ends ) || $ends < $starts ) {
			throw new \InvalidArgumentException( 'A placement needs a start date and an end date on or after it.' );
		}
		$stamp = $at->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$row   = array(
			'arrangement_id' => (int) $fields['arrangement_id'],
			'story_id'       => (int) $fields['story_id'],
			'sponsor_name'   => $sponsor,
			'slot'           => $slot,
			'starts_on'      => $starts,
			'ends_on'        => $ends,
			'created_at'     => $stamp,
			'updated_at'     => $stamp,
		);
		if ( false === $wpdb->insert( self::table(), $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw new \RuntimeException( 'The placement could not be stored.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Mark a placement as ended.
	 *
	 * @param int                $id Id.
	 * @param \DateTimeImmutable $at Moment.
	 */
	public function mark_ended( int $id, \DateTimeImmutable $at ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'ended'      => 1,
				'updated_at' => $at->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * One placement.
	 *
	 * @param int $id Id.
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Count placements that are not ended, belong to a deal that is not cancelled, share the page and overlap the dates.
	 *
	 * @param string $slot   home, stories or both.
	 * @param string $starts Start date.
	 * @param string $ends   End date.
	 */
	public function overlapping_count( string $slot, string $starts, string $ends ): int {
		global $wpdb;
		$clashes = 'home' === $slot ? array( 'home', 'both' ) : ( 'stories' === $slot ? array( 'stories', 'both' ) : self::SLOTS );
		$marks   = implode( ',', array_fill( 0, count( $clashes ), '%s' ) );
		$sql     = 'SELECT COUNT(*) FROM ' . self::table() . ' p JOIN ' . ArrangementRepository::table() . ' a ON a.id = p.arrangement_id WHERE p.ended = 0 AND a.cancelled = 0 AND p.slot IN (' . $marks . ') AND p.starts_on <= %s AND p.ends_on >= %s';
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, array_merge( $clashes, array( $ends, $starts ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names; values are placeholders.
	}

	/**
	 * Placements of a deal, oldest first.
	 *
	 * @param int $arrangement_id Deal id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_arrangement( int $arrangement_id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE arrangement_id = %d ORDER BY starts_on, id', $arrangement_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}
}
