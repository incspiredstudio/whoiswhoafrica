<?php
/**
 * Moves the site onto the classification tree (REQ-009 as amended 2026-11-14): seeds sectors, industries, Unclassified,
 * organisation types and the Pan-African place; classifies existing profiles through the aliases (or Unclassified) and sets their
 * primary industry and country; folds the old flat industry and organisation type terms into the tree; empties the retired sector
 * taxonomy. Forward only and safe to run again. Only the counts are logged.
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
		return '2026112103';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Classification tree: seed it and classify existing profiles';
	}

	/**
	 * Seed, classify and log the counts.
	 */
	public function up(): void {
		$counts = ( new ClassificationUpgrade() )->run();
		Logger::for_site()->log(
			'info',
			'Profiles moved onto the classification tree',
			array(
				'profiles_count'        => $counts['profiles'],
				'classified_count'      => $counts['classified'],
				'unclassified_count'    => $counts['unclassified'],
				'terms_merged_count'    => $counts['terms_merged'],
				'terms_removed_count'   => $counts['terms_removed'],
				'sectors_removed_count' => $counts['sectors_removed'],
			)
		);
	}

	/**
	 * Not a schema contraction: the terms it removes are folded into the tree first.
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
