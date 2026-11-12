<?php
/**
 * Creates the internal notes table (REQ-187, REQ-188). Notes are never public.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Notes\NotesService;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111142';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_notes';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_notes', NotesService::TABLE_DEFINITION );
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
		return array( 'wiw_notes.body' );
	}
};
