<?php
/**
 * Creates wiw_listing_consents (REQ-175, REQ-233): the person's decision on a complimentary listing, per profile, with the
 * policy version and time. It holds no email address or name.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Compliance\Consent\ListingConsent;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111171';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_listing_consents';
	}

	/**
	 * Create the table.
	 */
	public function up(): void {
		Schema::create_table( ListingConsent::TABLE, ListingConsent::TABLE_DEFINITION );
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
