<?php
/**
 * Job runner: retries, priority, time budget, heartbeat and the jobs:run command (REQ-050, REQ-209, REQ-075).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Cli\JobsRun;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class JobRunnerTest extends TestCase {

	/**
	 * Fake clock.
	 *
	 * @var \stdClass
	 */
	private \stdClass $clock;

	/**
	 * Fake option storage.
	 *
	 * @var \ArrayObject<string, mixed>
	 */
	private \ArrayObject $options;

	/**
	 * Queue.
	 *
	 * @var MemoryQueue
	 */
	private MemoryQueue $queue;

	protected function setUp(): void {
		parent::setUp();
		$this->clock    = new \stdClass();
		$this->clock->t = 1000000;
		$this->options  = new \ArrayObject();
		$this->queue    = new MemoryQueue();
	}

	private function runner(): JobRunner {
		$options             = $this->options;
		$clock               = $this->clock;
		$runner              = new JobRunner(
			$this->queue,
			static fn (): int => $clock->t,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$this->queue->runner = $runner;
		return $runner;
	}

	public function test_REQ_050_failed_job_retries_then_is_reported(): void {
		$runner   = $this->runner();
		$attempts = 0;
		$runner->handle(
			'flaky',
			static function () use ( &$attempts ): void {
				++$attempts;
				throw new \RuntimeException( 'jane@example.org must not leak' );
			}
		);
		$runner->enqueue( 'flaky', array( 'profile_id' => 7 ) );

		for ( $i = 0; $i < 10; $i++ ) {
			$runner->run_due( 60 );
			$this->clock->t += 3600;
		}

		$this->assertSame( JobRunner::MAX_ATTEMPTS, $attempts );
		$this->assertSame( array(), $this->queue->jobs, 'No retry is left once the maximum is reached.' );
		$failed = $runner->failed_jobs();
		$this->assertCount( 1, $failed );
		$this->assertSame( 'flaky', $failed[0]['job'] );
		$this->assertSame( JobRunner::MAX_ATTEMPTS, $failed[0]['attempts'] );
		$json = (string) json_encode( $failed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$this->assertStringNotContainsString( 'jane@example.org', $json );
		$this->assertArrayNotHasKey( 'args', $failed[0] );

		$runner->clear_failed();
		$this->assertSame( array(), $runner->failed_jobs() );
	}

	public function test_REQ_050_a_job_that_succeeds_on_retry_is_not_reported(): void {
		$runner = $this->runner();
		$calls  = 0;
		$runner->handle(
			'once-flaky',
			static function () use ( &$calls ): void {
				++$calls;
				if ( 1 === $calls ) {
					throw new \RuntimeException( 'first try fails' );
				}
			}
		);
		$runner->enqueue( 'once-flaky' );
		$runner->run_due( 60 );
		$this->assertCount( 1, $this->queue->jobs, 'A retry is queued with a delay.' );
		$this->assertSame( 0, $runner->run_due( 60 )['ran'], 'The retry waits for its backoff.' );
		$this->clock->t += 600;
		$runner->run_due( 60 );
		$this->assertSame( 2, $calls );
		$this->assertSame( array(), $runner->failed_jobs() );
	}

	public function test_REQ_209_high_priority_group_runs_before_normal(): void {
		$runner = $this->runner();
		$order  = array();
		foreach ( array( 'report', 'purge', 'email' ) as $name ) {
			$runner->handle(
				$name,
				static function () use ( &$order, $name ): void {
					$order[] = $name;
				}
			);
		}
		$runner->enqueue( 'report' );
		$runner->enqueue( 'purge', array(), true );
		$runner->enqueue( 'email', array(), true );

		$runner->run_due( 60 );
		$this->assertSame( array( 'purge', 'email', 'report' ), $order );
	}

	public function test_REQ_050_run_respects_time_budget(): void {
		$runner = $this->runner();
		$clock  = $this->clock;
		$done   = 0;
		$runner->handle(
			'slow',
			static function () use ( &$done, $clock ): void {
				++$done;
				$clock->t += 10;
			}
		);
		for ( $i = 0; $i < 5; $i++ ) {
			$runner->enqueue( 'slow' );
		}

		$first = $runner->run_due( 20 );
		$this->assertSame(
			array(
				'ran'  => 2,
				'more' => true,
			),
			$first
		);
		$this->assertCount( 3, $this->queue->jobs );

		$second = $runner->run_due( 100 );
		$this->assertSame( 3, $second['ran'] );
		$this->assertFalse( $second['more'] );
		$this->assertSame( 5, $done );
	}

	public function test_REQ_075_heartbeat_updates_each_run_and_recurring_jobs_requeue(): void {
		$runner = $this->runner();
		$this->assertSame( 0, $runner->heartbeat() );

		$ticks = 0;
		$runner->recurring(
			'tick',
			300,
			static function () use ( &$ticks ): void {
				++$ticks;
			}
		);
		$runner->ensure_recurring();
		$runner->ensure_recurring();
		$this->assertCount( 1, $this->queue->jobs, 'Ensuring twice does not double-queue.' );

		$runner->run_due( 60 );
		$this->assertSame( 1000000, $runner->heartbeat() );
		$this->assertSame( 1, $ticks );
		$this->assertCount( 1, $this->queue->jobs, 'The next run is queued after each run.' );

		$this->clock->t += 301;
		$runner->run_due( 60 );
		$this->assertSame( 1000301, $runner->heartbeat() );
		$this->assertSame( 2, $ticks );
	}

	public function test_REQ_050_jobs_run_command_processes_due_jobs(): void {
		$runner = $this->runner();
		$ran    = 0;
		$runner->handle(
			'work',
			static function () use ( &$ran ): void {
				++$ran;
			}
		);
		$runner->enqueue( 'work' );
		$runner->enqueue( 'work' );

		$output = new Output( false );
		$code   = ( new JobsRun( $runner ) )->run( array( '--budget=30' ), $output );

		$this->assertSame( 0, $code );
		$this->assertSame( 2, $ran );
		$this->assertSame( array( 'Ran 2 job(s).' ), $output->lines() );
	}
}
