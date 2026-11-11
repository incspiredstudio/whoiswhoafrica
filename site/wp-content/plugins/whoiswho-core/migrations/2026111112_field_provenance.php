<?php
/**
 * Creates wiw_field_provenance: for each profile field, its source URL, retrieval date, confidence and method (REQ-142).
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
		return '2026111112';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_field_provenance';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_field_provenance', Tables::PROVENANCE );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * Source URLs of public records, no personal data.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
