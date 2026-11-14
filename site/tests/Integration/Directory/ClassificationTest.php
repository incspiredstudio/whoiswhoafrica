<?php
/**
 * The classification tree on the site (REQ-009 as amended 2026-11-14, REQ-006, REQ-007): the seeded hierarchy, the upgrade that
 * moves existing profiles onto it, the read model's classification, organisation type and place, the sector filter that takes in
 * its industries, and the BreadcrumbList. Needs the WordPress test suite and the real tables; it removes what it creates.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\ClassificationSeeder;
use WhoIsWho\Core\Directory\ClassificationUpgrade;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileClassifier;
use WhoIsWho\Core\Directory\ProfileList;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Seo\StructuredData;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ClassificationTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Terms to remove: [term id, taxonomy].
	 *
	 * @var array<int, array{0: int, 1: string}>
	 */
	private array $terms = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! is_taxonomy_hierarchical( Taxonomies::INDUSTRY ) ) {
			( new Taxonomies() )->register();
		}
		( new ClassificationSeeder() )->seed();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->terms as list( $term_id, $taxonomy ) ) {
			if ( term_exists( $term_id, $taxonomy ) ) {
				wp_delete_term( $term_id, $taxonomy );
			}
		}
		$this->posts = array();
		$this->terms = array();
		Classification::flush();
		parent::tearDown();
	}

	/**
	 * A profile in a state.
	 *
	 * @param string               $name   Name.
	 * @param string               $type   Entity type.
	 * @param string               $state  State.
	 * @param array<string, mixed> $fields Sourced fields.
	 */
	private function profile( string $name, string $type, string $state = PubState::PUBLISHED_REVIEWED, array $fields = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => ProfileType::STATUS_HIDDEN,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => $type ) );
		$store = new ProfileFields();
		foreach ( $fields as $key => $value ) {
			$store->set( $id, $key, $value );
		}
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		return $id;
	}

	/**
	 * A term to clean up.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     Name.
	 * @param int    $parent_id Parent.
	 */
	private function term( string $taxonomy, string $name, int $parent_id = 0 ): int {
		$made = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent_id ) );
		$this->assertIsArray( $made );
		$this->terms[] = array( (int) $made['term_id'], $taxonomy );
		return (int) $made['term_id'];
	}

	public function test_REQ_009_the_seed_builds_the_tree_with_aliases_and_the_pan_african_place(): void {
		$this->assertTrue( is_taxonomy_hierarchical( Taxonomies::INDUSTRY ) );
		$sector = get_term_by( 'slug', 'industry-manufacturing', Taxonomies::INDUSTRY );
		$leaf   = get_term_by( 'slug', 'construction-cement', Taxonomies::INDUSTRY );
		$this->assertInstanceOf( \WP_Term::class, $sector );
		$this->assertInstanceOf( \WP_Term::class, $leaf );
		$this->assertSame( 0, (int) $sector->parent );
		$this->assertSame( (int) $sector->term_id, (int) $leaf->parent );
		$this->assertContains( 'Construction and materials', Classification::aliases_of( (int) $leaf->term_id ) );
		$unclassified = get_term_by( 'slug', 'unclassified', Taxonomies::INDUSTRY );
		$this->assertInstanceOf( \WP_Term::class, $unclassified );
		$this->assertSame( 0, (int) $unclassified->parent );
		$this->assertSame( 'State-owned enterprise', get_term_by( 'slug', 'state-owned-enterprise', Taxonomies::ORG_TYPE )->name );
		$pan = get_term_by( 'slug', 'pan-african', Taxonomies::PLACE );
		$this->assertInstanceOf( \WP_Term::class, $pan );
		$this->assertSame( 'PAN', get_term_meta( $pan->term_id, Taxonomies::META_ISO, true ) );
		$this->assertSame( Taxonomies::KIND_BODY, get_term_meta( $pan->term_id, Taxonomies::META_KIND, true ) );
		$this->assertSame( (int) $pan->term_id, Classification::country_id( 'PAN' ) );
		$this->assertFalse( get_taxonomy( Taxonomies::SECTOR )->show_ui, 'The sector taxonomy has no screen any more.' );
		$this->assertNotContains( Taxonomies::SECTOR, Taxonomies::all() );

		$count = (int) wp_count_terms(
			array(
				'taxonomy'   => Taxonomies::INDUSTRY,
				'hide_empty' => false,
			)
		);
		$this->assertSame( 0, ( new ClassificationSeeder() )->seed(), 'Seeding again creates nothing.' );
		$this->assertSame(
			$count,
			(int) wp_count_terms(
				array(
					'taxonomy'   => Taxonomies::INDUSTRY,
					'hide_empty' => false,
				)
			)
		);
	}

	public function test_REQ_009_matching_uses_slugs_names_aliases_and_staff_terms_but_never_a_sector(): void {
		$this->assertSame( 'oil-gas', Classification::match_industry( 'Oil and gas' ) );
		$this->assertSame( 'oil-gas', Classification::match_industry( 'OIL & GAS' ) );
		$this->assertSame( 'construction-cement', Classification::match_industry( 'construction-cement' ) );
		$this->assertNull( Classification::match_industry( 'Industry & Manufacturing' ), 'A sector name is not an industry.' );
		$this->assertNull( Classification::match_industry( 'Qwv Never Heard Of' ) );
		$this->assertSame( 'government-body', Classification::match_org_type( 'Public body' ) );

		$health = Classification::term_id( Taxonomies::INDUSTRY, 'health' );
		$staff  = $this->term( Taxonomies::INDUSTRY, 'Qwv Dentistry', (int) $health );
		update_term_meta( $staff, Taxonomies::META_ALIAS, array( 'Qwv Dental care' ) );
		Classification::flush();
		$this->assertSame( get_term( $staff )->slug, Classification::match_industry( 'qwv dental care' ), 'A staff-added leaf and its aliases match.' );
	}

	public function test_REQ_009_the_upgrade_moves_existing_profiles_onto_the_tree(): void {
		$old_flat   = $this->term( Taxonomies::INDUSTRY, 'Qwv Oil and gas' );
		$old_noise  = $this->term( Taxonomies::INDUSTRY, 'Qwv Hospitality noise' );
		$old_type   = $this->term( Taxonomies::ORG_TYPE, 'Qwv Public body' );
		$old_sector = $this->term( Taxonomies::SECTOR, 'Qwv Private' );
		$city       = $this->term( Taxonomies::PLACE, 'Qwv Port Town', (int) Classification::country_id( 'NG' ) );
		update_term_meta( $city, Taxonomies::META_KIND, Taxonomies::KIND_CITY );
		// The alias tables know the raw values without the test prefix only, so teach the tree the prefixed ones for this test.
		$oil = (int) Classification::term_id( Taxonomies::INDUSTRY, 'oil-gas' );
		update_term_meta( $oil, Taxonomies::META_ALIAS, array_merge( Classification::aliases_of( $oil ), array( 'Qwv Oil and gas' ) ) );
		$gov = (int) Classification::term_id( Taxonomies::ORG_TYPE, 'government-body' );
		update_term_meta( $gov, Taxonomies::META_ALIAS, array_merge( Classification::aliases_of( $gov ), array( 'Qwv Public body' ) ) );
		Classification::flush();

		$by_term = $this->profile( 'Upgrade Oil Qwv', 'organisation' );
		wp_set_object_terms( $by_term, array( $old_flat ), Taxonomies::INDUSTRY );
		wp_set_object_terms( $by_term, array( $old_type ), Taxonomies::ORG_TYPE );
		wp_set_object_terms( $by_term, array( $city ), Taxonomies::PLACE );
		wp_set_object_terms( $by_term, array( $old_sector ), Taxonomies::SECTOR );
		$by_text = $this->profile( 'Upgrade Text Qwv', 'individual', PubState::PUBLISHED_REVIEWED, array( 'field' => 'Politics and government' ) );
		$nothing = $this->profile( 'Upgrade Nothing Qwv', 'organisation' );
		wp_set_object_terms( $nothing, array( $old_noise ), Taxonomies::INDUSTRY );
		$story         = (int) wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Upgrade story Qwv',
				'post_status' => 'draft',
			)
		);
		$this->posts[] = $story;
		wp_set_object_terms( $story, array( $old_flat ), Taxonomies::INDUSTRY );

		$counts = ( new ClassificationUpgrade() )->run();

		$this->assertGreaterThanOrEqual( 3, $counts['profiles'] );
		$this->assertSame( $oil, (int) get_post_meta( $by_term, Classification::PRIMARY_INDUSTRY_META, true ) );
		$this->assertSame( array( $oil ), wp_get_object_terms( $by_term, Taxonomies::INDUSTRY, array( 'fields' => 'ids' ) ) );
		$this->assertSame( array( $gov ), wp_get_object_terms( $by_term, Taxonomies::ORG_TYPE, array( 'fields' => 'ids' ) ) );
		$this->assertSame( Classification::country_id( 'NG' ), (int) get_post_meta( $by_term, Classification::PRIMARY_COUNTRY_META, true ), 'The country comes from the city.' );
		$this->assertSame( (int) Classification::term_id( Taxonomies::INDUSTRY, 'government' ), (int) get_post_meta( $by_text, Classification::PRIMARY_INDUSTRY_META, true ), 'The person field maps through the aliases.' );
		$this->assertSame( Classification::unclassified_id(), (int) get_post_meta( $nothing, Classification::PRIMARY_INDUSTRY_META, true ) );
		$this->assertSame( (int) Classification::term_id( Taxonomies::ORG_TYPE, 'other' ), wp_get_object_terms( $nothing, Taxonomies::ORG_TYPE, array( 'fields' => 'ids' ) )[0] ?? 0 );

		$this->assertNull( term_exists( $old_flat, Taxonomies::INDUSTRY ), 'The old flat term is folded into the tree.' );
		$this->assertNull( term_exists( $old_noise, Taxonomies::INDUSTRY ), 'An old term the tree does not know is removed.' );
		$this->assertSame( array( $oil ), wp_get_object_terms( $story, Taxonomies::INDUSTRY, array( 'fields' => 'ids' ) ), 'Its story moves with it.' );
		$this->assertSame(
			array(),
			get_terms(
				array(
					'taxonomy'   => Taxonomies::SECTOR,
					'hide_empty' => false,
				)
			),
			'The sector taxonomy is empty.'
		);

		$again = ( new ClassificationUpgrade() )->run();
		$this->assertSame( 0, $again['profiles'], 'Running again changes nothing.' );
		$this->assertSame( 0, $again['terms_removed'] + $again['terms_merged'] + $again['sectors_removed'] );
	}

	public function test_REQ_009_classification_gives_country_sector_industry_and_extras_with_list_links(): void {
		$id = $this->profile( 'Classified Org Qwv', 'organisation' );
		( new ProfileClassifier() )->assign(
			$id,
			'organisation',
			array(
				'primary_industry' => 'construction-cement',
				'industries'       => array( 'food-beverage', 'oil-gas', 'industry-manufacturing', 'construction-cement' ),
				'country'          => 'NG',
				'org_type'         => 'listed-company',
			)
		);
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		$tree = $profile->classification();
		$this->assertSame( 'Nigeria', $tree['country']['name'] );
		$this->assertSame( 'NG', $tree['country']['code'] );
		$this->assertSame( home_url( '/directory/?country=nigeria' ), $tree['country']['url'] );
		$this->assertSame( 'Industry & Manufacturing', $tree['sector']['name'], 'Names are plain text, not entities.' );
		$this->assertSame( home_url( '/directory/?country=nigeria&industry=industry-manufacturing' ), $tree['sector']['url'] );
		$this->assertSame( 'construction-cement', $tree['industry']['slug'] );
		$this->assertSame( home_url( '/directory/?country=nigeria&industry=construction-cement' ), $tree['industry']['url'] );
		$this->assertEqualsCanonicalizing( array( 'food-beverage', 'oil-gas' ), array_column( $tree['extra'], 'slug' ), 'A sector is never an extra.' );
		$this->assertSame(
			array(
				'name' => 'Listed company',
				'slug' => 'listed-company',
			),
			$profile->org_type()
		);
		$this->assertSame(
			array(
				'city'    => null,
				'country' => 'Nigeria',
			),
			$profile->place()
		);
	}

	public function test_REQ_009_an_unclassified_profile_has_no_sector_or_industry_and_a_person_no_org_type(): void {
		$id  = $this->profile( 'Unclassified Person Qwv', 'individual' );
		$out = ( new ProfileClassifier() )->assign(
			$id,
			'individual',
			array(
				'primary_industry' => 'qwv-unknown',
				'country'          => 'XX',
				'org_type'         => 'company',
			)
		);
		$this->assertSame( Classification::unclassified_id(), $out['primary_industry'] );
		$this->assertNull( $out['primary_country'], 'XX is not an ISO code, so no country is made.' );
		$profile = PublicProfile::find_by_id( $id );
		$tree    = $profile->classification();
		$this->assertNull( $tree['sector'] );
		$this->assertNull( $tree['industry'] );
		$this->assertNull( $tree['country'] );
		$this->assertSame( array(), $tree['extra'] );
		$this->assertNull( $profile->org_type() );
		$this->assertNull( $profile->place() );
	}

	public function test_REQ_009_a_missing_country_is_created_only_for_a_real_iso_code(): void {
		$this->assertNull( Classification::country_id( 'FR' ) );
		$fr = Classification::ensure_country( 'FR' );
		$this->assertIsInt( $fr );
		$this->terms[] = array( $fr, Taxonomies::PLACE );
		$this->assertSame( 'France', get_term( $fr )->name );
		$this->assertSame( Taxonomies::KIND_COUNTRY, get_term_meta( $fr, Taxonomies::META_KIND, true ) );
		$this->assertSame( $fr, Classification::ensure_country( 'fr' ), 'Found again by code.' );
		$this->assertNull( Classification::ensure_country( 'QQ' ) );
	}

	public function test_REQ_007_a_sector_filter_takes_in_its_industries(): void {
		$a          = $this->profile( 'Sector Filter Cement Qwv', 'organisation' );
		$b          = $this->profile( 'Sector Filter Chem Qwv', 'organisation' );
		$c          = $this->profile( 'Sector Filter Bank Qwv', 'organisation' );
		$classifier = new ProfileClassifier();
		$classifier->assign(
			$a,
			'organisation',
			array(
				'primary_industry' => 'construction-cement',
				'country'          => 'KE',
			)
		);
		$classifier->assign(
			$b,
			'organisation',
			array(
				'primary_industry' => 'chemicals',
				'country'          => 'GH',
			)
		);
		$classifier->assign(
			$c,
			'organisation',
			array(
				'primary_industry' => 'banking',
				'country'          => 'KE',
			)
		);
		$list  = new ProfileList();
		$names = static fn ( array $result ): array => array_values( array_filter( array_map( static fn ( PublicProfile $p ): string => $p->name, $result['profiles'] ), static fn ( string $n ): bool => false !== strpos( $n, 'Sector Filter' ) ) );
		$this->assertEqualsCanonicalizing( array( 'Sector Filter Cement Qwv', 'Sector Filter Chem Qwv' ), $names( $list->query( 'all', array( 'industry' => 'industry-manufacturing' ) ) ) );
		$this->assertSame( array( 'Sector Filter Cement Qwv' ), $names( $list->query( 'all', array( 'industry' => 'construction-cement' ) ) ) );
		$this->assertSame(
			array( 'Sector Filter Cement Qwv' ),
			$names(
				$list->query(
					'all',
					array(
						'industry' => 'industry-manufacturing',
						'country'  => 'kenya',
					)
				)
			)
		);
		$slugs = array_column( $list->industry_counts(), 'slug' );
		$this->assertNotContains( 'unclassified', $slugs, 'Unclassified is not public navigation.' );
		$rows = array_column( $list->industry_counts(), null, 'slug' );
		$this->assertGreaterThanOrEqual( 2, $rows['industry-manufacturing']['count'], 'A sector counts the profiles in its industries.' );
		$this->assertSame( (int) $rows['industry-manufacturing']['term_id'], $rows['chemicals']['parent'] );
	}

	public function test_REQ_006_breadcrumb_list_follows_country_sector_industry_name(): void {
		$id = $this->profile( 'Breadcrumb Org Qwv', 'organisation' );
		( new ProfileClassifier() )->assign(
			$id,
			'organisation',
			array(
				'primary_industry' => 'oil-gas',
				'country'          => 'NG',
			)
		);
		$profile = PublicProfile::find_by_id( $id );
		$data    = ( new StructuredData() )->breadcrumb_for( $profile );
		$this->assertSame( 'BreadcrumbList', $data['@type'] );
		$this->assertSame( array( 'Nigeria', 'Energy & Resources', 'Oil & Gas', 'Breadcrumb Org Qwv' ), array_column( $data['itemListElement'], 'name' ) );
		$this->assertSame( array( 1, 2, 3, 4 ), array_column( $data['itemListElement'], 'position' ) );
		$this->assertSame( $profile->url, $data['itemListElement'][3]['item'] );
		$html = ( new StructuredData() )->render_breadcrumb( $profile );
		$this->assertStringContainsString( '"BreadcrumbList"', $html );
		$this->assertStringNotContainsString( '&amp;', $html );

		$plain = $this->profile( 'Breadcrumb Plain Qwv', 'individual' );
		( new ProfileClassifier() )->assign( $plain, 'individual', array( 'country' => 'GH' ) );
		$this->assertSame( array( 'Ghana', 'Breadcrumb Plain Qwv' ), array_column( ( new StructuredData() )->breadcrumb_for( PublicProfile::find_by_id( $plain ) )['itemListElement'], 'name' ), 'Unclassified: Country, then the name.' );

		$none = $this->profile( 'Breadcrumb None Qwv', 'individual' );
		$this->assertSame( array( 'Directory', 'Breadcrumb None Qwv' ), array_column( ( new StructuredData() )->breadcrumb_for( PublicProfile::find_by_id( $none ) )['itemListElement'], 'name' ), 'No country: the directory, then the name.' );

		$pending = $this->profile( 'Breadcrumb Pending Qwv', 'individual', PubState::PUBLISHED_PENDING );
		$this->assertNull( ( new StructuredData() )->breadcrumb_for( PublicProfile::find_by_id( $pending ) ), 'No schema while pending.' );
	}
}
