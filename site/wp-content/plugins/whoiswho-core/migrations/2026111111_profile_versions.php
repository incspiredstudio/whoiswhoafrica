<?php
/**
 * Creates wiw_profile_versions, append-only: triggers reject UPDATE and DELETE (REQ-125). Holds old and new field values, which
 * are public-after-approval profile fields (see config/pii.php).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\State\Tables;
use WhoIsWho\Core\Directory\State\VersionGuard;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111111';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_profile_versions (append-only)';
	}

	/**
	 * Create the table and the triggers. A host that refuses triggers does not fail the migration (the repository still has no
	 * update or delete); the failure is recorded in an option so staff can see the table is not protected in the database.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_profile_versions', Tables::VERSIONS );
		VersionGuard::install();
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * The old and new values hold profile fields.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array( 'wiw_profile_versions.old_value', 'wiw_profile_versions.new_value' );
	}
};
