<?php
/**
 * The migrate command: applies pending migrations, or lists them with --dry-run. In production it runs only when the deploy script sets
 * WIW_DEPLOY_MIGRATE=1 (the runner enforces that).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

use WhoIsWho\Core\Platform\Migrations\Runner;

/**
 * Migrate command.
 */
final class MigrateCommand implements Command {

	/**
	 * Runner.
	 *
	 * @var Runner
	 */
	private Runner $runner;

	/**
	 * Constructor.
	 *
	 * @param Runner $runner Migration runner.
	 */
	public function __construct( Runner $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Name.
	 */
	public function name(): string {
		return 'migrate';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Apply pending migrations (--dry-run lists them without applying).';
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments.
	 * @param Output   $output Output.
	 */
	public function run( array $args, Output $output ): int {
		if ( in_array( '--dry-run', $args, true ) ) {
			$pending = $this->runner->pending();
			$output->line( 'Current version: ' . $this->runner->current_version() );
			if ( array() === $pending ) {
				$output->line( 'Nothing to apply.' );
				return 0;
			}
			foreach ( $pending as $migration ) {
				$output->line( 'Pending ' . $migration->version() . ': ' . $migration->description() . ( $migration->is_contract() ? ' [contract]' : '' ) );
			}
			return 0;
		}

		$result = $this->runner->run();
		foreach ( $result['applied'] as $version ) {
			$output->line( 'Applied ' . $version );
		}
		if ( null !== $result['error'] ) {
			$output->line( 'Error: ' . $result['error'] );
			return 1;
		}
		$output->line( array() === $result['applied'] ? 'Nothing to apply.' : 'Done. Version ' . $this->runner->current_version() );
		return 0;
	}
}
