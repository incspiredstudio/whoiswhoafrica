<?php
/**
 * Repairs profile list fields stored without their backslashes by releases before 0.2.0 (issue 806, REQ-139, REQ-143). Forward
 * only and safe to run again: a healthy value is left alone, a value that cannot be repaired safely is left as it is and counted.
 * Only the counts are logged.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\Fields\ListMetaRepair;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026112101';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Repair profile list fields that lost their backslashes';
	}

	/**
	 * Repair what is safely recoverable and log the counts.
	 */
	public function up(): void {
		$counts = ( new ListMetaRepair() )->run();
		Logger::for_site()->log(
			'info',
			'Profile list fields checked for lost backslashes',
			array(
				'checked_count'       => $counts['checked'],
				'repaired_count'      => $counts['repaired'],
				'unrecoverable_count' => $counts['unrecoverable'],
			)
		);
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * No personal-data columns.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
