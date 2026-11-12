<?php
/**
 * The map from a stable record id to the profile post id (REQ-145, contract unknown U6: the site generates slugs and keeps this
 * map). A record id never maps to two profiles and a profile never has two record ids.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Apply;

/**
 * Record map.
 */
final class RecordMap {

	public const TABLE = 'wiw_record_map';

	public const TABLE_DEFINITION = 'record_id varchar(64) NOT NULL,
		profile_id bigint(20) unsigned NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (record_id),
		UNIQUE KEY profile (profile_id)';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * The profile for a record id, or null.
	 *
	 * @param string $record_id Record id.
	 */
	public function profile_for( string $record_id ): ?int {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT profile_id FROM ' . self::table() . ' WHERE record_id = %s', $record_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return null === $id ? null : (int) $id;
	}

	/**
	 * The record id of a profile, or null.
	 *
	 * @param int $profile_id Profile post id.
	 */
	public function record_for( int $profile_id ): ?string {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT record_id FROM ' . self::table() . ' WHERE profile_id = %d', $profile_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return null === $id ? null : (string) $id;
	}

	/**
	 * Map a record id to a profile.
	 *
	 * @param string $record_id  Record id.
	 * @param int    $profile_id Profile post id.
	 * @throws \RuntimeException When either side is already mapped.
	 */
	public function link( string $record_id, int $profile_id ): void {
		global $wpdb;
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'record_id'  => $record_id,
				'profile_id' => $profile_id,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		if ( false === $ok ) {
			throw new \RuntimeException( 'That record or profile is already mapped.' );
		}
	}

	/**
	 * Remove the mapping of a profile (when an undo deletes it).
	 *
	 * @param int $profile_id Profile post id.
	 */
	public function unlink_profile( int $profile_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'profile_id' => $profile_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
