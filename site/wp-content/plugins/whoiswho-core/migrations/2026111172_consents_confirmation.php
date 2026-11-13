<?php
/**
 * Adds confirmed_at and token_hash to wiw_consents (REQ-058, REQ-249): a newsletter sign-up is pending until the address is
 * confirmed with a single-use token whose hash is stored. Expand only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111172';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Add confirmation columns to wiw_consents';
	}

	/**
	 * Add the columns; running it again changes nothing.
	 */
	public function up(): void {
		Schema::create_table( ConsentRecorder::TABLE, ConsentRecorder::TABLE_DEFINITION );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * No new personal-data columns: the token column holds a hash only.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array();
	}
};
