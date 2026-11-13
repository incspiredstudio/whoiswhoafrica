<?php
/**
 * Creates wiw_arrangements and wiw_placements (REQ-021, REQ-112, REQ-179, REQ-205): sponsored story deals and their placements.
 * Money is an integer in minor units plus a currency code. No foreign key or column refers to any Awards table or to a profile.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026112010';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_arrangements and wiw_placements';
	}

	/**
	 * Create the tables; running it again changes nothing.
	 */
	public function up(): void {
		Schema::create_table( ArrangementRepository::TABLE, ArrangementRepository::TABLE_DEFINITION );
		Schema::create_table( PlacementRepository::TABLE, PlacementRepository::TABLE_DEFINITION );
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
			'wiw_arrangements.notes',
			'wiw_arrangements.reference',
			'wiw_arrangements.amount_minor',
			'wiw_placements.sponsor_name',
		);
	}
};
