<?php
/**
 * The command registry and dispatcher behind bin/wiw.php. Exit codes: 0 success, 1 failure, 2 usage (unknown command).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

use WhoIsWho\Core\Platform\Migrations\Runner;

/**
 * Application.
 */
final class Application {

	/**
	 * Registered commands by name.
	 *
	 * @var array<string, Command>
	 */
	private array $commands = array();

	/**
	 * Register a command.
	 *
	 * @param Command $command Command.
	 */
	public function add( Command $command ): void {
		$this->commands[ $command->name() ] = $command;
	}

	/**
	 * The application with the commands this plugin ships. Other code adds more with the wiw_cli_commands filter.
	 */
	public static function for_site(): self {
		$app = new self();
		$app->add( new MigrateCommand( Runner::for_site() ) );
		$app->add( new JobsRun( \WhoIsWho\Core\Platform\Jobs\JobRunner::for_site() ) );
		$app->add( new BackupRecord() );
		$app->add( new AuditVerify( \WhoIsWho\Core\Platform\Audit\AuditLogger::for_site() ) );
		foreach ( (array) apply_filters( 'wiw_cli_commands', array() ) as $command ) {
			if ( $command instanceof Command ) {
				$app->add( $command );
			}
		}
		return $app;
	}

	/**
	 * Whether this PHP runs as a command line process. Anything else (a web server) is refused.
	 *
	 * @param string $sapi PHP_SAPI.
	 */
	public static function is_cli( string $sapi ): bool {
		return 'cli' === $sapi || 'phpdbg' === $sapi;
	}

	/**
	 * Registered command names, sorted.
	 *
	 * @return string[]
	 */
	public function names(): array {
		$names = array_keys( $this->commands );
		sort( $names );
		return $names;
	}

	/**
	 * Run a command line.
	 *
	 * @param string[] $argv   Arguments as PHP gives them: script name first.
	 * @param Output   $output Output.
	 * @return int Exit code.
	 */
	public function run( array $argv, Output $output ): int {
		$name = $argv[1] ?? '';
		if ( '' === $name || 'help' === $name || '--help' === $name ) {
			$this->help( $output );
			return 0;
		}
		if ( ! isset( $this->commands[ $name ] ) ) {
			$output->line( 'Unknown command: ' . $name );
			$this->help( $output );
			return 2;
		}
		try {
			return $this->commands[ $name ]->run( array_slice( $argv, 2 ), $output );
		} catch ( \Throwable $e ) {
			// The class only: messages can carry personal data.
			$output->line( 'Command failed (' . get_class( $e ) . ').' );
			return 1;
		}
	}

	/**
	 * Print the command list.
	 *
	 * @param Output $output Output.
	 */
	private function help( Output $output ): void {
		$output->line( 'Usage: php bin/wiw.php <command> [options]' );
		$output->line( 'Commands:' );
		foreach ( $this->names() as $name ) {
			$output->line( '  ' . str_pad( $name, 12 ) . $this->commands[ $name ]->description() );
		}
	}
}
