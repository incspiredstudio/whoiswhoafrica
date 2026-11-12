<?php
/**
 * Cache purges queued on state changes (REQ-209, REQ-217). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Purge\PurgeQueue;
use WhoIsWho\Core\Directory\Purge\UrlSet;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Log\LogSink;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class PurgeQueueTest extends TestCase {

	/**
	 * Created posts.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	private MemoryQueue $queue;

	/**
	 * Log lines.
	 *
	 * @var string[]
	 */
	private array $lines = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		$this->queue         = new MemoryQueue();
		$store               = new \ArrayObject();
		$runner              = new JobRunner(
			$this->queue,
			null,
			array(
				'get' => static fn ( string $key, $fallback ) => $store[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $store ): void {
					$store[ $key ] = $value;
				},
			)
		);
		$this->queue->runner = $runner;
		$lines               = &$this->lines;
		$sink                = new class( $lines ) implements LogSink {
			/** @var string[] */
			private array $lines;

			/** @param string[] $lines Lines. */
			public function __construct( array &$lines ) {
				$this->lines = &$lines;
			}

			/** @param string $line Line. */
			public function write( string $line ): void {
				$this->lines[] = $line;
			}
		};
		$this->purge         = new PurgeQueue( $runner, new Logger( $sink, new PiiCatalogue() ) );
		$this->purge->register();
	}

	private PurgeQueue $purge;

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		remove_all_actions( 'wiw_profile_state_changed' );
		remove_all_actions( 'wiw_purge_urls' );
		remove_all_filters( 'wiw_purge_threshold' );
		parent::tearDown();
	}

	private function profile( string $state = PubState::PUBLISHED_REVIEWED ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => 'Purge test ' . count( $this->posts ),
				'post_status' => 'draft',
			)
		);
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::PUBLISHED_PENDING === $state ? $state : PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'organisation' ) );
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			( new StateMachine() )->transition( $id, PubState::PUBLISHED_REVIEWED, '', 5 );
		}
		$this->queue->jobs = array();
		$this->lines       = array();
		return $id;
	}

	/**
	 * Purge jobs waiting.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function purge_jobs(): array {
		return array_values( array_filter( $this->queue->jobs, static fn ( array $j ): bool => PurgeQueue::JOB === $j['name'] ) );
	}

	public function test_REQ_209_removal_queues_purge_job(): void {
		$id = $this->profile();
		( new StateMachine() )->transition( $id, PubState::REMOVED_SUPPRESSED, 'Owner request' );
		$jobs = $this->purge_jobs();
		$this->assertCount( 1, $jobs );
		$this->assertSame( $id, $jobs[0]['args']['profile_id'] );
		$this->assertTrue( $jobs[0]['high'] );
		$this->assertStringContainsString( 'unpublish_event', implode( "\n", $this->lines ), 'An unpublish event is logged.' );
	}

	public function test_REQ_217_approval_queues_purge_in_same_step(): void {
		$id = $this->profile( PubState::PUBLISHED_PENDING );
		( new StateMachine() )->transition( $id, PubState::PUBLISHED_REVIEWED, '', 5 );
		$this->assertCount( 1, $this->purge_jobs() );
	}

	public function test_REQ_209_non_public_changes_queue_nothing(): void {
		$id = $this->profile( PubState::IMPORTED_NOT_PUBLIC );
		( new StateMachine() )->transition( $id, PubState::REJECTED, 'Not suitable' );
		$this->assertSame( array(), $this->purge_jobs() );
	}

	public function test_REQ_209_bulk_above_threshold_queues_broad_purge(): void {
		add_filter( 'wiw_purge_threshold', static fn (): int => 2 );
		$ids = array( $this->profile(), $this->profile(), $this->profile() );
		$this->purge->begin_batch();
		foreach ( $ids as $id ) {
			( new StateMachine() )->transition( $id, PubState::UNPUBLISHED, 'Bulk clean-up', null, 'batch-1' );
		}
		$this->assertSame( array(), $this->purge_jobs(), 'Nothing is queued while the batch runs.' );
		$this->assertSame( 1, $this->purge->end_batch() );
		$jobs = $this->purge_jobs();
		$this->assertCount( 1, $jobs );
		$this->assertTrue( $jobs[0]['args']['broad'] );
	}

	public function test_REQ_209_bulk_below_threshold_queues_one_job_per_profile(): void {
		$ids = array( $this->profile(), $this->profile() );
		$this->purge->begin_batch();
		foreach ( $ids as $id ) {
			( new StateMachine() )->transition( $id, PubState::UNPUBLISHED, 'Bulk clean-up', null, 'batch-2' );
		}
		$this->assertSame( 2, $this->purge->end_batch() );
		$this->assertCount( 2, $this->purge_jobs() );
	}

	public function test_REQ_209_url_set_has_page_sitemap_and_lists_and_job_announces_it(): void {
		$id   = $this->profile();
		$urls = ( new UrlSet() )->for_profile( $id );
		$slug = get_post_field( 'post_name', $id );
		$this->assertContains( home_url( '/profile/' . $slug . '/' ), $urls );
		$this->assertContains( home_url( '/sitemap.xml' ), $urls );
		$this->assertContains( home_url( '/directory/' ), $urls );

		$seen = array();
		add_action(
			'wiw_purge_urls',
			static function ( array $u, bool $broad ) use ( &$seen ): void {
				$seen = array( $u, $broad );
			},
			10,
			2
		);
		$this->purge->run( array( 'profile_id' => $id ) );
		$this->assertSame( $urls, $seen[0] );
		$this->assertFalse( $seen[1] );
	}
}
