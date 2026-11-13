<?php
/**
 * The sample:remove command: deletes everything sample:seed made (marked `_wiw_sample`) and puts back the settings it changed.
 * Local and staging only; refuses in production. Safe to run again.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

use WhoIsWho\Core\Platform\Cli\Sample\SampleRemover;

/**
 * Sample remove command.
 */
final class SampleRemoveCommand implements Command {

	/**
	 * Environment type; the site's by default.
	 *
	 * @var string
	 */
	private string $environment;

	/**
	 * Constructor.
	 *
	 * @param string|null $environment Environment type (tests pass one).
	 */
	public function __construct( ?string $environment = null ) {
		$this->environment = $environment ?? wp_get_environment_type();
	}

	/**
	 * Name.
	 */
	public function name(): string {
		return 'sample:remove';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Delete the sample content that sample:seed made (local and staging only).';
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments.
	 * @param Output   $output Output.
	 */
	public function run( array $args, Output $output ): int {
		unset( $args );
		$refusal = SampleSeedCommand::refusal( $this->environment );
		if ( null !== $refusal ) {
			$output->line( $refusal );
			return 1;
		}
		$actor = SampleSeedCommand::administrator();
		if ( $actor < 1 ) {
			$output->line( 'No Administrator user was found.' );
			return 1;
		}
		( new SampleRemover( $actor ) )->remove( $output );
		$output->line( 'Done.' );
		return 0;
	}
}
