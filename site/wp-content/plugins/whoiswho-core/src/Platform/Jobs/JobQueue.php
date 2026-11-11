<?php
/**
 * Where scheduled jobs wait. The production implementation sits on Action Scheduler; tests use an in-memory queue, so the
 * retry, budget and priority rules in JobRunner are tested without WordPress.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Jobs;

/**
 * Job queue.
 */
interface JobQueue {

	/**
	 * Add a job to run at or after a time.
	 *
	 * @param string               $name      Job name.
	 * @param array<string, mixed> $args      Arguments (ids and cursors only, never personal data).
	 * @param int                  $timestamp Earliest run time (Unix).
	 * @param bool                 $high      High-priority group (cache purge, email).
	 * @return int Queue id.
	 */
	public function add( string $name, array $args, int $timestamp, bool $high ): int;

	/**
	 * Ids of jobs due now, high priority first, then by time.
	 *
	 * @param int $now   Unix time.
	 * @param int $limit Maximum number.
	 * @return array<int, int>
	 */
	public function due( int $now, int $limit ): array;

	/**
	 * Run one queued job now. The implementation hands it to JobRunner::execute() and marks it done.
	 *
	 * @param int $id Queue id.
	 */
	public function run( int $id ): void;

	/**
	 * Whether a job of this name is waiting.
	 *
	 * @param string $name Job name.
	 */
	public function has_pending( string $name ): bool;
}
