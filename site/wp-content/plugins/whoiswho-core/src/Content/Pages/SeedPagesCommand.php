<?php
/**
 * The pages:seed command: creates the starter pages that do not exist yet. Prints paths only (REQ-260).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Pages;

use WhoIsWho\Core\Platform\Cli\Command;
use WhoIsWho\Core\Platform\Cli\Output;

/**
 * Seed pages command.
 */
final class SeedPagesCommand implements Command {

	/**
	 * Name.
	 */
	public function name(): string {
		return 'pages:seed';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create the starter pages with draft copy. Never overwrites an existing page.';
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments.
	 * @param Output   $output Output.
	 */
	public function run( array $args, Output $output ): int {
		unset( $args );
		$result = ( new StarterPages() )->seed();
		foreach ( $result['created'] as $path ) {
			$output->line( 'created ' . $path );
		}
		foreach ( $result['skipped'] as $path ) {
			$output->line( 'kept ' . $path );
		}
		return 0;
	}
}
