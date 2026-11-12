<?php
/**
 * The search query service (REQ-008, REQ-254, REQ-183, REQ-208, REQ-218). Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Search;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Search\StoryIndexer;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryFlags;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Search\SearchIndexer;
use WhoIsWho\Core\Directory\Search\SearchService;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StatePolicy;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class SearchServiceTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		StoryTypes::register();
		StoryTypes::ensure_terms();
		StoryFields::register_meta();
		EventPostType::register();
		( new StoryIndexer() )->register();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( SearchIndexer::table(), array( 'item_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		parent::tearDown();
	}

	/**
	 * A fictional organisation in a state.
	 *
	 * @param string $name  Name.
	 * @param string $state Target state.
	 * @param string $bio   Summary text.
	 */
	private function profile( string $name, string $state, string $bio = 'A fictional organisation.' ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$start         = in_array( $state, array( PubState::IMPORTED_NOT_PUBLIC, PubState::REJECTED ), true ) ? PubState::IMPORTED_NOT_PUBLIC : PubState::PUBLISHED_PENDING;
		$machine->enter( $id, $start, array( 'entity_type' => 'organisation' ) );
		( new ProfileFields() )->set( $id, 'summary', $bio );
		( new ProvenanceRepository() )->record(
			$id,
			array(
				'field'        => 'summary',
				'source_url'   => 'https://example.org/src',
				'retrieved_at' => '2026-11-10T09:00:00Z',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		} elseif ( PubState::REJECTED === $state ) {
			$machine->transition( $id, PubState::REJECTED, 'test' );
		} elseif ( PubState::UNPUBLISHED === $state ) {
			$machine->transition( $id, PubState::UNPUBLISHED, 'test' );
		} elseif ( PubState::REMOVED_SUPPRESSED === $state ) {
			$machine->transition( $id, PubState::REMOVED_SUPPRESSED, 'test' );
		}
		return $id;
	}

	/**
	 * A published story.
	 *
	 * @param string $title Title.
	 * @param string $body  Body.
	 * @param string $date  Publish date (Y-m-d H:i:s).
	 */
	private function story( string $title, string $body = 'Text.', string $date = '' ): int {
		$args = array(
			'post_title'   => $title,
			'post_content' => $body,
			'post_status'  => 'publish',
		);
		if ( '' !== $date ) {
			$args['post_date'] = $date;
		}
		$id            = (int) wp_insert_post( $args );
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * Names of the profiles in a reply.
	 *
	 * @param array<string, mixed> $reply Reply.
	 * @return string[]
	 */
	private function profile_names( array $reply ): array {
		return array_column( $reply['profiles'], 'name' );
	}

	public function test_REQ_254_search_returns_only_public_items(): void {
		global $wpdb;
		$ids = array();
		foreach ( PubState::all() as $state ) {
			$ids[ $state ] = $this->profile( 'Zephyrine ' . str_replace( '_', ' ', $state ), $state );
		}
		// A stale row for an unpublished profile, as if a purge had failed: the read model still keeps it out.
		$wpdb->query( $wpdb->prepare( 'REPLACE INTO ' . SearchIndexer::table() . ' (item_type, item_id, entity_type, name, organisation, title, industry, bio, url, pending, name_sort) VALUES (%s, %d, %s, %s, %s, %s, %s, %s, %s, %d, %s)', 'profile', $ids[ PubState::UNPUBLISHED ], 'organisation', 'Zephyrine stale', '', '', '', '', '', 0, 'zephyrine stale' ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$draft         = (int) wp_insert_post(
			array(
				'post_title'  => 'Zephyrine draft story',
				'post_status' => 'draft',
			)
		);
		$this->posts[] = $draft;

		$reply = ( new SearchService() )->search( 'Zephyrine' );
		$this->assertSame( SearchService::STATE_RESULTS, $reply['state'] );
		$got = array_column( $reply['profiles'], 'id' );
		sort( $got );
		$expected = array( $ids[ PubState::PUBLISHED_REVIEWED ], $ids[ PubState::PUBLISHED_PENDING ] );
		sort( $expected );
		$this->assertSame( $expected, $got );
		$this->assertSame( array(), $reply['stories'], 'a draft story is not found' );
	}

	public function test_REQ_008_name_match_ranks_first(): void {
		$this->profile( 'The Obuya Foundation', PubState::PUBLISHED_REVIEWED, 'Obuya Obuya Obuya Obuya works with Obuya.' );
		$this->profile( 'Obuya Holdings Group', PubState::PUBLISHED_REVIEWED );
		$this->profile( 'Obuya Holdings', PubState::PUBLISHED_REVIEWED );
		$reply = ( new SearchService() )->search( 'Obuya Holdings' );
		$this->assertSame( 'Obuya Holdings', $reply['profiles'][0]['name'], 'the exact name comes first' );
		$this->assertSame( 'Obuya Holdings Group', $reply['profiles'][1]['name'], 'then a name that starts with the query' );

		$by_prefix = ( new SearchService() )->search( 'obuya' );
		$this->assertSame( array( 'Obuya Holdings', 'Obuya Holdings Group' ), array_slice( $this->profile_names( $by_prefix ), 0, 2 ), 'prefix matches before a name that only contains the word, then by name' );
		$this->assertContains( 'The Obuya Foundation', $this->profile_names( $by_prefix ) );
	}

	public function test_REQ_254_result_count_equals_profiles_plus_stories(): void {
		$this->profile( 'Quillon Works', PubState::PUBLISHED_REVIEWED );
		$this->profile( 'Quillon Trust', PubState::PUBLISHED_PENDING );
		$this->story( 'Quillon opens a new yard' );
		$reply = ( new SearchService() )->search( 'Quillon' );
		$this->assertCount( 2, $reply['profiles'] );
		$this->assertCount( 1, $reply['stories'] );
		$this->assertSame( 3, $reply['count'] );
		$this->assertSame( count( $reply['profiles'] ) + count( $reply['stories'] ), $reply['count'] );
	}

	public function test_REQ_218_pending_profile_is_found_with_label_flag(): void {
		$this->profile( 'Pendulum Quarry', PubState::PUBLISHED_PENDING );
		$this->profile( 'Pendulum Reviewed', PubState::PUBLISHED_REVIEWED );
		$reply = ( new SearchService() )->search( 'Pendulum' );
		$by    = array_column( $reply['profiles'], null, 'name' );
		$this->assertTrue( $by['Pendulum Quarry']['pending'] );
		$this->assertSame( StatePolicy::PENDING_LABEL, $by['Pendulum Quarry']['label'] );
		$this->assertFalse( $by['Pendulum Reviewed']['pending'] );
		$this->assertSame( '', $by['Pendulum Reviewed']['label'] );
	}

	public function test_REQ_183_paid_arrangement_does_not_change_result_order(): void {
		$plain = $this->story( 'Harbourlight plain report', 'Harbourlight.', '2026-11-01 10:00:00' );
		$paid  = $this->story( 'Harbourlight paid report', 'Harbourlight.', '2026-10-01 10:00:00' );
		foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $filter ) {
			remove_filter( $filter, array( StoryFlags::class, 'guard_meta' ), 1 );
		}
		$before = array_column( ( new SearchService() )->search( 'Harbourlight' )['stories'], 'id' );
		update_post_meta( $paid, 'wiw_sponsor_name', 'Acme Freight' );
		update_post_meta( $paid, 'wiw_sponsor_placement', 'both' );
		( new StoryIndexer() )->upsert( $paid );
		$reply = ( new SearchService() )->search( 'Harbourlight' );
		$this->assertSame( $before, array_column( $reply['stories'], 'id' ), 'the sponsored story keeps its place' );
		$this->assertSame( array( $plain, $paid ), $before, 'newest first when relevance is the same' );

		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Directory/Search/SearchService.php' );
		$this->assertStringNotContainsString( 'Core\Commerce', $source, 'search never uses Commerce' );
		preg_match_all( '/ORDER BY[^;]*/', $source, $orders );
		foreach ( $orders[0] as $clause ) {
			$this->assertDoesNotMatchRegularExpression( '/sponsor|pending|label|customer|paid/i', $clause, 'ordering reads only name, relevance and date' );
		}
	}

	public function test_REQ_182_a_sponsored_story_carries_its_flag_and_sponsor_name(): void {
		$paid = $this->story( 'Wharfside sponsored feature', 'Wharfside.' );
		$this->story( 'Wharfside plain feature', 'Wharfside.' );
		foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $filter ) {
			remove_filter( $filter, array( StoryFlags::class, 'guard_meta' ), 1 );
		}
		update_post_meta( $paid, 'wiw_sponsor_name', 'Acme Freight' );
		update_post_meta( $paid, 'wiw_sponsor_placement', 'both' );
		( new StoryIndexer() )->upsert( $paid );
		$by = array_column( ( new SearchService() )->search( 'Wharfside' )['stories'], null, 'id' );
		$this->assertTrue( $by[ $paid ]['sponsored'] );
		$this->assertSame( 'Acme Freight', $by[ $paid ]['sponsor_name'] );
		$others = array_filter( $by, static fn ( array $s ): bool => $s['id'] !== $paid );
		$this->assertCount( 1, $others );
		$this->assertFalse( array_values( $others )[0]['sponsored'] );
	}

	public function test_REQ_208_core_search_does_not_return_profiles(): void {
		$id    = $this->profile( 'Coresearch Probe Org', PubState::PUBLISHED_REVIEWED );
		$query = new \WP_Query(
			array(
				's'         => 'Coresearch',
				'post_type' => 'any',
			)
		);
		$this->assertNotContains( $id, wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertContains( $id, array_column( ( new SearchService() )->search( 'Coresearch' )['profiles'], 'id' ), 'this service is the one way to find it' );
	}

	public function test_REQ_254_empty_none_and_rate_limited_are_distinct_states(): void {
		$service = new SearchService();
		$this->assertSame( SearchService::STATE_EMPTY, $service->search( '   ' )['state'] );
		$this->assertSame( SearchService::STATE_EMPTY, $service->search( '<b></b>' )['state'] );
		$none = $service->search( 'Nothingmatchesthis' );
		$this->assertSame( SearchService::STATE_NONE, $none['state'] );
		$this->assertSame( 0, $none['count'] );

		$limited = new SearchService(
			new RateLimiter(
				new MemoryStore(),
				'k',
				array(
					'search'  => array(
						'ip'      => array( 2, 600 ),
						'subject' => array( 2, 600 ),
					),
					'default' => RateLimiter::LIMITS['default'],
				)
			)
		);
		$this->assertSame( SearchService::STATE_NONE, $limited->search( 'Nothingmatchesthis', '198.51.100.9' )['state'] );
		$this->assertSame( SearchService::STATE_NONE, $limited->search( 'Nothingmatchesthis', '198.51.100.9' )['state'] );
		$this->assertSame( SearchService::STATE_RATE_LIMITED, $limited->search( 'Nothingmatchesthis', '198.51.100.9' )['state'] );
		$this->assertSame( SearchService::STATE_NONE, $limited->search( 'Nothingmatchesthis', '198.51.100.10' )['state'], 'another visitor is not limited' );
	}

	public function test_REQ_254_the_query_is_length_limited_and_safe_and_both_surfaces_get_the_same_answer(): void {
		$this->profile( 'Lengthprobe Org', PubState::PUBLISHED_REVIEWED );
		$service = new SearchService();
		$long    = $service->search( str_repeat( 'a', 500 ) );
		$this->assertSame( SearchService::MAX_LENGTH, strlen( $long['query'] ) );
		$hostile = $service->search( "x' OR 1=1 -- %" );
		$this->assertSame( SearchService::STATE_NONE, $hostile['state'] );
		$this->assertSame( $service->search( 'Lengthprobe' ), ( new SearchService() )->search( 'Lengthprobe' ), 'the header panel and the page call the same service' );
	}
}
