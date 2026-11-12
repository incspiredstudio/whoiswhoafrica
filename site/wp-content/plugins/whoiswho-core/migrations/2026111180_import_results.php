<?php
/**
 * Creates wiw_import_results (one result code per record per batch, no field values) and wiw_record_map (the stable record_id
 * to profile post id map) for the importer (REQ-237, REQ-221, contract section 12 and unknown U6).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111180';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_import_results and wiw_record_map';
	}

	/**
	 * Create the tables.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_import_results', ResultRepository::TABLE_DEFINITION );
		Schema::create_table( 'wiw_record_map', RecordMap::TABLE_DEFINITION );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * No personal-data columns: record ids, codes and post ids.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
