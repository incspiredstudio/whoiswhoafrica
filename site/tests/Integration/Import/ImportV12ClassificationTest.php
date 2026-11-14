<?php
/**
 * Contract 1.2 on the website (REQ-009 as amended 2026-11-14, REQ-237, REQ-221): the optional `classification` object is checked
 * for shape, is part of the content hash and wins when the record is applied (primary industry, extras, primary country by ISO
 * code, organisation type, provenance). Without it (1.0 and 1.1) the raw values map through the aliases or to Unclassified, and
 * no record is refused for a list value. Needs the WordPress test suite and the real tables; it removes what it creates.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\ClassificationSeeder;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Import\RecordValidator;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class ImportV12ClassificationTest extends TestCase {

	/**
	 * Countries this test created.
	 *
	 * @var int[]
	 */
	private array $countries = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		if ( ! is_taxonomy_hierarchical( Taxonomies::INDUSTRY ) ) {
			( new Taxonomies() )->register();
		}
		( new ClassificationSeeder() )->seed();
		$this->clean();
	}

	protected function tearDown(): void {
		$this->clean();
		foreach ( $this->countries as $id ) {
			wp_delete_term( $id, Taxonomies::PLACE );
		}
		parent::tearDown();
	}

	/**
	 * Remove the profiles this test imported.
	 */
	private function clean(): void {
		global $wpdb;
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT profile_id FROM ' . RecordMap::table() . ' WHERE record_id LIKE %s', 'v12-qwv-%' ) ) as $id ) { // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
			wp_delete_post( (int) $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( RecordMap::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * A record from the worked example.
	 *
	 * @param string                    $id             Record id suffix.
	 * @param string                    $version        Contract version.
	 * @param array<string, mixed>|null $classification Classification object, or null for none.
	 * @param array<string, string>     $raw            Extra raw string fields (industry, hq_city, org_type).
	 */
	private function record( string $id, string $version, ?array $classification, array $raw = array() ): \stdClass {
		$record                   = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/import/worked-example.json' ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test fixture.
		$record->contract_version = $version;
		$record->record_id        = 'v12-qwv-' . $id;
		$record->external_ids     = new \stdClass();
		$record->fields->name->value = 'V12 Org ' . $id . ' Qwv';
		foreach ( $raw as $key => $value ) {
			$record->fields->{$key} = (object) array(
				'value'      => $value,
				'provenance' => $record->fields->name->provenance,
			);
		}
		if ( null !== $classification ) {
			$record->classification = json_decode( (string) wp_json_encode( $classification ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- objects stay objects.
		}
		$record->content_hash = ContentHasher::hash_record( $record );
		return $record;
	}

	/**
	 * A valid classification object.
	 *
	 * @param array<string, mixed> $over Overrides.
	 * @return array<string, mixed>
	 */
	private function classification( array $over = array() ): array {
		return array_merge(
			array(
				'primary_country'  => 'NG',
				'primary_industry' => 'construction-cement',
				'industries'       => array( 'food-beverage', 'oil-gas' ),
				'provenance'       => array(
					'method'         => 'ai',
					'model'          => 'model-x',
					'prompt_version' => 'classify-v1',
					'confidence'     => 'high',
				),
			),
			$over
		);
	}

	/**
	 * Validate a record line.
	 *
	 * @param \stdClass $record Record.
	 * @return array<string, mixed>
	 */
	private function validate( \stdClass $record ): array {
		return ( new RecordValidator() )->validate( (string) json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP.
	}

	/**
	 * Validate and apply; return the profile id.
	 *
	 * @param \stdClass $record Record.
	 */
	private function apply( \stdClass $record ): int {
		$valid = $this->validate( $record );
		$this->assertTrue( $valid['ok'], 'Valid record, got ' . (string) $valid['code'] . ' on ' . (string) $valid['field'] );
		$result = ( new ProfileApplier( new Suppression( AuditLogger::for_site() ) ) )->apply( (array) $valid['record'], 'B-v12-qwv', 1, true );
		$this->assertSame( ProfileApplier::CREATED, $result['code'] );
		return (int) $result['profile_id'];
	}

	/**
	 * Term slugs of a profile.
	 *
	 * @param int    $id       Profile.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	private function slugs( int $id, string $taxonomy ): array {
		return wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'slugs' ) );
	}

	public function test_REQ_237_the_importer_accepts_contract_1_2(): void {
		$this->assertSame( array( '1.0', '1.1', '1.2' ), BundleReader::ACCEPTED_VERSIONS );
		$this->assertContains( 'classification', ContentHasher::HASHED_FIELDS );
	}

	public function test_REQ_009_a_classification_object_is_checked_for_shape_and_only_from_1_2(): void {
		$this->assertTrue( $this->validate( $this->record( 'ok', '1.2', $this->classification() ) )['ok'] );
		$this->assertTrue( $this->validate( $this->record( 'none', '1.2', null ) )['ok'], 'The object is optional in 1.2.' );
		$old = $this->validate( $this->record( 'old', '1.1', $this->classification() ) );
		$this->assertSame( array( 'SCHEMA', 'classification' ), array( $old['code'], $old['field'] ), '1.1 has no classification key.' );
		$bad = array(
			'country'    => array( 'primary_country' => 'Nigeria' ),
			'industry'   => array( 'primary_industry' => 'Oil & Gas' ),
			'extras'     => array( 'industries' => 'oil-gas' ),
			'method'     => array( 'provenance' => array( 'method' => 'guess', 'confidence' => 'high' ) ),
			'confidence' => array( 'provenance' => array( 'method' => 'rule', 'confidence' => 'low' ) ),
			'unknownkey' => array( 'colour' => 'red' ),
		);
		foreach ( $bad as $case => $over ) {
			$got = $this->validate( $this->record( 'bad-' . $case, '1.2', $this->classification( $over ) ) );
			$this->assertSame( 'SCHEMA', $got['code'], $case );
			$this->assertSame( 'classification', $got['field'], $case );
		}
	}

	public function test_REQ_221_the_classification_is_part_of_the_content_hash(): void {
		$record = $this->record( 'hash', '1.2', $this->classification() );
		$record->classification->primary_industry = 'banking';
		$got = $this->validate( $record );
		$this->assertSame( 'HASH_MISMATCH', $got['code'], 'Changing the classification without re-hashing is caught.' );
		$plain = $this->record( 'hash-plain', '1.1', null );
		$this->assertSame( $plain->content_hash, ContentHasher::hash_record( $plain ), 'A 1.1 hash is unchanged by the new key.' );
	}

	public function test_REQ_009_the_classification_object_wins_when_applied(): void {
		$id = $this->apply( $this->record( 'win', '1.2', $this->classification(), array( 'industry' => 'Banking and financial services' ) ) );
		$this->assertSame( (int) Classification::term_id( Taxonomies::INDUSTRY, 'construction-cement' ), (int) get_post_meta( $id, Classification::PRIMARY_INDUSTRY_META, true ) );
		$this->assertEqualsCanonicalizing( array( 'construction-cement', 'food-beverage', 'oil-gas' ), $this->slugs( $id, Taxonomies::INDUSTRY ), 'Not the raw industry.' );
		$this->assertSame( Classification::country_id( 'NG' ), (int) get_post_meta( $id, Classification::PRIMARY_COUNTRY_META, true ) );
		$this->assertSame( array( 'other' ), $this->slugs( $id, Taxonomies::ORG_TYPE ), 'The worked example\'s organisation type is not in the list.' );
		$prov = json_decode( (string) get_post_meta( $id, Classification::PROVENANCE_META, true ), true );
		$this->assertSame( 'ai', $prov['method'] );
		$this->assertSame( 'classify-v1', $prov['prompt_version'] );
	}

	public function test_REQ_009_org_type_slug_pan_african_and_a_new_country(): void {
		$pan = $this->apply( $this->record( 'pan', '1.2', $this->classification( array( 'primary_country' => 'PAN', 'primary_industry' => 'diplomacy-international-bodies', 'industries' => array() ) ), array( 'org_type' => 'international-organisation' ) ) );
		$this->assertSame( Classification::country_id( 'PAN' ), (int) get_post_meta( $pan, Classification::PRIMARY_COUNTRY_META, true ) );
		$this->assertSame( array( 'international-organisation' ), $this->slugs( $pan, Taxonomies::ORG_TYPE ) );

		$this->assertNull( Classification::country_id( 'PT' ) );
		$pt = $this->apply( $this->record( 'pt', '1.2', $this->classification( array( 'primary_country' => 'PT' ) ) ) );
		$country = Classification::country_id( 'PT' );
		$this->assertIsInt( $country, 'A real ISO code the list lacks creates the country.' );
		$this->countries[] = $country;
		$this->assertSame( $country, (int) get_post_meta( $pt, Classification::PRIMARY_COUNTRY_META, true ) );

		$xx = $this->apply( $this->record( 'xx', '1.2', $this->classification( array( 'primary_country' => 'XX' ) ) ) );
		$this->assertSame( '', get_post_meta( $xx, Classification::PRIMARY_COUNTRY_META, true ), 'A code that is not ISO makes no country and refuses nothing.' );
	}

	public function test_REQ_009_an_unknown_slug_falls_back_to_the_aliases_or_unclassified(): void {
		$alias = $this->apply( $this->record( 'alias', '1.2', $this->classification( array( 'primary_industry' => 'qwv-not-a-leaf', 'industries' => array( 'energy-resources' ) ) ), array( 'industry' => 'Oil and gas' ) ) );
		$this->assertSame( array( 'oil-gas' ), $this->slugs( $alias, Taxonomies::INDUSTRY ), 'An unknown slug and a sector slug are ignored; the raw value maps.' );
		$uncl = $this->apply( $this->record( 'uncl', '1.2', $this->classification( array( 'primary_industry' => 'unclassified', 'industries' => array() ) ), array( 'industry' => 'Oil and gas' ) ) );
		$this->assertSame( array( 'unclassified' ), $this->slugs( $uncl, Taxonomies::INDUSTRY ), 'Unclassified from the pipeline is kept.' );
		$profile = PublicProfile::find_by_id( $uncl, true );
		$this->assertNull( null === $profile ? null : $profile->classification()['industry'] );
	}

	public function test_REQ_009_a_1_1_record_maps_through_the_aliases_and_is_never_refused(): void {
		$mapped = $this->apply( $this->record( 'v11-mapped', '1.1', null, array( 'industry' => 'Aviation and aerospace', 'org_type' => 'Public body' ) ) );
		$this->assertSame( array( 'aviation' ), $this->slugs( $mapped, Taxonomies::INDUSTRY ) );
		$this->assertSame( array( 'government-body' ), $this->slugs( $mapped, Taxonomies::ORG_TYPE ) );
		$this->assertSame( Classification::country_id( 'NG' ), (int) get_post_meta( $mapped, Classification::PRIMARY_COUNTRY_META, true ), 'The record country is the primary country.' );
		$prov = json_decode( (string) get_post_meta( $mapped, Classification::PROVENANCE_META, true ), true );
		$this->assertSame( 'alias', $prov['method'] );

		$unknown = $this->apply( $this->record( 'v11-unknown', '1.1', null, array( 'industry' => 'Qwv Zeppelin Repair', 'org_type' => 'Qwv Guild' ) ) );
		$this->assertSame( array( 'unclassified' ), $this->slugs( $unknown, Taxonomies::INDUSTRY ) );
		$this->assertSame( array( 'other' ), $this->slugs( $unknown, Taxonomies::ORG_TYPE ) );
		$this->assertSame( (int) Classification::unclassified_id(), (int) get_post_meta( $unknown, Classification::PRIMARY_INDUSTRY_META, true ) );
	}
}
