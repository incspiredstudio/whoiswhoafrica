<?php
/**
 * The retention:run command: applies the retention periods once; --dry-run counts without changing anything. Prints counts only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

use WhoIsWho\Core\Platform\Retention\RetentionJob;

/**
 * Retention run command.
 */
final class RetentionRun implements Command {

	/**
	 * Name.
	 */
	public function name(): string {
		return 'retention:run';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Delete or anonymise data past its retention period (--dry-run counts only).';
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments.
	 * @param Output   $output Output.
	 */
	public function run( array $args, Output $output ): int {
		$dry    = in_array( '--dry-run', $args, true );
		$report = ( new RetentionJob() )->run( $dry );
		if ( array() === $report ) {
			$output->line( 'No retention period is set; nothing to do.' );
			return 0;
		}
		foreach ( $report as $class => $count ) {
			$output->line( $class . ': ' . $count . ( $dry ? ' would change' : ' changed' ) );
		}
		return 0;
	}
}
