<?php
/**
 * The winners query: filters, search, sorting, paging and URL state (REQ-246, REQ-198). Needs the WordPress test suite and the
 * real profile and awards tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\WinnersQuery;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\WpTestCase;

final class WinnersQueryTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Terms to remove.
	 *
	 * @var int[]
	 */
	private array $terms = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES, AwardsTables::EDITIONS ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->terms as $id ) {
			wp_delete_term( $id, Taxonomies::PLACE );
		}
		$this->posts = array();
		$this->terms = array();
		parent::tearDown();
	}

	private function country( string $name ): int {
		$term = wp_insert_term( $name, Taxonomies::PLACE );
		$id   = (int) ( is_array( $term ) ? $term['term_id'] : 0 );
		update_term_meta( $id, Taxonomies::META_KIND, Taxonomies::KIND_COUNTRY );
		$this->terms[] = $id;
		return $id;
	}

	private function profile( string $name, string $type, int $country = 0 ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => $type ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		if ( $country > 0 ) {
			wp_set_object_terms( $id, array( $country ), Taxonomies::PLACE );
		}
		return $id;
	}

	private function edition( int $year ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::EDITIONS ),
			array(
				'title'      => 'Awards ' . $year,
				'year'       => $year,
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function category( string $title, string $group ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => $group,
				'title'      => $title,
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function winner( int $profile, int $category, int $edition, string $citation = '', string $status = 'live' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'edition_id'  => $edition,
				'category_id' => $category,
				'profile_id'  => $profile,
				'citation'    => '' === $citation ? null : $citation,
				'evidence'    => 'private nominator evidence',
				'stage'       => 'winner',
				'status'      => $status,
				'source'      => 'staff',
				'created_at'  => '2026-11-12 10:00:00',
				'updated_at'  => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Three winners over two editions, in two groups and two countries, plus one Not live.
	 *
	 * @return array<string, int>
	 */
	private function seed(): array {
		$ng   = $this->country( 'Nigeriaq' );
		$gh   = $this->country( 'Ghanaq' );
		$e25  = $this->edition( 2025 );
		$e26  = $this->edition( 2026 );
		$tech = $this->category( 'Digital Pioneer', 'technology-innovation' );
		$biz  = $this->category( 'Founder of the Year', 'entrepreneurship-business' );
		$this->winner( $this->profile( 'Ada Okafor Zq', 'individual', $ng ), $biz, $e26, 'Built a continental payments network' );
		$this->winner( $this->profile( 'Bright Labs Zq', 'organisation', $gh ), $tech, $e26, 'Open data tools for schools' );
		$this->winner( $this->profile( 'Chidi Eze Zq', 'entrepreneur', $ng ), $tech, $e25 );
		$this->winner( $this->profile( 'Hidden Winner Zq', 'individual', $ng ), $tech, $e26, '', 'not_live' );
		return array(
			'ng'   => $ng,
			'tech' => $tech,
			'biz'  => $biz,
		);
	}

	private function names( array $result ): array {
		return array_map( static fn ( $w ) => $w->name, $result['items'] );
	}

	public function test_REQ_246_winners_filter_by_year_group_category_type_country(): void {
		$ids   = $this->seed();
		$query = new WinnersQuery();
		$all   = $query->run( array() );
		$this->assertSame( 3, $all['total'], 'Not live winners never appear' );
		$this->assertSame( array( 'Ada Okafor Zq', 'Bright Labs Zq', 'Chidi Eze Zq' ), $this->names( $all ), 'newest edition first' );
		$this->assertSame( array( 2026, 2025 ), array_keys( $all['by_year'] ) );
		$this->assertSame(
			array(
				2026 => 2,
				2025 => 1,
			),
			$all['years']
		);

		$this->assertSame( array( 'Chidi Eze Zq' ), $this->names( $query->run( array( 'year' => '2025' ) ) ) );
		$this->assertSame( array( 'Ada Okafor Zq' ), $this->names( $query->run( array( 'group' => 'entrepreneurship-business' ) ) ) );
		$this->assertSame( array( 'Bright Labs Zq', 'Chidi Eze Zq' ), $this->names( $query->run( array( 'category' => (string) $ids['tech'] ) ) ) );
		$this->assertSame( array( 'Bright Labs Zq' ), $this->names( $query->run( array( 'type' => 'organisation' ) ) ) );
		$this->assertSame( array( 'Ada Okafor Zq', 'Chidi Eze Zq' ), $this->names( $query->run( array( 'type' => 'person' ) ) ) );
		$this->assertSame( array( 'Ada Okafor Zq', 'Chidi Eze Zq' ), $this->names( $query->run( array( 'country' => 'nigeriaq' ) ) ) );
		$this->assertSame(
			array( 'Chidi Eze Zq' ),
			$this->names(
				$query->run(
					array(
						'country' => 'nigeriaq',
						'year'    => '2025',
					)
				)
			)
		);
		$this->assertSame( array( 'Ghanaq', 'Nigeriaq' ), array_values( $all['countries'] ) );
		$this->assertSame(
			array( 'Ada Okafor Zq', 'Chidi Eze Zq' ),
			$this->names(
				$query->run(
					array(
						'sort' => 'name',
						'type' => 'person',
					)
				)
			)
		);
		$this->assertSame( array( 'Ada Okafor Zq', 'Bright Labs Zq', 'Chidi Eze Zq' ), $this->names( $query->run( array( 'sort' => 'name' ) ) ) );

		// The year facet shows what picking a year would give under the other filters.
		$this->assertSame(
			array(
				2026 => 1,
				2025 => 1,
			),
			$query->run( array( 'country' => 'nigeriaq' ) )['years']
		);
	}

	public function test_REQ_246_winners_search_by_name_category_or_citation(): void {
		$this->seed();
		$query = new WinnersQuery();
		$this->assertSame( array( 'Ada Okafor Zq' ), $this->names( $query->run( array( 'q' => 'okafor' ) ) ) );
		$this->assertSame( array( 'Ada Okafor Zq' ), $this->names( $query->run( array( 'q' => 'founder of the year' ) ) ) );
		$this->assertSame( array( 'Bright Labs Zq' ), $this->names( $query->run( array( 'q' => 'open data' ) ) ) );
		$this->assertSame( array( 'Bright Labs Zq', 'Chidi Eze Zq' ), $this->names( $query->run( array( 'q' => 'digital pioneer' ) ) ) );
		$this->assertSame( array(), $this->names( $query->run( array( 'q' => 'private nominator evidence' ) ) ), 'evidence is never searchable' );
		$this->assertSame( array(), $this->names( $query->run( array( 'q' => 'hidden' ) ) ), 'a Not live winner is never found' );
	}

	public function test_REQ_246_winners_filters_round_trip_through_the_url(): void {
		$raw    = array(
			'year'     => '2026',
			'group'    => 'technology-innovation',
			'category' => '7',
			'type'     => 'organisation',
			'country'  => 'ghanaq',
			'q'        => 'open data',
			'sort'     => 'name',
			'page'     => '2',
		);
		$params = WinnersQuery::params( $raw );
		$string = WinnersQuery::query_string( $params );
		parse_str( $string, $parsed );
		$this->assertSame( $params, WinnersQuery::params( $parsed ) );
		$this->assertSame( '', WinnersQuery::query_string( WinnersQuery::params( array() ) ), 'defaults stay out of the address' );
		$bad = WinnersQuery::params(
			array(
				'year'  => 'abc',
				'group' => 'nope',
				'type'  => 'robot',
				'sort'  => 'random',
				'page'  => '-4',
			)
		);
		$this->assertSame( 0, $bad['year'] );
		$this->assertSame( '', $bad['group'] );
		$this->assertSame( '', $bad['type'] );
		$this->assertSame( 'newest', $bad['sort'] );
		$this->assertSame( 1, $bad['page'] );
	}

	public function test_REQ_246_winners_are_paged_twelve_at_a_time(): void {
		$this->seed();
		$edition = $this->edition( 2027 );
		$cat     = $this->category( 'Mass Category', 'technology-innovation' );
		for ( $i = 1; $i <= 13; $i++ ) {
			$this->winner( $this->profile( 'Bulk Winner ' . str_pad( (string) $i, 2, '0', STR_PAD_LEFT ), 'individual' ), $cat, $edition );
		}
		$page1 = ( new WinnersQuery() )->run( array() );
		$this->assertSame( 16, $page1['total'] );
		$this->assertSame( 2, $page1['pages'] );
		$this->assertCount( 12, $page1['items'] );
		$page2 = ( new WinnersQuery() )->run( array( 'page' => '2' ) );
		$this->assertCount( 4, $page2['items'] );
		$this->assertSame( 2, ( new WinnersQuery() )->run( array( 'page' => '99' ) )['page'], 'a page past the end clamps to the last' );
	}
}
