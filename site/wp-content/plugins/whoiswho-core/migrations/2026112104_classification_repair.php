<?php
/**
 * Repairs the classification on sites that already ran 2026112103 with the shorter 0.3.0 alias lists (REQ-009, REQ-056): profiles
 * left Unclassified, and organisations or institutions left without an organisation type or with only "other", are matched again
 * from their stored raw text with the longer aliases and the comma-list matching. A classification staff chose, or that the
 * acquisition side decided, is never changed. Forward only and safe to run again. Only the counts are logged.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\ClassificationUpgrade;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026112104';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Classification repair: match unclassified profiles and missing organisation types again';
	}

	/**
	 * Repair and log the counts.
	 */
	public function up(): void {
		$counts = ( new ClassificationUpgrade() )->repair();
		Logger::for_site()->log(
			'info',
			'Profile classification repaired',
			array(
				'profiles_count'            => $counts['profiles'],
				'industries_repaired_count' => $counts['industries_repaired'],
				'org_types_repaired_count'  => $counts['org_types_repaired'],
				'kept_as_set_count'         => $counts['kept_as_set'],
			)
		);
	}

	/**
	 * Not a schema contraction.
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
