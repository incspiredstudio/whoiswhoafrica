<?php
/**
 * Adds the enquiry columns to wiw_support_items (REQ-177, REQ-205): phone, organisation, interest, source and a stable external
 * id, and makes due_at optional because an enquiry has no due date. Expand only: nothing is removed.
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
		return '2026111143';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Add enquiry columns to wiw_support_items';
	}

	/**
	 * Add the columns and relax due_at.
	 */
	public function up(): void {
		global $wpdb;
		$table = Schema::create_table( SupportItems::TABLE, SupportItems::TABLE_DEFINITION );
		$wpdb->query( "ALTER TABLE {$table} MODIFY due_at datetime NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; schema change.
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
			'wiw_support_items.requester_phone',
			'wiw_support_items.organisation',
		);
	}
};
