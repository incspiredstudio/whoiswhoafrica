<?php
/**
 * Mapping import values to the site's controlled lists (REQ-009, REQ-212). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Mapping\TermMapper;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\WpTestCase;

final class TermMapperTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! taxonomy_exists( Taxonomies::INDUSTRY ) ) {
			( new Taxonomies() )->register();
		}
		delete_option( TermMapper::OPTION );
	}

	protected function tearDown(): void {
		delete_option( TermMapper::OPTION );
		parent::tearDown();
	}

	/**
	 * A record with the given field values.
	 *
	 * @param array<string, string> $values Field => value.
	 * @return array<string, mixed>
	 */
	private function record( array $values ): array {
		$fields = array();
		foreach ( $values as $field => $value ) {
			$fields[ $field ] = array( 'value' => $value );
		}
		return array( 'fields' => $fields );
	}

	private function term( string $taxonomy, string $name ): int {
		$id = self::factory()->term->create(
			array(
				'taxonomy' => $taxonomy,
				'name'     => $name,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	public function test_REQ_009_known_values_resolve_by_slug_name_and_alias(): void {
		$bank = $this->term( Taxonomies::ORG_TYPE, 'Commercial Bank' );
		$mfb  = $this->term( Taxonomies::ORG_TYPE, 'Microfinance Bank' );
		add_term_meta( $mfb, TermMapper::ALIAS_META, 'mfb' );
		$mapper = new TermMapper();
		$this->assertSame( $bank, $mapper->resolve( 'org_type', 'commercial-bank' ), 'By slug.' );
		$this->assertSame( $bank, $mapper->resolve( 'org_type', 'Commercial Bank' ), 'By name.' );
		$this->assertSame( $mfb, $mapper->resolve( 'org_type', 'MFB' ), 'By alias, case-insensitive.' );
	}

	public function test_REQ_009_unknown_term_requires_mapping_before_apply(): void {
		$mapper = new TermMapper();
		$record = $this->record(
			array(
				'org_type' => 'Quantum Qwv Bank',
				'industry' => '',
			)
		);
		$this->assertSame(
			array(
				array(
					'field' => 'org_type',
					'value' => 'Quantum Qwv Bank',
				),
			),
			$mapper->unmapped( $record ),
			'The dry run lists the value as needing a mapping.'
		);
		$this->assertSame( array(), $mapper->terms_for( $record ), 'An unmapped value is not written.' );
		$this->expectException( \RuntimeException::class );
		$mapper->assert_mapped( $record );
	}

	public function test_REQ_009_mapping_is_reused_on_next_import(): void {
		$bank   = $this->term( Taxonomies::ORG_TYPE, 'Commercial Bank Two' );
		$mapper = new TermMapper();
		$record = $this->record( array( 'org_type' => 'Qwv Retail Lender' ) );
		$this->assertCount( 1, $mapper->unmapped( $record ) );
		$mapper->save( 'org_type', 'Qwv Retail Lender', $bank );

		$next = new TermMapper();
		$this->assertSame( array(), $next->unmapped( $this->record( array( 'org_type' => '  qwv   retail lender ' ) ) ) );
		$this->assertSame( array( 'org_type' => $bank ), $next->terms_for( $record ) );
		$next->assert_mapped( $record );
	}

	public function test_REQ_009_a_skipped_value_passes_apply_but_writes_no_term(): void {
		$mapper = new TermMapper();
		$record = $this->record( array( 'sector' => 'Qwv Unlisted Sector' ) );
		$mapper->save( 'sector', 'Qwv Unlisted Sector', TermMapper::SKIP );
		$this->assertSame( array(), $mapper->unmapped( $record ) );
		$this->assertSame( array(), $mapper->terms_for( $record ) );
	}

	public function test_REQ_009_a_mapping_must_point_at_a_term_in_the_right_list(): void {
		$industry = $this->term( Taxonomies::INDUSTRY, 'Qwv Mining' );
		$this->expectException( \InvalidArgumentException::class );
		( new TermMapper() )->save( 'org_type', 'Something', $industry );
	}
}
