<?php
/**
 * Creates wiw_customers (REQ-205, REQ-112, REQ-187): customers with a stable external id, contact details (personal data) and
 * internal notes that are never public.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111170';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_customers';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( CustomerRepository::TABLE, CustomerRepository::TABLE_DEFINITION );
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
			'wiw_customers.name',
			'wiw_customers.contact_email',
			'wiw_customers.contact_phone',
			'wiw_customers.notes',
		);
	}
};
