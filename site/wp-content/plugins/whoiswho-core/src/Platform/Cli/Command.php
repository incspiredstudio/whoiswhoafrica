<?php
/**
 * One command of bin/wiw.php. Later issues register their own (jobs:run, audit:verify, import) through the wiw_cli_commands filter.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

/**
 * Command contract.
 */
interface Command {

	/**
	 * The name typed after bin/wiw.php, for example "migrate".
	 */
	public function name(): string;

	/**
	 * One line for the help list.
	 */
	public function description(): string;

	/**
	 * Run the command. Print identifiers only, never personal data.
	 *
	 * @param string[] $args   Arguments after the command name.
	 * @param Output   $output Output.
	 * @return int Exit code: 0 success, 1 failure, 2 usage.
	 */
	public function run( array $args, Output $output ): int;
}
