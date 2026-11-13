<?php
/**
 * Replaces a legal page that still holds the original short draft (unchanged) with the fuller draft text (REQ-100, REQ-120, REQ-058).
 * An edited page is never touched.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Compliance\Legal\LegalPages;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026112015';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Replace unedited first-draft legal pages with the fuller draft text';
	}

	/**
	 * Replace only pages still holding the original short draft; edited pages are never touched.
	 */
	public function up(): void {
		( new LegalPages() )->upgrade_untouched();
	}

	/**
	 * Not destructive.
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
