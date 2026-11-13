<?php
/**
 * The profile picker of the "Add profile to nominations" form (REQ-267): a search by name over existing profiles that are
 * reviewed or pending review, nothing else. A draft, an unpublished or a removed profile is never offered, and there is no way
 * to type a new name.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards\Admin;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;

/**
 * Profile picker.
 */
final class ProfilePicker {

	public const LIMIT = 20;

	/**
	 * Existing profiles whose name contains the text.
	 *
	 * @param string $query Search text.
	 * @return array<int, array{id: int, name: string, state: string, type: string}>
	 */
	public function search( string $query ): array {
		global $wpdb;
		$query = trim( $query );
		if ( mb_strlen( $query ) < 2 ) {
			return array();
		}
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT p.ID, p.post_title, s.pub_state, s.entity_type FROM ' . $wpdb->posts . ' p INNER JOIN ' . StateRepository::table() . ' s ON s.profile_id = p.ID WHERE p.post_type = %s AND s.pub_state IN ( %s, %s ) AND p.post_title LIKE %s ORDER BY p.post_title ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table names.
				ProfileType::POST_TYPE,
				PubState::PUBLISHED_REVIEWED,
				PubState::PUBLISHED_PENDING,
				'%' . $wpdb->esc_like( $query ) . '%',
				self::LIMIT
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id'    => (int) $row['ID'],
				'name'  => (string) $row['post_title'],
				'state' => (string) $row['pub_state'],
				'type'  => (string) $row['entity_type'],
			);
		}
		return $out;
	}
}
