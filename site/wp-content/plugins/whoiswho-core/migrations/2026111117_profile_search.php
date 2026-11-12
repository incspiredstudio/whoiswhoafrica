<?php
/**
 * Creates wiw_profile_search: the flattened FULLTEXT table holding only public profiles (REQ-254, REQ-208). It holds
 * public text only; nothing in it is personal data beyond what the public profile already shows.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\Search\SearchIndexer;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111117';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_profile_search';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( SearchIndexer::TABLE, SearchIndexer::TABLE_DEFINITION );
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
