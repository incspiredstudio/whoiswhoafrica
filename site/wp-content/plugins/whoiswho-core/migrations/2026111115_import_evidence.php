<?php
/**
 * Creates wiw_claims, wiw_passages and wiw_verification: the pipeline's claims, source passages and verification report for a
 * profile, for the reviewer only (REQ-142, REQ-166, REQ-208). Claim and passage text can quote people named in public sources,
 * so those columns are in the PII catalogue.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Import\EvidenceRepository;
use WhoIsWho\Core\Platform\Migrations\MigrationInterface;
use WhoIsWho\Core\Platform\Migrations\Schema;

return new class() implements MigrationInterface {

	/**
	 * Version.
	 */
	public function version(): string {
		return '2026111115';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create wiw_claims, wiw_passages and wiw_verification';
	}

	/**
	 * Create the tables.
	 */
	public function up(): void {
		Schema::create_table( 'wiw_claims', EvidenceRepository::CLAIMS );
		Schema::create_table( 'wiw_passages', EvidenceRepository::PASSAGES );
		Schema::create_table( 'wiw_verification', EvidenceRepository::VERIFICATION );
	}

	/**
	 * Not destructive.
	 */
	public function is_contract(): bool {
		return false;
	}

	/**
	 * Quoted source text and claims may name people.
	 *
	 * @return string[]
	 */
	public function pii_columns(): array {
		return array( 'wiw_claims.claim_text', 'wiw_claims.quote', 'wiw_passages.passage_text' );
	}
};
