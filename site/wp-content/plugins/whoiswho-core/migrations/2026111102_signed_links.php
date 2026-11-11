<?php
/**
 * Creates the signed-link table. Only the hash of each token is stored; the address the link was sent to is personal data.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Links\LinkStore;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111102';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_signed_links (token hash only)';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_signed_links', LinkStore::TABLE_DEFINITION );
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
		return array( 'wiw_signed_links.email' );
	}
};
