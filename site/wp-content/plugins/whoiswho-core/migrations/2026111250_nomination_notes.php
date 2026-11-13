<?php
/**
 * Adds the internal notes column to wiw_nominations (REQ-031, REQ-202): free text staff keep on a nomination from the Nomination
 * detail screen. Expand only: nothing is removed. The notes are never public and never in an export.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111250';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Add the internal notes column to wiw_nominations';
	}

	/**
	 * Add the notes column.
	 */
	public function up(): void {
		Schema::create_table( AwardsTables::NOMINATIONS, AwardsTables::NOMINATIONS_DEFINITION );
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
		return array( 'wiw_nominations.notes' );
	}
};
