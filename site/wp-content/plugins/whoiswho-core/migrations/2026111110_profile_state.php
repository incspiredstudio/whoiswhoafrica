<?php
/**
 * Creates wiw_profile_state: one row per profile holding its publication state and filter columns (REQ-123, REQ-242).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\State\Tables;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111110';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_profile_state';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_profile_state', Tables::STATE );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * No personal-data columns: ids, states and a source name.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
