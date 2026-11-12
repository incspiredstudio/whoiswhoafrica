<?php
/**
 * Creates wiw_sources, the sources snapshot fed by each import manifest (REQ-136, REQ-243).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Import\Sources\SourceRepository;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111195';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_sources';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_sources', SourceRepository::TABLE_DEFINITION );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * No personal-data columns: a source is an organisation or a site, named by the acquisition side.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
