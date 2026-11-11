<?php
/**
 * The audit table through $wpdb. The append lock is a MySQL named lock, so it also serialises writers on other connections.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Audit;

/**
 * Connection on $wpdb.
 */
final class WpdbConnection implements Connection {

	/**
	 * Table name without the prefix.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Constructor.
	 *
	 * @param string $name Table name without the prefix.
	 */
	public function __construct( string $name = 'wiw_audit_log' ) {
		$this->name = $name;
	}

	/**
	 * Full table name.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . $this->name;
	}

	/**
	 * Lock name, unique per database and table.
	 */
	private function lock_name(): string {
		global $wpdb;
		return 'wiw_audit:' . $wpdb->dbname . ':' . $this->table();
	}

	/**
	 * Take the append lock, waiting up to ten seconds.
	 */
	public function lock(): bool {
		global $wpdb;
		return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $this->lock_name() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Release the append lock.
	 */
	public function unlock(): void {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $this->lock_name() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Hash of the newest row.
	 */
	public function last_hash(): ?string {
		global $wpdb;
		$hash = $wpdb->get_var( "SELECT hash FROM {$this->table()} ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB
		return is_string( $hash ) ? $hash : null;
	}

	/**
	 * Insert a row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @throws \RuntimeException When the row cannot be written.
	 */
	public function insert( array $row ): int {
		global $wpdb;
		$ok = $wpdb->insert( $this->table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $ok ) {
			throw new \RuntimeException( 'Audit row could not be written.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Rows after an id.
	 *
	 * @param int $after_id Id.
	 * @param int $limit    Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function rows( int $after_id, int $limit ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id > %d ORDER BY id ASC LIMIT %d", $after_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : array();
	}
}
