<?php
/**
 * On an update, moves WordPress's untouched "Hello world!" post and "Sample Page" to the trash, as a first install does (issue 806,
 * REQ-056). The checks are the same strict ones (default id and slug, published, never edited), and it runs once per site: a site
 * that already did this keeps whatever was put back from the trash.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Setup\DefaultContent;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026112102';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Move the untouched WordPress sample post and page to the trash on an update';
	}

	/**
	 * Run the first-run cleanup once.
	 */
	public function up(): void {
		DefaultContent::retire();
	}

	/**
	 * Not destructive: only untouched defaults move, to the trash.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * No personal-data columns.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
