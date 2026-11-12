<?php
/**
 * Creates wiw_profile_claims (REQ-251, REQ-252, REQ-256): one row per claim. Not wiw_claims, which is the import fact-claims
 * table. The profile state row already carries claimed, claimed_at and assigned_user_id (the profile manager), so nothing is
 * added to it here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Trust\ClaimRepository;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111160';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_profile_claims';
	}

	/**
	 * Create the table; running it again changes nothing.
	 */
	public function up(): void {
		Schema::create_table( ClaimRepository::TABLE, ClaimRepository::TABLE_DEFINITION );
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
		return array(
			'wiw_profile_claims.name',
			'wiw_profile_claims.role',
			'wiw_profile_claims.work_email',
			'wiw_profile_claims.phone',
			'wiw_profile_claims.evidence',
		);
	}
};
