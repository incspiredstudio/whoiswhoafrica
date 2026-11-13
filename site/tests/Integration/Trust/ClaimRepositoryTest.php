<?php
/**
 * Profile claim storage (REQ-251, REQ-252, REQ-256). Needs the WordPress test suite and the real claims table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Trust;

use WhoIsWho\Core\Import\EvidenceRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Trust\Claim;
use WhoIsWho\Core\Trust\ClaimRepository;
use WhoIsWho\Tests\TestCase;

final class ClaimRepositoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		$this->migrate();
		$wpdb->query( 'TRUNCATE TABLE ' . ClaimRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		delete_option( ClaimRepository::SEQUENCE_OPTION );
	}

	private function migrate(): void {
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	/**
	 * A valid claim input.
	 *
	 * @return array<string, mixed>
	 */
	private function input(): array {
		return array(
			'profile_id'   => 42,
			'relationship' => 'representative',
			'name'         => 'Ada Obi',
			'role'         => 'Company secretary',
			'work_email'   => 'ada@example.test',
			'phone'        => '+234 800 000 0000',
			'evidence'     => 'Listed on the company registry as secretary.',
			'consent'      => true,
		);
	}

	private function utc( string $when ): \DateTimeImmutable {
		return new \DateTimeImmutable( $when, new \DateTimeZone( 'UTC' ) );
	}

	public function test_REQ_251_claim_row_stores_every_form_field(): void {
		$repo   = new ClaimRepository();
		$claim  = $repo->create( $this->input(), $this->utc( '2026-11-12 09:30:00' ) );
		$stored = $repo->find( $claim->id );
		$this->assertNotNull( $stored );
		$this->assertSame( 42, $stored->profile_id );
		$this->assertSame( 'representative', $stored->relationship );
		$this->assertSame( 'Ada Obi', $stored->name );
		$this->assertSame( 'Company secretary', $stored->role );
		$this->assertSame( 'ada@example.test', $stored->work_email );
		$this->assertSame( '+234 800 000 0000', $stored->phone );
		$this->assertSame( 'Listed on the company registry as secretary.', $stored->evidence );
		$this->assertSame( '2026-11-12 09:30:00', $stored->consent_at );
		$this->assertSame( Claim::NEW, $stored->status );
		$this->assertSame( '2026-11-12 09:30:00', $stored->received_at );
		$this->assertNull( $stored->decided_by );
		$this->assertSame( 'CLM-0001', $stored->reference );
		$this->assertSame( 'CLM-0002', $repo->create( $this->input() )->reference );
		$this->assertSame( $claim->id, $repo->find_by_reference( 'CLM-0001' )->id );
	}

	public function test_REQ_251_a_claim_needs_a_relationship_and_consent(): void {
		$repo = new ClaimRepository();
		foreach ( array(
			'relationship' => 'stranger',
			'consent'      => false,
			'profile_id'   => 0,
		) as $field => $value ) {
			$input           = $this->input();
			$input[ $field ] = $value;
			try {
				$repo->create( $input );
				$this->fail( "Accepted a bad {$field}." );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertInstanceOf( \InvalidArgumentException::class, $e );
			}
		}
	}

	public function test_REQ_256_decide_by_is_received_plus_five_days(): void {
		$claim = ( new ClaimRepository() )->create( $this->input(), $this->utc( '2026-11-12 09:30:00' ) );
		$this->assertSame( '2026-11-17 09:30:00', $claim->decide_by_at );
	}

	public function test_REQ_252_a_claim_is_decided_once_and_a_rejection_needs_a_reason(): void {
		$repo  = new ClaimRepository();
		$claim = $repo->create( $this->input() );
		try {
			$repo->decide( $claim->id, Claim::REJECTED, 7, '' );
			$this->fail( 'A rejection without a reason was accepted.' );
		} catch ( \DomainException $e ) {
			$this->assertSame( Claim::NEW, $repo->find( $claim->id )->status );
		}
		$repo->decide( $claim->id, Claim::APPROVED, 7, '', $this->utc( '2026-11-13 10:00:00' ) );
		$decided = $repo->find( $claim->id );
		$this->assertSame( Claim::APPROVED, $decided->status );
		$this->assertSame( 7, $decided->decided_by );
		$this->assertSame( '2026-11-13 10:00:00', $decided->decided_at );
		$this->expectException( \DomainException::class );
		$repo->decide( $claim->id, Claim::REJECTED, 8, 'Changed my mind' );
	}

	public function test_REQ_252_claim_table_pii_columns_in_catalogue(): void {
		$catalogue = new PiiCatalogue();
		$this->assertSame(
			array(),
			$catalogue->missing(
				array(
					'wiw_profile_claims.name',
					'wiw_profile_claims.role',
					'wiw_profile_claims.work_email',
					'wiw_profile_claims.phone',
					'wiw_profile_claims.evidence',
				)
			)
		);
	}

	public function test_REQ_251_migration_runs_twice_and_does_not_clash_with_the_import_claims_table(): void {
		global $wpdb;
		$this->migrate();
		$this->migrate();
		$this->assertSame( $wpdb->prefix . 'wiw_profile_claims', ClaimRepository::table() );
		$this->assertNotSame( 'wiw_claims', ClaimRepository::TABLE );
		$this->assertNotSame( $wpdb->prefix . 'wiw_claims', ClaimRepository::table() );
		$this->assertNotEmpty( EvidenceRepository::CLAIMS );
	}
}
