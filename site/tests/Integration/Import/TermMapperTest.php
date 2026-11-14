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

	public function test_REQ_009_an_unknown_city_needs_no_mapping_when_the_record_has_a_country(): void {
		$mapper = new TermMapper();
		$record = $this->record( array( 'hq_city' => 'Qwv Unknown Town' ) ) + array( 'country' => 'NG' );
		$this->assertSame( array(), $mapper->unmapped( $record ), 'It will be added under the country on import.' );
		$this->assertSame( array(), $mapper->terms_for( $record ), 'Nothing is written by the dry run.' );
		$this->assertFalse( method_exists( $mapper, 'assert_mapped' ), 'Nothing refuses a record for a list value any more.' );
		$this->assertFalse( method_exists( $mapper, 'create_missing' ), '"Add all as new places" is gone.' );
		$pan = $this->record( array( 'hq_city' => 'Qwv Union Seat' ) ) + array( 'country' => 'PAN' );
		$this->assertSame(
			array(
				array(
					'field' => 'hq_city',
					'value' => 'Qwv Union Seat',
				),
			),
			$mapper->unmapped( $pan ),
			'A city with no country to file it under is still offered for mapping.'
		);
	}

	public function test_REQ_009_unknown_industry_and_org_type_never_need_a_mapping(): void {
		$mapper = new TermMapper();
		$record = $this->record(
			array(
				'org_type' => 'Quantum Qwv Bank',
				'industry' => 'Qwv Unheard Industry',
			)
		) + array( 'entity_type' => 'organisation' );
		$this->assertSame( array(), $mapper->unmapped( $record ) );
		$class = $mapper->classification_for( $record );
		$this->assertSame( 'unclassified', $class['primary_industry'] );
		$this->assertSame( 'other', $class['org_type'] );
		$this->assertSame( 'low', $class['provenance']['confidence'] );
	}

	public function test_REQ_009_a_city_mapping_is_reused_on_next_import(): void {
		$town   = $this->term( Taxonomies::PLACE, 'Qwv Mapped Town' );
		$mapper = new TermMapper();
		$record = $this->record( array( 'hq_city' => 'Qwv Old Town Name' ) );
		$this->assertCount( 1, $mapper->unmapped( $record ) );
		$mapper->save( 'hq_city', 'Qwv Old Town Name', $town );

		$next = new TermMapper();
		$this->assertSame( array(), $next->unmapped( $this->record( array( 'hq_city' => '  qwv   old town name ' ) ) ) );
		$this->assertSame( array( 'hq_city' => $town ), $next->terms_for( $record ) );
	}

	public function test_REQ_009_an_unknown_city_is_created_under_the_primary_country_with_a_clean_name(): void {
		$mapper = new TermMapper();
		$ghana  = \WhoIsWho\Core\Directory\Classification::ensure_country( 'GH' );
		$made   = $mapper->city_for( $this->record( array( 'hq_city' => "  QWV   NEW\tHARBOUR " ) ) + array( 'country' => 'GH' ) );
		$this->assertIsInt( $made );
		$term = get_term( $made, Taxonomies::PLACE );
		$this->assertSame( 'Qwv New Harbour', $term->name, 'Trimmed, single spaces, title case for an all-capitals value.' );
		$this->assertSame( $ghana, (int) $term->parent );
		$this->assertSame( Taxonomies::KIND_CITY, get_term_meta( $made, Taxonomies::META_KIND, true ) );
		$this->assertSame( $made, $mapper->city_for( $this->record( array( 'hq_city' => 'qwv new harbour' ) ) + array( 'country' => 'GH' ) ), 'Found again, not made twice.' );

		$mixed = $mapper->city_for( $this->record( array( 'hq_city' => 'Qwv de la Côte' ) ) + array( 'country' => 'GH' ) );
		$this->assertSame( 'Qwv de la Côte', get_term( $mixed, Taxonomies::PLACE )->name, 'Mixed case is kept as given.' );
		$lower = $mapper->city_for( $this->record( array( 'hq_city' => 'qwv port town' ) ) + array( 'country' => 'GH' ) );
		$this->assertSame( 'Qwv Port Town', get_term( $lower, Taxonomies::PLACE )->name );

		$this->assertNull( $mapper->city_for( $this->record( array( 'hq_city' => 'Ghana' ) ) + array( 'country' => 'GH' ) ), 'A country name is not a city.' );
		$this->assertNull( $mapper->city_for( $this->record( array( 'hq_city' => 'Kenya' ) ) + array( 'country' => 'GH' ) ), 'Any country name.' );
		$this->assertNull( $mapper->city_for( $this->record( array( 'hq_city' => 'NG' ) ) + array( 'country' => 'GH' ) ), 'An ISO code is not a city.' );
		$this->assertNull( $mapper->city_for( $this->record( array( 'hq_city' => 'Qwv Union Seat' ) ) + array( 'country' => 'PAN' ) ), 'Never under Pan-African.' );
		$this->assertNull( $mapper->city_for( $this->record( array( 'hq_city' => 'Qwv Nowhere' ) ) ), 'No country, no city.' );
		$this->assertFalse( get_term_by( 'name', 'Qwv Union Seat', Taxonomies::PLACE ) );
		$classified = $this->record( array( 'hq_city' => 'Qwv Lake Side' ) ) + array(
			'country'        => 'NG',
			'classification' => array( 'primary_country' => 'KE' ),
		);
		$this->assertSame( \WhoIsWho\Core\Directory\Classification::ensure_country( 'KE' ), (int) get_term( $mapper->city_for( $classified ), Taxonomies::PLACE )->parent, 'The primary country of a 1.2 record wins.' );
	}

	public function test_REQ_009_a_skipped_value_passes_apply_but_writes_no_term(): void {
		$mapper = new TermMapper();
		$record = $this->record( array( 'hq_city' => 'Qwv Unlisted Town' ) );
		$mapper->save( 'hq_city', 'Qwv Unlisted Town', TermMapper::SKIP );
		$this->assertSame( array(), $mapper->unmapped( $record ) );
		$this->assertSame( array(), $mapper->terms_for( $record ) );
	}

	public function test_REQ_009_a_mapping_must_point_at_a_term_in_the_right_list(): void {
		$industry = $this->term( Taxonomies::INDUSTRY, 'Qwv Mining' );
		$this->expectException( \InvalidArgumentException::class );
		( new TermMapper() )->save( 'org_type', 'Something', $industry );
	}
}
