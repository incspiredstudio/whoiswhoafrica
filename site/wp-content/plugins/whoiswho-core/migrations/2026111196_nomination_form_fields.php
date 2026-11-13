<?php
/**
 * Adds the public nomination form's fields to wiw_nominations (REQ-030, REQ-196, REQ-233): the reference issued on submit, the
 * nominee's name and organisation as typed, and the nominator's declaration of any relationship with the nominee. Expand only:
 * nothing is removed. This is the nominator's own declaration; the site holds no judge, conflict or score data (D-064).
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
		return '2026111196';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Add nomination form fields to wiw_nominations';
	}

	/**
	 * Add the columns and the unique reference key.
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
		return array(
			'wiw_nominations.nominee_name',
			'wiw_nominations.nominee_org',
			'wiw_nominations.relationship_note',
		);
	}
};
