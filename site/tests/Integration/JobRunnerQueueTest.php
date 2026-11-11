<?php
/**
 * The job runner on the real Action Scheduler queue (REQ-050, REQ-209). Needs WordPress and a database.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Jobs\ActionSchedulerQueue;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Tests\TestCase;

final class JobRunnerQueueTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler is not installed (run composer install).' );
		}
		\as_unschedule_all_actions( JobRunner::HOOK );
		delete_option( JobRunner::OPTION_HEARTBEAT );
		delete_option( JobRunner::OPTION_FAILED );
	}

	protected function tearDown(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( JobRunner::HOOK );
		}
		parent::tearDown();
	}

	public function test_REQ_209_queued_jobs_run_on_action_scheduler_high_group_first(): void {
		$runner = JobRunner::for_site();
		$order  = array();
		foreach ( array( 'report', 'purge' ) as $name ) {
			$runner->handle(
				$name,
				static function () use ( &$order, $name ): void {
					$order[] = $name;
				}
			);
		}

		$runner->enqueue( 'report' );
		$runner->enqueue( 'purge', array( 'profile_id' => 5 ), true );

		$result = $runner->run_due( 30 );

		$this->assertSame( 2, $result['ran'] );
		$this->assertSame( array( 'purge', 'report' ), $order );
		$this->assertGreaterThan( 0, $runner->heartbeat() );
	}

	public function test_REQ_050_failures_on_the_real_queue_are_retried_with_backoff(): void {
		$runner = JobRunner::for_site();
		$runner->handle(
			'boom',
			static function (): void {
				throw new \RuntimeException( 'fails' );
			}
		);

		$runner->enqueue( 'boom' );
		$runner->run_due( 30 );

		$this->assertTrue( ( new ActionSchedulerQueue() )->has_pending( 'boom' ), 'A retry is waiting.' );
		$this->assertSame( array(), $runner->failed_jobs() );
	}
}
