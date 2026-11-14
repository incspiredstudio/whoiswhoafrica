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

	public function test_REQ_009_an_unknown_city_is_listed_for_the_dry_run_but_never_refuses(): void {
		$mapper = new TermMapper();
		$record = $this->record( array( 'hq_city' => 'Qwv Unknown Town' ) ) + array( 'country' => 'NG' );
		$this->assertSame(
			array(
				array(
					'field'   => 'hq_city',
					'value'   => 'Qwv Unknown Town',
					'country' => 'NG',
				),
			),
			$mapper->unmapped( $record ),
			'The dry run lists the city, with the record country so "Add all as new places" can file it.'
		);
		$this->assertSame( array(), $mapper->terms_for( $record ), 'An unmapped city is not written.' );
		$this->assertFalse( method_exists( $mapper, 'assert_mapped' ), 'Nothing refuses a record for a list value any more.' );
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

	public function test_REQ_009_add_all_files_a_new_city_under_the_record_country(): void {
		$added = ( new TermMapper() )->create_missing(
			array(
				array(
					'field'   => 'hq_city',
					'value'   => 'Qwv New Harbour',
					'country' => 'GH',
				),
				array(
					'field'   => 'hq_city',
					'value'   => 'Ghana',
					'country' => 'GH',
				),
				array(
					'field' => 'industry',
					'value' => 'Qwv Never Added',
				),
			)
		);
		$this->assertSame( 1, $added, 'Only the real city is added; a country name and an industry are not.' );
		$term = get_term_by( 'name', 'Qwv New Harbour', Taxonomies::PLACE );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertSame( \WhoIsWho\Core\Directory\Classification::country_id( 'GH' ), (int) $term->parent );
		$this->assertSame( Taxonomies::KIND_CITY, get_term_meta( $term->term_id, Taxonomies::META_KIND, true ) );
		$this->assertFalse( get_term_by( 'name', 'Qwv Never Added', Taxonomies::INDUSTRY ) );
		wp_delete_term( $term->term_id, Taxonomies::PLACE );
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
