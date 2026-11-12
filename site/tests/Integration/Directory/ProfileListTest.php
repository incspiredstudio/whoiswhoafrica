<?php
/**
 * The public list queries (REQ-007, REQ-218, REQ-208, REQ-009). Needs the WordPress test suite. Plain TestCase because it needs
 * the real state table; its posts and terms are removed in tearDown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\ProfileList;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileListTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Terms to remove: id => taxonomy.
	 *
	 * @var array<int, string>
	 */
	private array $terms = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! taxonomy_exists( Taxonomies::INDUSTRY ) ) {
			( new Taxonomies() )->register();
		}
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->terms as $id => $taxonomy ) {
			wp_delete_term( $id, $taxonomy );
		}
		$this->posts = array();
		$this->terms = array();
		parent::tearDown();
	}

	private function term( string $name, string $taxonomy, array $meta = array(), int $parent_id = 0 ): int {
		$made = wp_insert_term(
			$name,
			$taxonomy,
			array( 'parent' => $parent_id )
		);
		$id   = (int) $made['term_id'];
		foreach ( $meta as $key => $value ) {
			update_term_meta( $id, $key, $value );
		}
		$this->terms[ $id ] = $taxonomy;
		return $id;
	}

	/**
	 * Create a profile and give it terms.
	 *
	 * @param string                $name  Name.
	 * @param string                $type  Entity type.
	 * @param string                $state Target state: pending, reviewed or imported.
	 * @param array<string, int[]>  $terms Taxonomy => term ids.
	 */
	private function profile( string $name, string $type, string $state, array $terms = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, 'imported' === $state ? PubState::IMPORTED_NOT_PUBLIC : PubState::PUBLISHED_PENDING, array( 'entity_type' => $type ) );
		if ( 'reviewed' === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		foreach ( $terms as $taxonomy => $ids ) {
			wp_set_object_terms( $id, $ids, $taxonomy );
		}
		return $id;
	}

	public function test_REQ_007_list_returns_sixteen_per_page(): void {
		$industry = $this->term( 'Sixteen Qwv', Taxonomies::INDUSTRY );
		for ( $i = 1; $i <= 18; $i++ ) {
			$this->profile( sprintf( 'Org %02d Qwv', $i ), 'organisation', 'reviewed', array( Taxonomies::INDUSTRY => array( $industry ) ) );
		}
		$list = new ProfileList();
		$one  = $list->query( 'businesses', array( 'industry' => 'sixteen-qwv' ) );
		$this->assertCount( 16, $one['profiles'] );
		$this->assertSame( 18, $one['total'] );
		$this->assertSame( 2, $one['pages'] );
		$this->assertSame( 'Org 01 Qwv', $one['profiles'][0]->name, 'Name A to Z by default.' );
		$two = $list->query(
			'businesses',
			array(
				'industry' => 'sixteen-qwv',
				'page'     => 2,
			)
		);
		$this->assertCount( 2, $two['profiles'] );
		$this->assertSame(
			2,
			$list->query(
				'all',
				array(
					'industry' => 'sixteen-qwv',
					'page'     => 99,
				)
			)['page'],
			'A page past the end shows the last page.'
		);
	}

	public function test_REQ_007_filters_apply_from_url_parameters(): void {
		$ind_a   = $this->term( 'Filter A Qwv', Taxonomies::INDUSTRY );
		$ind_b   = $this->term( 'Filter B Qwv', Taxonomies::INDUSTRY );
		$country = $this->term( 'Qwvland', Taxonomies::PLACE, array( Taxonomies::META_KIND => Taxonomies::KIND_COUNTRY ) );
		$city    = $this->term( 'Qwvtown', Taxonomies::PLACE, array( Taxonomies::META_KIND => Taxonomies::KIND_CITY ), $country );
		$this->profile(
			'In A Town Qwv',
			'organisation',
			'reviewed',
			array(
				Taxonomies::INDUSTRY => array( $ind_a ),
				Taxonomies::PLACE    => array( $city ),
			)
		);
		$this->profile( 'In B Qwv', 'organisation', 'reviewed', array( Taxonomies::INDUSTRY => array( $ind_b ) ) );
		$list = new ProfileList();

		$only_a = $list->query( 'all', array( 'industry' => 'filter-a-qwv' ) );
		$this->assertSame( array( 'In A Town Qwv' ), array_map( static fn ( $p ) => $p->name, $only_a['profiles'] ) );
		$this->assertSame( $only_a['profiles'][0]->id, $list->query( 'all', array( 'industry' => 'filter-a-qwv' ) )['profiles'][0]->id, 'The same URL gives the same set.' );

		$by_country = $list->query( 'all', array( 'country' => 'qwvland' ) );
		$this->assertCount( 1, $by_country['profiles'], 'A country includes its cities.' );
		$this->assertCount( 1, $list->query( 'all', array( 'city' => 'qwvtown' ) )['profiles'] );
		$this->assertCount( 0, $list->query( 'all', array( 'city' => 'qwvland' ) )['profiles'], 'A country is not a city.' );
		$this->assertCount( 0, $list->query( 'all', array( 'industry' => 'no-such-term-qwv' ) )['profiles'], 'An unknown term matches nothing.' );
	}

	public function test_REQ_008_q_narrows_by_name_together_with_other_filters(): void {
		$ind = $this->term( 'Narrow Ind Qwv', Taxonomies::INDUSTRY );
		$this->profile( 'Zanzibar Foods Qwv', 'organisation', 'reviewed', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		$this->profile( 'Zanzibar Bank Qwv', 'organisation', 'reviewed' );
		$this->profile( 'Other Foods Qwv', 'organisation', 'reviewed', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		$this->profile( 'Zanzibar Pending Qwv', 'organisation', 'pending' );
		$list = new ProfileList();

		$q = $list->query( 'all', array( 'q' => '  zanzibar  ' ) );
		$this->assertEqualsCanonicalizing( array( 'Zanzibar Foods Qwv', 'Zanzibar Bank Qwv' ), array_map( static fn ( $p ) => $p->name, $q['profiles'] ), 'q matches the name, ignoring case and spaces around it.' );
		$this->assertSame( 2, $q['total'] );
		$this->assertSame( 'zanzibar', $q['applied']['q'] );

		$both = $list->query(
			'all',
			array(
				'q'        => 'Zanzibar',
				'industry' => 'narrow-ind-qwv',
			)
		);
		$this->assertSame( array( 'Zanzibar Foods Qwv' ), array_map( static fn ( $p ) => $p->name, $both['profiles'] ) );
		$this->assertSame( 1, $both['total'] );
		$this->assertSame( 0, $list->query( 'all', array( 'q' => '100%_Qwv' ) )['total'], 'LIKE wildcards in q are literal.' );
		$this->assertSame( 0, $list->query( 'all', array( 'q' => str_repeat( 'x', 200 ) . 'Zanzibar' ) )['total'], 'A long q is cut to 80 characters and cannot break the query.' );
		$this->assertArrayNotHasKey( 'q', $list->query( 'all', array( 'q' => '   ' ) )['applied'] );
	}

	public function test_REQ_007_profession_filter_only_on_people_lists(): void {
		$prof     = $this->term( 'Surgeon Qwv', Taxonomies::PROFESSION );
		$org_type = $this->term( 'Bank Qwv', Taxonomies::ORG_TYPE );
		$this->profile( 'Dr Qwv', 'individual', 'reviewed', array( Taxonomies::PROFESSION => array( $prof ) ) );
		$this->profile( 'Qwv Bank', 'organisation', 'reviewed', array( Taxonomies::ORG_TYPE => array( $org_type ) ) );
		$list = new ProfileList();

		$this->assertContains( 'profession', ProfileList::filters_for( 'people' ) );
		$this->assertContains( 'profession', ProfileList::filters_for( 'entrepreneurs' ) );
		$this->assertNotContains( 'profession', ProfileList::filters_for( 'businesses' ) );
		$this->assertNotContains( 'profession', ProfileList::filters_for( 'all' ) );
		$this->assertContains( 'org_type', ProfileList::filters_for( 'institutions' ) );
		$this->assertNotContains( 'org_type', ProfileList::filters_for( 'people' ) );

		$this->assertCount( 1, $list->query( 'people', array( 'profession' => 'surgeon-qwv' ) )['profiles'] );
		$ignored = $list->query( 'businesses', array( 'profession' => 'surgeon-qwv' ) );
		$this->assertArrayNotHasKey( 'profession', $ignored['applied'], 'An inapplicable filter is ignored.' );
		$this->assertCount( 1, $list->query( 'businesses', array( 'org_type' => 'bank-qwv' ) )['profiles'] );
		$this->assertCount(
			0,
			$list->query(
				'people',
				array(
					'org_type'   => 'bank-qwv',
					'profession' => 'no-such-qwv',
				)
			)['profiles']
		);
	}

	public function test_REQ_007_lists_hold_only_their_entity_types(): void {
		$ind = $this->term( 'Types Qwv', Taxonomies::INDUSTRY );
		foreach ( array( 'individual', 'entrepreneur', 'organisation', 'institution' ) as $type ) {
			$this->profile( 'T ' . $type . ' Qwv', $type, 'reviewed', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		}
		$list = new ProfileList();
		$this->assertSame( 4, $list->query( 'all', array( 'industry' => 'types-qwv' ) )['total'] );
		foreach ( array(
			'people'        => 'individual',
			'entrepreneurs' => 'entrepreneur',
			'businesses'    => 'organisation',
			'institutions'  => 'institution',
		) as $name => $type ) {
			$result = $list->query( $name, array( 'industry' => 'types-qwv' ) );
			$this->assertSame( 1, $result['total'], $name );
			$this->assertSame( $type, $result['profiles'][0]->entity_type );
		}
	}

	public function test_REQ_218_pending_profile_never_listed(): void {
		$ind = $this->term( 'Pending Qwv', Taxonomies::INDUSTRY );
		$this->profile( 'Pending One Qwv', 'organisation', 'pending', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		$this->profile( 'Imported One Qwv', 'organisation', 'imported', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		$reviewed = $this->profile( 'Reviewed One Qwv', 'organisation', 'reviewed', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		$result   = ( new ProfileList() )->query( 'all', array( 'industry' => 'pending-qwv' ) );
		$this->assertSame( array( $reviewed ), array_map( static fn ( $p ) => $p->id, $result['profiles'] ) );
		$this->assertSame( 1, $result['total'] );
	}

	public function test_REQ_007_sorting_has_no_commercial_signal(): void {
		$ind = $this->term( 'Sort Qwv', Taxonomies::INDUSTRY );
		$b   = $this->profile( 'B Sort Qwv', 'organisation', 'reviewed', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		$a   = $this->profile( 'A Sort Qwv', 'organisation', 'reviewed', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => '2030-01-01 00:00:00' ), array( 'ID' => $b ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$list = new ProfileList();
		$this->assertSame( array( $a, $b ), array_map( static fn ( $p ) => $p->id, $list->query( 'all', array( 'industry' => 'sort-qwv' ) )['profiles'] ) );
		$this->assertSame(
			array( $b, $a ),
			array_map(
				static fn ( $p ) => $p->id,
				$list->query(
					'all',
					array(
						'industry' => 'sort-qwv',
						'sort'     => 'updated',
					)
				)['profiles']
			)
		);
		$this->assertSame(
			array( $a, $b ),
			array_map(
				static fn ( $p ) => $p->id,
				$list->query(
					'all',
					array(
						'industry' => 'sort-qwv',
						'sort'     => 'sponsored',
					)
				)['profiles']
			),
			'An unknown sort falls back to name.'
		);
	}

	public function test_REQ_208_industry_counts_exclude_non_public(): void {
		$ind = $this->term( 'Counts Qwv', Taxonomies::INDUSTRY );
		$this->term( 'Empty Counts Qwv', Taxonomies::INDUSTRY );
		$this->profile( 'C1 Qwv', 'organisation', 'reviewed', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		$this->profile( 'C2 Qwv', 'individual', 'reviewed', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		$this->profile( 'C3 Qwv', 'organisation', 'pending', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		$this->profile( 'C4 Qwv', 'organisation', 'imported', array( Taxonomies::INDUSTRY => array( $ind ) ) );
		$by_slug = array();
		foreach ( ( new ProfileList() )->industry_counts() as $row ) {
			$by_slug[ $row['slug'] ] = $row['count'];
		}
		$this->assertSame( 2, $by_slug['counts-qwv'] );
		$this->assertSame( 0, $by_slug['empty-counts-qwv'] );
	}
}
