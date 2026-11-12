<?php
/**
 * Import claims, passages and the verification report, reviewer only (REQ-142, REQ-166, REQ-208). Needs the WordPress test
 * suite. Rows are removed in tearDown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Import\EvidenceRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\WpTestCase;

final class EvidenceRepositoryTest extends WpTestCase {

	private const PROFILE = 987001;

	protected function setUp(): void {
		parent::setUp();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		( new EvidenceRepository() )->clear( self::PROFILE );
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		( new EvidenceRepository() )->clear( self::PROFILE );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A record with one passage, one claim and a verification report.
	 *
	 * @param string $quote Quote of the claim.
	 * @return array<string, mixed>
	 */
	private function record( string $quote = 'founded in 1999' ): array {
		return array(
			'claims'       => array(
				array(
					'claim_id'   => 'c1',
					'field'      => 'summary',
					'text'       => 'The company was started in 1999.',
					'passage_id' => 'p1',
					'quote'      => $quote,
				),
			),
			'passages'     => array(
				array(
					'passage_id'   => 'p1',
					'source_id'    => 'src_a',
					'source_url'   => 'https://example.org/about',
					'retrieved_at' => '2026-11-10T09:00:00Z',
					'source_hash'  => 'sha256:' . str_repeat( 'b', 64 ),
					'text'         => 'Sample Co was founded in 1999 in Lagos.',
				),
			),
			'verification' => array(
				'run_id'         => 'run1',
				'verified_at'    => '2026-11-10T10:00:00Z',
				'checks'         => array( 'approved_source' => 'pass' ),
				'claims_kept'    => 1,
				'claims_dropped' => 0,
				'fields_dropped' => array(),
			),
		);
	}

	public function test_REQ_166_claim_quote_must_appear_in_passage(): void {
		$repo = new EvidenceRepository();
		$this->assertSame( array(), EvidenceRepository::bad_claims( $this->record()['claims'], $this->record()['passages'] ) );
		$this->assertSame( array( 'c1' ), EvidenceRepository::bad_claims( $this->record( 'founded in 2001' )['claims'], $this->record()['passages'] ) );
		try {
			$repo->store( self::PROFILE, $this->record( 'founded in 2001' ) );
			$this->fail( 'A quote that is not in the passage must be refused.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'quote', $e->getMessage() );
		}
		global $wpdb;
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . EvidenceRepository::table( 'passages' ) . ' WHERE profile_id = %d', self::PROFILE ) ), 'A refused set writes nothing.' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$missing                            = $this->record();
		$missing['claims'][0]['passage_id'] = 'nope';
		$this->assertSame( array( 'c1' ), EvidenceRepository::bad_claims( $missing['claims'], $missing['passages'] ), 'A claim needs its passage.' );
	}

	public function test_REQ_142_evidence_is_stored_and_read_by_a_reviewer_only(): void {
		$repo = new EvidenceRepository();
		$repo->store( self::PROFILE, $this->record() );
		try {
			$repo->for_reviewer( self::PROFILE );
			$this->fail( 'A visitor must not read import evidence.' );
		} catch ( \DomainException $e ) {
			$this->assertStringContainsString( 'Not allowed', $e->getMessage() );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		wp_get_current_user()->add_cap( EvidenceRepository::READ_CAPABILITY );
		$evidence = $repo->for_reviewer( self::PROFILE );
		$this->assertCount( 1, $evidence['claims'] );
		$this->assertSame( 'founded in 1999', $evidence['claims'][0]['quote'] );
		$this->assertSame( str_repeat( 'b', 64 ), $evidence['passages'][0]['source_hash'], 'The sha256: prefix is stripped.' );
		$this->assertSame( 'run1', $evidence['verification']['run_id'] );

		$repo->store( self::PROFILE, $this->record() );
		$this->assertCount( 1, $repo->for_reviewer( self::PROFILE )['claims'], 'Storing again replaces the evidence.' );
		$repo->clear( self::PROFILE );
		$this->assertSame( array(), $repo->for_reviewer( self::PROFILE )['claims'] );
	}

	public function test_REQ_208_claims_and_passages_never_public(): void {
		$root = dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src';
		foreach ( array( 'Directory/PublicProfile.php', 'Content/PublicStory.php', 'Content/PublicEvent.php', 'Directory/ProfileList.php' ) as $file ) {
			if ( ! is_readable( $root . '/' . $file ) ) {
				continue;
			}
			$source = (string) file_get_contents( $root . '/' . $file );
			$this->assertStringNotContainsString( 'EvidenceRepository', $source, $file );
			$this->assertStringNotContainsString( 'wiw_claims', $source, $file );
			$this->assertStringNotContainsString( 'wiw_passages', $source, $file );
		}
		$this->assertFalse( property_exists( PublicProfile::class, 'claims' ) );
		$this->assertFalse( property_exists( PublicProfile::class, 'passages' ) );
		$this->assertFalse( property_exists( PublicProfile::class, 'verification' ) );
	}
}
