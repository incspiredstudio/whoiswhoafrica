<?php
/**
 * The purge log: one row per purge attempt. Holds ids, a count and a result, never URLs or personal data.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache;

/**
 * Purge log.
 */
interface PurgeLog {

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		subject_type varchar(30) NOT NULL,
		subject_id bigint(20) unsigned NOT NULL,
		url_count int(10) unsigned NOT NULL,
		result varchar(12) NOT NULL,
		reason varchar(120) NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY subject (subject_type,subject_id),
		KEY created_at (created_at)';

	/**
	 * Record an attempt.
	 *
	 * @param string $subject_type Subject type, for example profile.
	 * @param int    $subject_id   Subject id.
	 * @param int    $url_count    How many URLs.
	 * @param string $result       ok, skipped or failed.
	 * @param string $reason       Short reason, for example unpublished or removed. Never personal data.
	 */
	public function add( string $subject_type, int $subject_id, int $url_count, string $result, string $reason = '' ): void;

	/**
	 * Newest rows first.
	 *
	 * @param int                                                $limit   Maximum rows.
	 * @param int                                                $offset  Offset.
	 * @param array{status?: string, from?: string, to?: string} $filters Result, and first and last day (Y-m-d).
	 * @return array<int, array{subject_type: string, subject_id: int, url_count: int, result: string, reason: string, created_at: string}>
	 */
	public function rows( int $limit = 50, int $offset = 0, array $filters = array() ): array;
}
