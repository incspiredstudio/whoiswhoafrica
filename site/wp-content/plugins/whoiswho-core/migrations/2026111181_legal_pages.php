<?php
/**
 * Seeds the legal pages (REQ-100, REQ-120, REQ-058): Privacy, Terms, Cookie policy, Your data rights and Complaints, published
 * with draft text. A page that already exists is never touched.
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
		return '2026111181';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Seed the legal pages';
	}

	/**
	 * Create the missing pages; running it again changes nothing.
	 */
	public function up(): void {
		( new LegalPages() )->ensure();
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
