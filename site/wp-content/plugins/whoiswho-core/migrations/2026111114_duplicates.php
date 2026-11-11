<?php
/**
 * Creates wiw_duplicates: pairs of probable duplicate profiles with a score, the basis of the match and a status (REQ-123,
 * REQ-145). Holds no personal data: two profile ids, a score, a basis code and a decision.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Review\Duplicates\DuplicateRepository;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111114';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_duplicates';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_duplicates', DuplicateRepository::TABLE_DEFINITION );
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
