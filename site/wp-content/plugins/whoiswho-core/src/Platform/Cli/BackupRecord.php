<?php
/**
 * The backup:record command: the host backup job calls it after a good backup so the backup-age check stays quiet.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

use WhoIsWho\Core\Platform\Monitoring\BackupCheck;

/**
 * Backup record command.
 */
final class BackupRecord implements Command {

	/**
	 * Name.
	 */
	public function name(): string {
		return 'backup:record';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Record that a backup just finished (clears the backup-age warning).';
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments.
	 * @param Output   $output Output.
	 */
	public function run( array $args, Output $output ): int {
		unset( $args );
		( new BackupCheck() )->record();
		$output->line( 'Backup time recorded.' );
		return 0;
	}
}
