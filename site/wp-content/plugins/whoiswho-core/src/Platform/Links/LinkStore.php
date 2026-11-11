<?php
/**
 * Where signed links are kept. Only the SHA-256 hash of a token is ever stored. The production implementation is a table; tests use memory.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Links;

/**
 * Link store.
 */
interface LinkStore {

	/**
	 * Table definition used by the migration.
	 */
	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		token_hash char(64) NOT NULL,
		purpose varchar(60) NOT NULL,
		subject_type varchar(60) NOT NULL,
		subject_id varchar(64) NOT NULL,
		email varchar(254) NULL,
		created_at datetime NOT NULL,
		expires_at datetime NOT NULL,
		used_at datetime NULL,
		revoked_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token_hash (token_hash),
		KEY subject (subject_type, subject_id),
		KEY expires_at (expires_at)';

	/**
	 * Store a new link.
	 *
	 * @param array<string, mixed> $row Columns: token_hash, purpose, subject_type, subject_id, email, created_at, expires_at.
	 */
	public function insert( array $row ): void;

	/**
	 * Find a link by token hash.
	 *
	 * @param string $hash Token hash.
	 * @return array<string, mixed>|null
	 */
	public function find( string $hash ): ?array;

	/**
	 * Mark a link used, but only if it is still unused, unrevoked and unexpired. Atomic: of two concurrent calls only one wins.
	 *
	 * @param string $hash Token hash.
	 * @param string $now  Current UTC time, "Y-m-d H:i:s".
	 * @return bool Whether this call consumed it.
	 */
	public function consume( string $hash, string $now ): bool;

	/**
	 * Revoke every unused link for a subject and purpose.
	 *
	 * @param string $purpose      Purpose.
	 * @param string $subject_type Subject type.
	 * @param string $subject_id   Subject id.
	 * @param string $now          Current UTC time.
	 * @return int Links revoked.
	 */
	public function revoke_for( string $purpose, string $subject_type, string $subject_id, string $now ): int;

	/**
	 * Delete spent links: used, revoked or expired before a time.
	 *
	 * @param string $before UTC time.
	 * @return int Rows deleted.
	 */
	public function purge_spent( string $before ): int;
}
