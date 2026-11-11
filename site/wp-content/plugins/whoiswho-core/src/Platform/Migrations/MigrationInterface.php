<?php
/**
 * A forward-only schema or data migration. There is deliberately no down(): undoing is a new, later migration.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Migrations;

/**
 * Contract for one migration file.
 */
interface MigrationInterface {

	/**
	 * Sortable version, for example 2026111101. Unique across migrations.
	 */
	public function version(): string;

	/**
	 * One-line description for the run report.
	 */
	public function description(): string;

	/**
	 * Apply the change. Must be safe to attempt again after a failure.
	 */
	public function up(): void;

	/**
	 * True when the step is destructive (drops or rewrites data). Contract steps are listed by CI.
	 */
	public function is_contract(): bool;

	/**
	 * Personal-data columns this migration adds, as "table.column" strings, for the PII catalogue test.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array;
}
