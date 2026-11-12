<?php
/**
 * The cache purge service and the cache header helper (REQ-209, REQ-121).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Cache\CacheHeaders;
use WhoIsWho\Core\Platform\Cache\FakeClient;
use WhoIsWho\Core\Platform\Cache\MemoryPurgeLog;
use WhoIsWho\Core\Platform\Cache\NullClient;
use WhoIsWho\Core\Platform\Cache\PurgeService;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class PurgeServiceTest extends TestCase {

	private MemoryQueue $queue;
	private JobRunner $runner;
	private MemoryPurgeLog $log;

	protected function setUp(): void {
		parent::setUp();
		$this->queue         = new MemoryQueue();
		$options             = new \ArrayObject();
		$this->runner        = new JobRunner(
			$this->queue,
			static fn (): int => 1000000,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$this->queue->runner = $this->runner;
		$this->log           = new MemoryPurgeLog();
	}

	public function test_REQ_209_unpublish_event_queues_high_priority_purge_after_commit(): void {
		$service = new PurgeService( $this->runner, new FakeClient(), $this->log );
		$service->register_handler();
		$service->on_unpublished( 'profile', 42, array( 'https://example.test/p/a/' ) );

		$this->assertCount( 1, $this->queue->jobs );
		$job = reset( $this->queue->jobs );
		$this->assertSame( PurgeService::JOB, $job['name'] );
		$this->assertTrue( $job['high'], 'the purge runs in the high-priority group' );
		$this->assertSame( array(), $this->log->rows(), 'nothing is purged until the queued job runs, which is after the commit' );

		$this->runner->run_due( 60 );
		$this->assertCount( 1, $this->log->rows() );
	}

	public function test_REQ_209_fake_client_receives_expected_urls(): void {
		$client  = new FakeClient();
		$service = new PurgeService( $this->runner, $client, $this->log );
		$service->register_handler();
		$urls = array( 'https://example.test/p/a/', 'https://example.test/lists/', 'https://example.test/sitemap.xml' );
		$service->on_unpublished( 'profile', 42, array_merge( $urls, array( 'https://example.test/p/a/', '' ) ) );
		$this->runner->run_due( 60 );

		$this->assertSame( array( $urls ), $client->calls, 'exactly the URLs passed, once each' );
		$row = $this->log->rows()[0];
		$this->assertSame( 'profile', $row['subject_type'] );
		$this->assertSame( 42, $row['subject_id'] );
		$this->assertSame( 3, $row['url_count'] );
		$this->assertSame( 'ok', $row['result'] );
	}

	public function test_REQ_209_null_client_still_logs_purge(): void {
		$service = new PurgeService( $this->runner, new NullClient(), $this->log );
		$service->register_handler();
		$service->on_unpublished( 'profile', 7, array( 'https://example.test/p/b/' ) );
		$this->runner->run_due( 60 );

		$rows = $this->log->rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'skipped', $rows[0]['result'] );
		$this->assertSame( 1, $rows[0]['url_count'] );
	}

	public function test_REQ_209_a_failing_client_is_logged_and_retried(): void {
		$client          = new FakeClient();
		$client->failing = true;
		$service         = new PurgeService( $this->runner, $client, $this->log );
		$service->register_handler();
		$service->on_unpublished( 'profile', 7, array( 'https://example.test/p/b/' ) );
		$this->runner->run_due( 60 );

		$this->assertSame( 'failed', $this->log->rows()[0]['result'] );
		$this->assertCount( 1, $this->queue->jobs, 'a retry waits in the queue' );
	}

	public function test_REQ_121_staff_responses_are_not_publicly_cacheable(): void {
		$public = CacheHeaders::for_response( array( 'method' => 'GET' ) );
		$this->assertSame( 'public, max-age=120, s-maxage=120', $public['Cache-Control'] );

		$list = CacheHeaders::for_response(
			array(
				'method' => 'GET',
				'list'   => true,
			)
		);
		$this->assertSame( 'public, max-age=60, s-maxage=60', $list['Cache-Control'] );

		$no_cache = 'no-store, no-cache, must-revalidate, max-age=0, s-maxage=0, private';
		foreach (
			array(
				array(
					'method' => 'GET',
					'staff'  => true,
				),
				array(
					'method' => 'GET',
					'public' => false,
				),
				array( 'method' => 'POST' ),
				array(
					'method' => 'GET',
					'status' => 404,
				),
			) as $case
		) {
			$this->assertSame( $no_cache, CacheHeaders::for_response( $case )['Cache-Control'], (string) json_encode( $case ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		}
		$this->assertLessThanOrEqual( 120, CacheHeaders::MAX_AGE );
	}
}
