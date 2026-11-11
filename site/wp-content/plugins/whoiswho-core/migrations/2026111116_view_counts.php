<?php
/**
 * Creates wiw_view_counts_daily: profile views per profile per day (REQ-236). Holds a profile id, a day and a count; no IP
 * address, user agent or other identifier.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\Views\ViewCounter;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111116';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_view_counts_daily';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_view_counts_daily', ViewCounter::TABLE_DEFINITION );
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
