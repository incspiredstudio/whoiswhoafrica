<?php
/**
 * In-memory link store for tests.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Support;

use WhoIsWho\Core\Platform\Links\LinkStore;

/**
 * Memory link store.
 */
final class MemoryLinkStore implements LinkStore {

	/**
	 * Rows by hash.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $rows = array();

	/**
	 * Insert.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	public function insert( array $row ): void {
		$this->rows[ $row['token_hash'] ] = $row + array(
			'used_at'    => null,
			'revoked_at' => null,
		);
	}

	/**
	 * Find.
	 *
	 * @param string $hash Hash.
	 * @return array<string, mixed>|null
	 */
	public function find( string $hash ): ?array {
		return $this->rows[ $hash ] ?? null;
	}

	/**
	 * Consume.
	 *
	 * @param string $hash Hash.
	 * @param string $now  Now.
	 */
	public function consume( string $hash, string $now ): bool {
		$row = $this->rows[ $hash ] ?? null;
		if ( null === $row || null !== $row['used_at'] || null !== $row['revoked_at'] || $row['expires_at'] <= $now ) {
			return false;
		}
		$this->rows[ $hash ]['used_at'] = $now;
		return true;
	}

	/**
	 * Revoke.
	 *
	 * @param string $purpose      Purpose.
	 * @param string $subject_type Type.
	 * @param string $subject_id   Id.
	 * @param string $now          Now.
	 */
	public function revoke_for( string $purpose, string $subject_type, string $subject_id, string $now ): int {
		$count = 0;
		foreach ( $this->rows as $hash => $row ) {
			if ( $row['purpose'] === $purpose && $row['subject_type'] === $subject_type && $row['subject_id'] === $subject_id && null === $row['used_at'] && null === $row['revoked_at'] ) {
				$this->rows[ $hash ]['revoked_at'] = $now;
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Purge.
	 *
	 * @param string $before Before.
	 */
	public function purge_spent( string $before ): int {
		$count = 0;
		foreach ( $this->rows as $hash => $row ) {
			if ( $row['expires_at'] < $before ) {
				unset( $this->rows[ $hash ] );
				++$count;
			}
		}
		return $count;
	}
}
