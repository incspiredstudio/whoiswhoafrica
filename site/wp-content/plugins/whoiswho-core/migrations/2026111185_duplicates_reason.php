<?php
/**
 * Adds the decision reason to the duplicate pairs (REQ-145): "not a duplicate" must say why. Additive: dbDelta adds the column.
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
		return '2026111185';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Add the reason column to wiw_duplicates';
	}

	/**
	 * Update the table.
	 */
	public function up(): void {
		Schema::create_table( DuplicateRepository::TABLE, DuplicateRepository::TABLE_DEFINITION );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * A staff reason; no personal data by rule.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
