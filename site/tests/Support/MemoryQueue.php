<?php
/**
 * In-memory job queue for tests.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Support;

use WhoIsWho\Core\Platform\Jobs\JobQueue;
use WhoIsWho\Core\Platform\Jobs\JobRunner;

/**
 * Memory queue.
 */
final class MemoryQueue implements JobQueue {

	/**
	 * Waiting jobs by id.
	 *
	 * @var array<int, array{name: string, args: array<string, mixed>, at: int, high: bool}>
	 */
	public array $jobs = array();

	/**
	 * Runner to hand jobs to.
	 *
	 * @var JobRunner|null
	 */
	public ?JobRunner $runner = null;

	/**
	 * Next id.
	 *
	 * @var int
	 */
	private int $next = 1;

	/**
	 * Add.
	 *
	 * @param string               $name      Name.
	 * @param array<string, mixed> $args      Args.
	 * @param int                  $timestamp Time.
	 * @param bool                 $high      High priority.
	 */
	public function add( string $name, array $args, int $timestamp, bool $high ): int {
		$id                = $this->next++;
		$this->jobs[ $id ] = array(
			'name' => $name,
			'args' => $args,
			'at'   => $timestamp,
			'high' => $high,
		);
		return $id;
	}

	/**
	 * Due ids, high first, then by time.
	 *
	 * @param int $now   Now.
	 * @param int $limit Limit.
	 * @return array<int, int>
	 */
	public function due( int $now, int $limit ): array {
		$due = array_filter( $this->jobs, static fn ( array $job ): bool => $job['at'] <= $now );
		uasort(
			$due,
			static fn ( array $a, array $b ): int => array( $b['high'], $a['at'] ) <=> array( $a['high'], $b['at'] )
		);
		return array_slice( array_keys( $due ), 0, $limit );
	}

	/**
	 * Run.
	 *
	 * @param int $id Id.
	 */
	public function run( int $id ): void {
		$job = $this->jobs[ $id ];
		unset( $this->jobs[ $id ] );
		if ( null !== $this->runner ) {
			$this->runner->execute( $job['name'], $job['args'] );
		}
	}

	/**
	 * Whether a job of this name waits.
	 *
	 * @param string $name Name.
	 */
	public function has_pending( string $name ): bool {
		foreach ( $this->jobs as $job ) {
			if ( $job['name'] === $name ) {
				return true;
			}
		}
		return false;
	}
}
