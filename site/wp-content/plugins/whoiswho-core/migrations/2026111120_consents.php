<?php
/**
 * Creates the consent table: who agreed to what, with the policy version and time.
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
		return '2026111120';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_consents (consent records with policy version and time)';
	}

	/**
	 * Create the table.
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
	 * Personal-data columns.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array( 'wiw_consents.email', 'wiw_consents.email_hash' );
	}
};
