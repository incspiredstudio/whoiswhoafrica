<?php
/**
 * Creates the permanent suppression list (REQ-207, REQ-147). Retention never purges it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111141';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_suppression';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_suppression', Suppression::TABLE_DEFINITION );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * Personal-data columns.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array(
			'wiw_suppression.name',
			'wiw_suppression.name_key',
			'wiw_suppression.id_keys',
			'wiw_suppression.reason',
		);
	}
};
