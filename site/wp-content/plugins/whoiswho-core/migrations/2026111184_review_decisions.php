<?php
/**
 * Creates the review decision log (REQ-149): staff ids, a decision, a reason code and a wait time.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Review\Metrics\DecisionLog;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111184';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_review_decisions';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_review_decisions', DecisionLog::TABLE_DEFINITION );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * No personal-data columns: staff user ids and codes.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
