<?php
/**
 * The jobs:run command: runs due background jobs once within the time budget. Use it from a cPanel cron every minute when
 * DISABLE_WP_CRON is set. Prints counts only, never job arguments.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

use WhoIsWho\Core\Platform\Jobs\JobRunner;

/**
 * Jobs run command.
 */
final class JobsRun implements Command {

	/**
	 * Runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $runner;

	/**
	 * Constructor.
	 *
	 * @param JobRunner $runner Job runner.
	 */
	public function __construct( JobRunner $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Name.
	 */
	public function name(): string {
		return 'jobs:run';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Run due background jobs once (--budget=SECONDS, default 20).';
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments.
	 * @param Output   $output Output.
	 */
	public function run( array $args, Output $output ): int {
		$budget = JobRunner::DEFAULT_BUDGET;
		foreach ( $args as $arg ) {
			if ( 1 === preg_match( '/^--budget=(\d{1,3})$/', $arg, $m ) ) {
				$budget = max( 1, (int) $m[1] );
			}
		}
		$result = $this->runner->run_due( $budget );
		$output->line( 'Ran ' . $result['ran'] . ' job(s).' . ( $result['more'] ? ' More are due.' : '' ) );
		$failed = count( $this->runner->failed_jobs() );
		if ( $failed > 0 ) {
			$output->line( $failed . ' failed job(s) need attention.' );
		}
		return 0;
	}
}
