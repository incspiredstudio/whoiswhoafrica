<?php
/**
 * The search:rebuild command: rebuilds wiw_profile_search from public profiles only (REQ-254). Prints a count only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Search;

use WhoIsWho\Core\Platform\Cli\Command;
use WhoIsWho\Core\Platform\Cli\Output;

/**
 * Search rebuild command.
 */
final class SearchRebuildCommand implements Command {

	/**
	 * Name.
	 */
	public function name(): string {
		return 'search:rebuild';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Rebuild the search index from public profiles.';
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments.
	 * @param Output   $output Output.
	 */
	public function run( array $args, Output $output ): int {
		unset( $args );
		$count = ( new SearchIndexer() )->rebuild();
		$output->line( 'Indexed profiles: ' . $count );
		return 0;
	}
}
