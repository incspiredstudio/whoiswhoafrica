<?php
/**
 * The production queue: Action Scheduler, installed by composer as a library and loaded by the plugin. Jobs run under the
 * single hook JobRunner::HOOK with the job name and arguments; a high-priority group runs first.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Jobs;

/**
 * Action Scheduler queue.
 */
final class ActionSchedulerQueue implements JobQueue {

	public const GROUP_HIGH   = 'wiw-high';
	public const GROUP_NORMAL = 'wiw-normal';

	/**
	 * Add a job.
	 *
	 * @param string               $name      Job name.
	 * @param array<string, mixed> $args      Arguments.
	 * @param int                  $timestamp Earliest run time.
	 * @param bool                 $high      High-priority group.
	 */
	public function add( string $name, array $args, int $timestamp, bool $high ): int {
		return (int) as_schedule_single_action(
			$timestamp,
			JobRunner::HOOK,
			array( $name, $args ),
			$high ? self::GROUP_HIGH : self::GROUP_NORMAL,
			false,
			$high ? 1 : 10
		);
	}

	/**
	 * Ids of due jobs, high group first.
	 *
	 * @param int $now   Unix time.
	 * @param int $limit Maximum number.
	 * @return array<int, int>
	 */
	public function due( int $now, int $limit ): array {
		$ids = array();
		foreach ( array( self::GROUP_HIGH, self::GROUP_NORMAL ) as $group ) {
			if ( count( $ids ) >= $limit ) {
				break;
			}
			$found = \ActionScheduler::store()->query_actions(
				array(
					'hook'         => JobRunner::HOOK,
					'group'        => $group,
					'status'       => \ActionScheduler_Store::STATUS_PENDING,
					'date'         => new \DateTime( '@' . $now ),
					'date_compare' => '<=',
					'orderby'      => 'date',
					'order'        => 'ASC',
					'per_page'     => $limit - count( $ids ),
				)
			);
			foreach ( (array) $found as $id ) {
				$ids[] = (int) $id;
			}
		}
		return $ids;
	}

	/**
	 * Run one job now.
	 *
	 * @param int $id Queue id.
	 */
	public function run( int $id ): void {
		\ActionScheduler::runner()->process_action( (string) $id, 'wiw' );
	}

	/**
	 * Whether a job of this name is waiting.
	 *
	 * @param string $name Job name.
	 */
	public function has_pending( string $name ): bool {
		$actions = as_get_scheduled_actions(
			array(
				'hook'     => JobRunner::HOOK,
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 200,
			)
		);
		foreach ( $actions as $action ) {
			$args = $action->get_args();
			if ( ( $args[0] ?? '' ) === $name ) {
				return true;
			}
		}
		return false;
	}
}
