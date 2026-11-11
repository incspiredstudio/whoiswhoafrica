<?php
/**
 * Creates wiw_import_batches: one row per import batch with the contract version, file hash, settings snapshot, status and
 * progress cursor (REQ-237). Holds no personal data: ids, hashes, counts and a file name.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111113';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_import_batches';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_import_batches', BatchRepository::TABLE_DEFINITION );
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
