<?php
/**
 * Append-only version history of a profile (REQ-125). There is no update or delete method here, and database triggers
 * (installed by the migration) reject UPDATE and DELETE on the table. A pending revision of a claimed profile is a row with
 * approval_status "pending"; deciding it adds a new row with revision_of pointing at it and status "approved" or "rejected".
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\State;

/**
 * Version table access.
 */
final class VersionRepository {

	public const TABLE = 'wiw_profile_versions';

	public const VIA = array( 'edit', 'import', 'claim', 'restore', 'revision', 'decision' );

	public const APPROVAL = array( 'pending', 'approved', 'rejected' );

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Add one version.
	 *
	 * @param int                  $profile_id Profile post id.
	 * @param int                  $author_id  Author user id; 0 for an import.
	 * @param string               $via        One of VIA.
	 * @param string               $reason     Why (required for staff edits, may be empty for imports).
	 * @param array<string, mixed> $old_value  Field values before.
	 * @param array<string, mixed> $new_value  Field values after.
	 * @param string|null          $approval   One of APPROVAL, or null when no approval applies.
	 * @param int|null             $revision_of Row id of the pending revision this row decides.
	 * @return int The new row id.
	 * @throws \InvalidArgumentException On an unknown value.
	 * @throws \RuntimeException When the row cannot be written.
	 */
	public function append( int $profile_id, int $author_id, string $via, string $reason, array $old_value, array $new_value, ?string $approval = null, ?int $revision_of = null ): int {
		global $wpdb;
		if ( ! in_array( $via, self::VIA, true ) ) {
			throw new \InvalidArgumentException( 'Unknown way of creating a version.' );
		}
		if ( null !== $approval && ! in_array( $approval, self::APPROVAL, true ) ) {
			throw new \InvalidArgumentException( 'Unknown approval status.' );
		}
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'profile_id'      => $profile_id,
				'author_id'       => $author_id,
				'created_via'     => $via,
				'reason'          => '' === $reason ? null : $reason,
				'created_at'      => gmdate( 'Y-m-d H:i:s' ),
				'approval_status' => $approval,
				'revision_of'     => $revision_of,
				'old_value'       => wp_json_encode( $old_value ),
				'new_value'       => wp_json_encode( $new_value ),
			)
		);
		if ( false === $ok ) {
			throw new \RuntimeException( 'Could not write the version.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Versions of one profile, newest first.
	 *
	 * @param int $profile_id Profile post id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_profile( int $profile_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE profile_id = %d ORDER BY id DESC', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Pending revisions of a profile that have not been decided.
	 *
	 * @param int $profile_id Profile post id.
	 * @return array<int, array<string, mixed>>
	 */
	public function pending_for_profile( int $profile_id ): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT p.* FROM {$table} p WHERE p.profile_id = %d AND p.approval_status = 'pending' AND NOT EXISTS (SELECT 1 FROM {$table} d WHERE d.revision_of = p.id) ORDER BY p.id ASC", $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $rows ) ? $rows : array();
	}
}
