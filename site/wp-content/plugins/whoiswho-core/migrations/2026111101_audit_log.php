<?php
/**
 * Creates the append-only, hash-chained audit table. It holds no personal data beyond ids and the values callers supply.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111101';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_audit_log (append-only, hash-chained)';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_audit_log', AuditLogger::TABLE_DEFINITION );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * No personal-data columns: values are supplied by callers as ids.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
