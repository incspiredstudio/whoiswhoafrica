<?php
/**
 * Creates the Awards tables (REQ-034, REQ-266, REQ-267, REQ-268, REQ-202): editions, award categories, nominations and the
 * nomination change log. Additive and forward-only; no foreign key or column refers to any commercial table.
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
		return '2026111190';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create the Awards tables';
	}

	/**
	 * Create the tables; running it again changes nothing.
	 */
	public function up(): void {
		foreach ( AwardsTables::definitions() as $name => $definition ) {
			Schema::create_table( $name, $definition );
		}
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
			'wiw_nominations.nominator_name',
			'wiw_nominations.nominator_email',
			'wiw_nominations.contact_email',
			'wiw_nominations.evidence',
		);
	}
};
