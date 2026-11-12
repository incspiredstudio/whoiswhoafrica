<?php
/**
 * Creates wiw_bulk_actions: one row per bulk unpublish with its filter, reason, count, actor, time and restore time (REQ-193).
 * The reason and the filter (which may hold a name search) are staff-typed text, catalogued as caller-supplied.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Review\Bulk\BulkRepository;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111150';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_bulk_actions';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_bulk_actions', BulkRepository::TABLE_DEFINITION );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * Staff-typed text.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array(
			'wiw_bulk_actions.filter_json',
			'wiw_bulk_actions.reason',
		);
	}
};
