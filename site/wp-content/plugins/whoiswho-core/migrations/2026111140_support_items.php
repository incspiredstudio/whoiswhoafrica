<?php
/**
 * Creates the support items table: enquiries, corrections, removals, reports, complaints and data requests (REQ-089, REQ-158).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111140';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_support_items';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_support_items', SupportItems::TABLE_DEFINITION );
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
			'wiw_support_items.requester_name',
			'wiw_support_items.requester_relationship',
			'wiw_support_items.requester_email',
			'wiw_support_items.message',
			'wiw_support_items.ip_hash',
		);
	}
};
