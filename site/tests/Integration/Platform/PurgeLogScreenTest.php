<?php
/**
 * The Purge log screen lists unpublish events with reason, status and date filters (REQ-209, REQ-244).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Cache\Admin\PurgeLogScreen;
use WhoIsWho\Core\Platform\Cache\FakeClient;
use WhoIsWho\Core\Platform\Cache\MemoryPurgeLog;
use WhoIsWho\Core\Platform\Cache\PurgeService;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\WpTestCase;

final class PurgeLogScreenTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
	}

	private function as_role( string $role ): void {
		wp_set_current_user( 0 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	public function test_REQ_209_purge_log_lists_unpublish_events(): void {
		$queue         = new MemoryQueue();
		$runner        = new JobRunner( $queue, static fn (): int => 1000000 );
		$queue->runner = $runner;
		$log           = new MemoryPurgeLog();
		$service       = new PurgeService( $runner, new FakeClient(), $log );
		$service->register_handler();
		$service->on_unpublished( 'profile', 11, array( 'https://example.test/p/a/' ), 'unpublished' );
		$runner->run_due( 60 );

		$this->as_role( 'administrator' );
		$html = ( new PurgeLogScreen( $log ) )->html( new Request( array() ) );
		$this->assertStringContainsString( 'profile #11', $html );
		$this->assertStringContainsString( 'unpublished', $html );
		$this->assertStringContainsString( '<td>ok</td>', $html );
	}

	public function test_REQ_209_purge_log_filters_by_status_and_date(): void {
		$log = new MemoryPurgeLog();
		$log->add( 'profile', 1, 1, 'ok', 'removed', '2026-11-01 10:00:00' );
		$log->add( 'profile', 2, 1, 'failed', 'declined', '2026-11-05 10:00:00' );
		$log->add( 'profile', 3, 1, 'skipped', 'unpublished', '2026-11-09 10:00:00' );

		$this->as_role( 'administrator' );
		$screen = new PurgeLogScreen( $log );
		$failed = $screen->html( new Request( array( 'status' => 'failed' ) ) );
		$this->assertStringContainsString( 'profile #2', $failed );
		$this->assertStringNotContainsString( 'profile #1', $failed );

		$dated = $screen->html(
			new Request(
				array(
					'from' => '2026-11-04',
					'to'   => '2026-11-06',
				)
			)
		);
		$this->assertStringContainsString( 'profile #2', $dated );
		$this->assertStringNotContainsString( 'profile #3', $dated );
		$this->assertStringNotContainsString( 'profile #1', $dated );

		$bad = $screen->html( new Request( array( 'status' => "ok' OR 1=1" ) ) );
		$this->assertStringContainsString( 'profile #3', $bad, 'an unknown status is ignored' );
	}

	public function test_REQ_244_purge_log_denies_without_capability(): void {
		$this->as_role( 'wiw_reviewer' );
		$this->expectException( \DomainException::class );
		( new PurgeLogScreen( new MemoryPurgeLog() ) )->html( new Request( array() ) );
	}
}
