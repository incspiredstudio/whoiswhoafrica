<?php
/**
 * The audit:verify command (REQ-051): checks the audit log's hash chain. Exit code 0 when intact, 1 when a row does not fit,
 * so a cron job or a monitor can alert on it. Prints the row id only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Audit verify command.
 */
final class AuditVerify implements Command {

	/**
	 * Logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger $audit Logger.
	 */
	public function __construct( AuditLogger $audit ) {
		$this->audit = $audit;
	}

	/**
	 * Name.
	 */
	public function name(): string {
		return 'audit:verify';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Check the audit log hash chain; exits 1 when a row has been altered.';
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments (none).
	 * @param Output   $output Output.
	 */
	public function run( array $args, Output $output ): int {
		unset( $args );
		$result = $this->audit->verify();
		if ( $result['intact'] ) {
			$output->line( 'Audit chain intact.' );
			return 0;
		}
		$output->line( 'Audit chain broken at row ' . (int) $result['broken_id'] . '.' );
		return 1;
	}
}
