<?php
/**
 * Adds the reason column to the purge log (REQ-209, REQ-244). Additive: dbDelta adds the column and leaves rows alone.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Cache\PurgeLog;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111183';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Add the reason column to wiw_purge_log';
	}

	/**
	 * Update the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_purge_log', PurgeLog::TABLE_DEFINITION );
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
