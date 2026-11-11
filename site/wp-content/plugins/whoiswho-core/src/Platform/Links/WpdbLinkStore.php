<?php
/**
 * Signed links in the wiw_signed_links table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Links;

/**
 * Table-backed link store.
 */
final class WpdbLinkStore implements LinkStore {

	/**
	 * Full table name.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wiw_signed_links';
	}

	/**
	 * Store a new link.
	 *
	 * @param array<string, mixed> $row Columns.
	 */
	public function insert( array $row ): void {
		global $wpdb;
		$wpdb->insert( $this->table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Find a link.
	 *
	 * @param string $hash Token hash.
	 * @return array<string, mixed>|null
	 */
	public function find( string $hash ): ?array {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", $hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Consume atomically.
	 *
	 * @param string $hash Token hash.
	 * @param string $now  UTC time.
	 */
	public function consume( string $hash, string $now ): bool {
		global $wpdb;
		$table    = $this->table();
		$affected = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET used_at = %s WHERE token_hash = %s AND used_at IS NULL AND revoked_at IS NULL AND expires_at > %s", $now, $hash, $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return 1 === $affected;
	}

	/**
	 * Revoke unused links.
	 *
	 * @param string $purpose      Purpose.
	 * @param string $subject_type Subject type.
	 * @param string $subject_id   Subject id.
	 * @param string $now          UTC time.
	 */
	public function revoke_for( string $purpose, string $subject_type, string $subject_id, string $now ): int {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET revoked_at = %s WHERE purpose = %s AND subject_type = %s AND subject_id = %s AND used_at IS NULL AND revoked_at IS NULL", $now, $purpose, $subject_type, $subject_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Delete spent links.
	 *
	 * @param string $before UTC time.
	 */
	public function purge_spent( string $before ): int {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %s OR (used_at IS NOT NULL AND used_at < %s) OR (revoked_at IS NOT NULL AND revoked_at < %s)", $before, $before, $before ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
