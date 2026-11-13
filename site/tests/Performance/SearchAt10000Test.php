<?php
/**
 * Search at 10,000 public profiles (REQ-079, REQ-254; M2-SRC-04). Seeds 10,000 reviewed or pending profiles and 400 stories, all
 * fictional and synthetic (no personal data), puts them in the search index table, then runs SearchService::search for a spread of
 * queries (a full name, a prefix, a rare word, a common word, an industry, a story word, no match) several times each. The p95 and
 * the median are written to a JSON file in the system temp folder and the p95 must stay under the target below. The measured result
 * is recorded in tests/Performance/README.md. Plain TestCase: it needs the real tables, and removes its rows afterwards.
 *
 * Target: p95 under 300 ms on the CI database (proposal in issue #317, to be confirmed by the human owner).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Performance;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Search\SearchIndexer;
use WhoIsWho\Core\Directory\Search\SearchService;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Forms\TransientStore;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class SearchAt10000Test extends TestCase {

	private const PROFILES = 10000;
	private const STORIES  = 400;
	private const BATCH    = 1000;
	private const RUNS     = 5;

	/**
	 * The agreed target for the 95th percentile, in seconds.
	 */
	private const TARGET_P95 = 0.3;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
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
		$wpdb->query( 'DELETE i FROM ' . SearchIndexer::table() . " i INNER JOIN {$posts} p ON p.ID = i.item_id WHERE p.post_title LIKE 'SPerf %'" );
		$wpdb->query( 'DELETE s FROM ' . StateRepository::table() . " s INNER JOIN {$posts} p ON p.ID = s.profile_id WHERE p.post_type = '" . ProfileType::POST_TYPE . "' AND p.post_title LIKE 'SPerf %'" );
		$wpdb->query( "DELETE FROM {$posts} WHERE post_title LIKE 'SPerf %'" );
		// phpcs:enable
	}

	/**
	 * Insert the profiles, their state rows, the stories and the index rows in batches.
	 */
	private function seed(): void {
		global $wpdb;
		$first = (int) $wpdb->get_var( "SELECT COALESCE(MAX(ID),0) FROM {$wpdb->posts}" ) + 1; // phpcs:ignore WordPress.DB
		$now   = gmdate( 'Y-m-d H:i:s' );
		$total = self::PROFILES + self::STORIES;
		// Rows left behind by other tests can sit on the ids this run will use; clear them first.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . SearchIndexer::table() . ' WHERE item_id >= %d', $first ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . StateRepository::table() . ' WHERE profile_id >= %d', $first ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$words    = array( 'logistics', 'bank', 'energy', 'media', 'health', 'farm', 'studio', 'works', 'capital', 'labs' );
		$industry = array( 'Finance', 'Technology', 'Energy', 'Health', 'Agriculture', 'Media' );
		$types    = StateRepository::ENTITY_TYPES;
		$post_sql = "INSERT INTO {$wpdb->posts} (ID,post_author,post_date,post_date_gmt,post_content,post_title,post_excerpt,post_status,comment_status,ping_status,post_password,post_name,to_ping,pinged,post_modified,post_modified_gmt,post_content_filtered,post_parent,menu_order,post_type,post_mime_type,comment_count) VALUES ";

		for ( $start = 0; $start < $total; $start += self::BATCH ) {
			$posts_rows = array();
			$state_rows = array();
			$index_rows = array();
			$end        = min( $total, $start + self::BATCH );
			for ( $n = $start; $n < $end; $n++ ) {
				$id       = $first + $n;
				$is_story = $n >= self::PROFILES;
				$word     = $words[ $n % count( $words ) ];
				$ind      = $industry[ $n % count( $industry ) ];
				$title    = 'SPerf ' . ucfirst( $word ) . ' ' . substr( md5( (string) $n ), 0, 6 ) . ' ' . $n;
				$bio      = 'Fictional ' . $word . ' record number ' . $n . ' in ' . $ind . ', written for the search timing test.';
				$type     = $is_story ? 'post' : ProfileType::POST_TYPE;

				$posts_rows[] = $wpdb->prepare( '(%d,1,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,0,0,%s,%s,0)', $id, $now, $now, $bio, $title, '', 'publish', 'closed', 'closed', '', 'sperf-' . $n, '', '', $now, $now, '', $type, '' );
				if ( ! $is_story ) {
					$state_rows[] = $wpdb->prepare( '(%d,%s,%s,%s,%s)', $id, 0 === $n % 5 ? PubState::PUBLISHED_PENDING : PubState::PUBLISHED_REVIEWED, $types[ $n % count( $types ) ], 'import', $now );
				}
				$index_rows[] = $wpdb->prepare(
					'(%s,%d,%s,%s,%s,%s,%s,%s,%s,%d,%s,0)',
					$is_story ? 'story' : SearchIndexer::ITEM_TYPE,
					$id,
					$is_story ? 'news' : $types[ $n % count( $types ) ],
					$title,
					$is_story ? '' : ucfirst( $word ) . ' Holdings',
					'',
					$ind,
					$bio,
					'https://example.invalid/sperf-' . $n . '/',
					0,
					strtolower( $title )
				);
			}
			// phpcs:disable WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixture rows built with prepare() above; fixed table names.
			$wpdb->query( $post_sql . implode( ',', $posts_rows ) );
			if ( array() !== $state_rows ) {
				$wpdb->query( 'INSERT INTO ' . StateRepository::table() . ' (profile_id,pub_state,entity_type,origin,updated_at) VALUES ' . implode( ',', $state_rows ) );
			}
			$wpdb->query( 'INSERT INTO ' . SearchIndexer::table() . ' (item_type,item_id,entity_type,name,organisation,title,industry,bio,url,pending,name_sort,sponsored) VALUES ' . implode( ',', $index_rows ) );
			// phpcs:enable
		}
	}

	/**
	 * Percentile of a sorted list (nearest rank).
	 *
	 * @param float[] $sorted Sorted ascending.
	 * @param float   $p      0 to 1.
	 */
	private static function percentile( array $sorted, float $p ): float {
		$rank = (int) max( 0, ceil( $p * count( $sorted ) ) - 1 );
		return $sorted[ $rank ];
	}

	public function test_REQ_079_search_on_10000_profiles_returns_within_target(): void {
		$this->seed();
		global $wpdb;
		$rows = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SearchIndexer::table() . " WHERE item_type = 'profile' AND name LIKE 'SPerf %'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		$this->assertSame( self::PROFILES, $rows, 'the 10,000 profiles are in the search index' );

		// No rate limit: the test measures the query, not the throttle.
		$service = new SearchService( new RateLimiter( new TransientStore(), 'perf-key', array( SearchService::FORM => array( 'ip' => array( 1000000, 600 ) ) ) + RateLimiter::LIMITS ) );
		$queries = array( 'SPerf Logistics', 'sperf bank', 'Fictional energy record', 'Holdings', 'Finance', 'record number 4242', 'story word media', 'no-such-thing-zzzqx', 'Capital', 'Studio Technology' );

		$service->search( 'warm up' ); // the first query pays for loading the index into memory.
		$times   = array();
		$by      = array();
		$results = array();
		foreach ( $queries as $query ) {
			for ( $i = 0; $i < self::RUNS; $i++ ) {
				$start             = microtime( true );
				$reply             = $service->search( $query, '127.0.0.1' );
				$elapsed           = microtime( true ) - $start;
				$times[]           = $elapsed;
				$by[ $query ][]    = $elapsed;
				$results[ $query ] = $reply['count'];
			}
		}
		sort( $times );
		$p95    = self::percentile( $times, 0.95 );
		$figure = array(
			'profiles'      => self::PROFILES,
			'stories'       => self::STORIES,
			'samples'       => count( $times ),
			'target_p95_s'  => self::TARGET_P95,
			'p50_s'         => round( self::percentile( $times, 0.5 ), 4 ),
			'p95_s'         => round( $p95, 4 ),
			'max_s'         => round( max( $times ), 4 ),
			'per_query_max' => array_map( static fn ( array $t ): float => round( max( $t ), 4 ), $by ),
			'result_counts' => $results,
		);
		file_put_contents( sys_get_temp_dir() . '/wiw-search-10000.json', wp_json_encode( $figure, JSON_PRETTY_PRINT ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_p

		$this->assertGreaterThan( 0, $results['SPerf Logistics'], 'a name search finds profiles' );
		$this->assertSame( 0, $results['no-such-thing-zzzqx'], 'a query with no match returns nothing' );
		$this->assertLessThan( self::TARGET_P95, $p95, 'p95 of search on 10,000 profiles within the target' );
	}
}
