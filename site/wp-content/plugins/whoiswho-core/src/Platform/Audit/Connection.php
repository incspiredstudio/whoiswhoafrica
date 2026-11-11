<?php
/**
 * The few database operations the audit log needs. Keeping them behind an interface lets the chain logic be plain PHP, so it
 * can be tested with separate processes. Only AuditLogger uses a Connection to write.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Audit;

/**
 * Audit table access.
 */
interface Connection {

	/**
	 * Take the exclusive append lock. Returns false if it could not be had in time.
	 */
	public function lock(): bool;

	/**
	 * Release the append lock.
	 */
	public function unlock(): void;

	/**
	 * Hash of the newest row, or null when the log is empty.
	 */
	public function last_hash(): ?string;

	/**
	 * Add one row and return its id.
	 *
	 * @param array<string, mixed> $row Column => value.
	 */
	public function insert( array $row ): int;

	/**
	 * Rows in id order, starting after an id.
	 *
	 * @param int $after_id Return rows with a larger id.
	 * @param int $limit    Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function rows( int $after_id, int $limit ): array;
}
