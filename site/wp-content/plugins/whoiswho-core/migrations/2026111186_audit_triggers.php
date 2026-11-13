<?php
/**
 * Installs the append-only triggers on the audit table where the database account may (REQ-051). A host that refuses them does
 * not fail the migration; the outcome is recorded and shown on the Audit log screen.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Audit\AuditGuard;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111186';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Install the append-only triggers on wiw_audit_log (when permitted)';
	}

	/**
	 * Install; the result is recorded, never thrown.
	 */
	public function up(): void {
		AuditGuard::install();
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * No columns.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
