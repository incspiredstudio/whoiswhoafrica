<?php
/**
 * The launch safeguard rules (M5-LCH-01; REQ-208, REQ-209, REQ-210, REQ-217). Pure logic on facts.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Deploy\Safeguards;
use WhoIsWho\Tests\TestCase;

require_once dirname( __DIR__, 2 ) . '/deploy/safeguards/Safeguards.php';

final class DeploySafeguardsTest extends TestCase {

	/**
	 * Facts for a staging copy that is ready.
	 *
	 * @return array<string, mixed>
	 */
	private function good(): array {
		return array(
			'states'                       => array(
				'published_reviewed'  => array( 'organisation' => 2 ),
				'published_pending'   => array( 'organisation' => 1 ),
				'imported_not_public' => array( 'individual' => 3 ),
			),
			'unreviewed_public_people'     => 0,
			'status_mismatch'              => 0,
			'reviewed_slugs'               => array( 'a', 'b' ),
			'not_reviewed_slugs'           => array( 'c', 'd' ),
			'sitemap_slugs'                => array( 'a', 'b' ),
			'direct_status_writes_refused' => true,
		);
	}

	/**
	 * Names of failed checks.
	 *
	 * @param array<string, mixed> $facts Facts.
	 * @return list<string>
	 */
	private function failed( array $facts ): array {
		$names = array();
		foreach ( Safeguards::evaluate( $facts ) as $result ) {
			if ( Safeguards::FAIL === $result['status'] ) {
				$names[] = $result['name'];
			}
		}
		return $names;
	}

	public function test_REQ_208_a_ready_staging_copy_passes(): void {
		$results = Safeguards::evaluate( $this->good() );
		$this->assertTrue( Safeguards::passed( $results ) );
		$this->assertStringContainsString( 'RESULT: nothing unreviewed is public', Safeguards::render( $results ) );
		$this->assertStringContainsString( 'published_reviewed=2', Safeguards::render( $results ) );
	}

	public function test_REQ_217_a_public_individual_without_review_fails(): void {
		$facts                             = $this->good();
		$facts['unreviewed_public_people'] = 2;
		$this->assertSame( array( 'Every public individual is Published - reviewed' ), $this->failed( $facts ) );
	}

	public function test_REQ_208_an_unreviewed_profile_in_the_sitemap_fails(): void {
		$facts                  = $this->good();
		$facts['sitemap_slugs'] = array( 'a', 'b', 'c' );
		$this->assertSame( array( 'The sitemap lists only reviewed profiles' ), $this->failed( $facts ) );
	}

	public function test_REQ_208_a_reviewed_profile_missing_from_the_sitemap_fails(): void {
		$facts                  = $this->good();
		$facts['sitemap_slugs'] = array( 'a' );
		$this->assertSame( array( 'The sitemap lists every reviewed profile' ), $this->failed( $facts ) );
	}

	public function test_REQ_210_a_status_that_disagrees_with_the_state_fails(): void {
		$facts                    = $this->good();
		$facts['status_mismatch'] = 1;
		$this->assertContains( 'WordPress status agrees with the publication state on every profile', $this->failed( $facts ) );
	}

	public function test_REQ_210_a_missing_status_guard_fails(): void {
		$facts                                 = $this->good();
		$facts['direct_status_writes_refused'] = false;
		$this->assertSame( array( 'Direct status writes are refused' ), $this->failed( $facts ) );
	}

	public function test_REQ_208_facts_that_were_not_collected_never_pass(): void {
		$this->assertFalse( Safeguards::passed( Safeguards::evaluate( array() ) ) );
		$this->assertFalse( Safeguards::passed( array() ) );
	}

	public function test_REQ_208_the_output_carries_counts_and_names_of_checks_only(): void {
		$facts                       = $this->good();
		$facts['not_reviewed_slugs'] = array( 'c', 'sample-slug-qwv' );
		$facts['sitemap_slugs']      = array( 'a', 'b', 'sample-slug-qwv' );
		$this->assertStringNotContainsString( 'sample-slug-qwv', Safeguards::render( Safeguards::evaluate( $facts ) ), 'A slug can carry a name, so the report gives a count instead.' );
	}
}
