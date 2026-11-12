<?php
/**
 * Q82 measurement (REQ-040, REQ-007): the list, filter, search and industry count queries at 10,000 fictional profiles, on the
 * profile state table (the shipped design) and on native post meta (the alternative). The test seeds 10,000 profiles with
 * fictional names across all types and states, times each query, asserts the budget below and writes the figures to a JSON file
 * in the system temp folder so they can be attached to the issue. Plain TestCase: it needs the real tables, and removes its
 * rows afterwards.
 *
 * Budget agreed in issue #270 (a shared CI runner, cold query cache): the first page of a list, a filtered list and a text search
 * each answer within 500 ms; the industry counts within 1000 ms.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Performance;

use WhoIsWho\Core\Directory\ProfileList;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class ListQueryAt10000Test extends TestCase {

	private const PROFILES = 10000;
	private const BATCH    = 1000;

	/**
	 * Budgets in seconds.
	 */
	private const BUDGET_LIST       = 0.5;
	private const BUDGET_INDUSTRIES = 1.0;

	/**
	 * Industry term ids created for the run.
	 *
	 * @var int[]
	 */
	private array $terms = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		if ( ! taxonomy_exists( Taxonomies::INDUSTRY ) ) {
			( new Taxonomies() )->register();
		}
		$this->clean();
	}

	protected function tearDown(): void {
		$this->clean();
		parent::tearDown();
	}

	/**
	 * Remove every seeded row.
	 */
	private function clean(): void {
		global $wpdb;
		$posts = $wpdb->posts;
		// phpcs:disable WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names; fixture clean-up.
		$wpdb->query( "DELETE tr FROM {$wpdb->term_relationships} tr INNER JOIN {$posts} p ON p.ID = tr.object_id WHERE p.post_type = '" . ProfileType::POST_TYPE . "' AND p.post_title LIKE 'Perf %'" );
		$wpdb->query( "DELETE m FROM {$wpdb->postmeta} m INNER JOIN {$posts} p ON p.ID = m.post_id WHERE p.post_type = '" . ProfileType::POST_TYPE . "' AND p.post_title LIKE 'Perf %'" );
		$wpdb->query( 'DELETE s FROM ' . StateRepository::table() . " s INNER JOIN {$posts} p ON p.ID = s.profile_id WHERE p.post_type = '" . ProfileType::POST_TYPE . "' AND p.post_title LIKE 'Perf %'" );
		$wpdb->query( "DELETE FROM {$posts} WHERE post_type = '" . ProfileType::POST_TYPE . "' AND post_title LIKE 'Perf %'" );
		// phpcs:enable
		foreach ( $this->terms as $term_id ) {
			wp_delete_term( $term_id, Taxonomies::INDUSTRY );
		}
		$this->terms = array();
	}

	/**
	 * Insert the profiles, their state rows, one industry term each and, for the comparison, the state as post meta.
	 *
	 * @return string[] Industry slugs.
	 */
	private function seed(): array {
		global $wpdb;
		$slugs = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$term = wp_insert_term( 'Perf industry ' . $i, Taxonomies::INDUSTRY, array( 'slug' => 'perf-industry-' . $i ) );
			if ( is_array( $term ) ) {
				$this->terms[] = (int) $term['term_id'];
				$slugs[]       = 'perf-industry-' . $i;
			}
		}
		$tt_ids = array();
		foreach ( $this->terms as $term_id ) {
			$term     = get_term( $term_id, Taxonomies::INDUSTRY );
			$tt_ids[] = $term instanceof \WP_Term ? (int) $term->term_taxonomy_id : 0;
		}
		$states = PubState::all();
		$types  = StateRepository::ENTITY_TYPES;
		$first  = (int) $wpdb->get_var( "SELECT COALESCE(MAX(ID),0) FROM {$wpdb->posts}" ) + 1; // phpcs:ignore WordPress.DB
		$now    = gmdate( 'Y-m-d H:i:s' );

		for ( $start = 0; $start < self::PROFILES; $start += self::BATCH ) {
			$post_rows  = array();
			$state_rows = array();
			$rel_rows   = array();
			$meta_rows  = array();
			for ( $n = $start; $n < $start + self::BATCH; $n++ ) {
				$id           = $first + $n;
				$state        = $states[ $n % count( $states ) ];
				$type         = $types[ $n % count( $types ) ];
				$title        = 'Perf ' . substr( md5( (string) $n ), 0, 8 ) . ' ' . $n;
				$post_rows[]  = $wpdb->prepare( '(%d,1,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,0,0,%s,%s,0)', $id, $now, $now, '', $title, '', 'publish', 'closed', 'closed', '', 'perf-' . $n, '', '', $now, $now, '', 'perf-' . $n, ProfileType::POST_TYPE, '' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- placeholders counted for the fixed column list.
				$state_rows[] = $wpdb->prepare( '(%d,%s,%s,%s,%s)', $id, $state, $type, 'import', $now );
				$rel_rows[]   = $wpdb->prepare( '(%d,%d,0)', $id, $tt_ids[ $n % count( $tt_ids ) ] );
				$meta_rows[]  = $wpdb->prepare( '(%d,%s,%s),(%d,%s,%s)', $id, 'wiw_pub_state', $state, $id, 'wiw_entity_type', $type );
			}
			// phpcs:disable WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixture rows built with prepare() above; fixed table names.
			$wpdb->query( "INSERT INTO {$wpdb->posts} (ID,post_author,post_date,post_date_gmt,post_content,post_title,post_excerpt,post_status,comment_status,ping_status,post_password,post_name,to_ping,pinged,post_modified,post_modified_gmt,post_content_filtered,guid,post_parent,menu_order,post_type,post_mime_type,comment_count) VALUES " . implode( ',', $post_rows ) );
			$wpdb->query( 'INSERT INTO ' . StateRepository::table() . ' (profile_id,pub_state,entity_type,origin,updated_at) VALUES ' . implode( ',', $state_rows ) );
			$wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id,term_taxonomy_id,term_order) VALUES " . implode( ',', $rel_rows ) );
			$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES " . implode( ',', $meta_rows ) );
			// phpcs:enable
		}
		return $slugs;
	}

	/**
	 * Median seconds of three runs.
	 *
	 * @param callable $run What to time.
	 */
	private function time( callable $run ): float {
		$times = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$start = microtime( true );
			$run();
			$times[] = microtime( true ) - $start;
		}
		sort( $times );
		return $times[1];
	}

	/**
	 * The same first-page list on native post meta: two meta joins instead of the state table.
	 */
	private function native_meta_list(): void {
		global $wpdb;
		$states = array();
		foreach ( PubState::all() as $state ) {
			if ( \WhoIsWho\Core\Review\StatePolicy::for_state( $state )->in_lists ) {
				$states[] = $state;
			}
		}
		$marks = implode( ',', array_fill( 0, count( $states ), '%s' ) );
		$args  = array_merge( array( ProfileType::POST_TYPE, 'publish' ), $states, array( 'organisation', 16 ) );
		// phpcs:disable WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- measurement of the alternative design; fixed names, values prepared.
		$from = " FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} ms ON ms.post_id = p.ID AND ms.meta_key = 'wiw_pub_state' INNER JOIN {$wpdb->postmeta} mt ON mt.post_id = p.ID AND mt.meta_key = 'wiw_entity_type'";
		$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*){$from} WHERE p.post_type = %s AND p.post_status = %s AND ms.meta_value IN ({$marks}) AND mt.meta_value = %s", array_slice( $args, 0, -1 ) ) );
		$wpdb->get_col( $wpdb->prepare( "SELECT p.ID{$from} WHERE p.post_type = %s AND p.post_status = %s AND ms.meta_value IN ({$marks}) AND mt.meta_value = %s ORDER BY p.post_title ASC, p.ID ASC LIMIT %d", $args ) );
		// phpcs:enable
	}

	public function test_REQ_040_list_query_within_budget_at_10000_profiles(): void {
		$slugs = $this->seed();
		$this->assertCount( 12, $slugs );
		$list = new ProfileList();

		$figures = array(
			'profiles'             => self::PROFILES,
			'budget_list_s'        => self::BUDGET_LIST,
			'budget_industries_s'  => self::BUDGET_INDUSTRIES,
			'list_all_state_table' => $this->time( static fn () => $list->query( 'all', array( 'page' => 1 ) ) ),
			'list_deep_page'       => $this->time( static fn () => $list->query( 'all', array( 'page' => 300 ) ) ),
			'list_businesses'      => $this->time( static fn () => $list->query( 'businesses', array() ) ),
			'list_filter_industry' => $this->time( static fn () => $list->query( 'all', array( 'industry' => $slugs[3] ) ) ),
			'list_sorted_updated'  => $this->time( static fn () => $list->query( 'all', array( 'sort' => ProfileList::SORT_UPDATED ) ) ),
			'list_text_search'     => $this->time( static fn () => $list->query( 'all', array( 'q' => 'Perf abc' ) ) ),
			'industry_counts'      => $this->time( static fn () => $list->industry_counts() ),
			'list_native_meta'     => $this->time( fn () => $this->native_meta_list() ),
		);
		file_put_contents( sys_get_temp_dir() . '/wiw-list-query-10000.json', wp_json_encode( $figures, JSON_PRETTY_PRINT ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$result = $list->query( 'all', array() );
		$this->assertGreaterThan( 1000, $result['total'], 'the seeded profiles are listed' );
		$this->assertCount( ProfileList::PER_PAGE, $result['profiles'] );

		foreach ( array( 'list_all_state_table', 'list_deep_page', 'list_businesses', 'list_filter_industry', 'list_sorted_updated', 'list_text_search' ) as $key ) {
			$this->assertLessThan( self::BUDGET_LIST, $figures[ $key ], "{$key} within the list budget" );
		}
		$this->assertLessThan( self::BUDGET_INDUSTRIES, $figures['industry_counts'], 'industry counts within budget' );
	}
}
