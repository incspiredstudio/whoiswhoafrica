<?php
/**
 * Cache purge and rollover for events (REQ-262, REQ-264). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Events;

use WhoIsWho\Core\Content\Events\EventCache;
use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Tests\WpTestCase;

final class EventCacheTest extends WpTestCase {

	/**
	 * URLs purged.
	 *
	 * @var string[]
	 */
	private array $purged = array();

	protected function setUp(): void {
		parent::setUp();
		$this->purged = array();
		delete_option( EventCache::OPTION );
		add_action(
			'wiw_purge_urls',
			function ( $urls ): void {
				$this->purged = array_merge( $this->purged, (array) $urls );
			}
		);
	}

	protected function tearDown(): void {
		remove_all_actions( 'wiw_purge_urls' );
		delete_option( EventCache::OPTION );
		parent::tearDown();
	}

	/**
	 * An event.
	 *
	 * @param string $status Status.
	 * @param string $start  Start (Y-m-d H:i, site time).
	 * @param string $end    End or empty.
	 * @return int Id.
	 */
	private function event( string $status, string $start, string $end = '' ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => EventPostType::TYPE,
				'post_status' => $status,
				'post_title'  => 'Summit',
			)
		);
		update_post_meta( $id, 'wiw_event_start', $start );
		update_post_meta( $id, 'wiw_event_end', $end );
		return $id;
	}

	public function test_REQ_262_unpublish_purges_event_url(): void {
		EventCache::hooks();
		$id           = $this->event( 'publish', '2030-01-01 10:00' );
		$url          = (string) get_permalink( $id );
		$this->purged = array();

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);

		$this->assertContains( $url, $this->purged );
		$this->assertContains( home_url( '/events/' ), $this->purged );
		$this->assertContains( home_url( '/' ), $this->purged );
	}

	public function test_REQ_262_publish_and_trash_purge_the_list_and_home(): void {
		EventCache::hooks();
		$id           = $this->event( 'draft', '2030-01-01 10:00' );
		$this->purged = array();

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$this->assertContains( home_url( '/events/' ), $this->purged );

		$this->purged = array();
		wp_trash_post( $id );
		$this->assertContains( home_url( '/events/' ), $this->purged );
	}

	public function test_REQ_262_draft_save_purges_nothing(): void {
		EventCache::hooks();
		$id           = $this->event( 'draft', '2030-01-01 10:00' );
		$this->purged = array();

		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Renamed',
			)
		);

		$this->assertSame( array(), $this->purged );
	}

	public function test_REQ_262_event_rollover_purges_cache(): void {
		$ended   = $this->event( 'publish', '2026-11-10 09:00', '2026-11-12 17:00' );
		$later   = $this->event( 'publish', '2026-12-01 09:00' );
		$started = $this->event( 'publish', '2026-11-13 09:00' );
		$draft   = $this->event( 'draft', '2026-11-12 09:00' );
		update_option( EventCache::OPTION, '2026-11-12 12:00', false );
		$this->purged = array();

		$done = EventCache::rollover( '2026-11-13 12:00' );

		$this->assertEqualsCanonicalizing( array( $ended, $started ), $done );
		$this->assertContains( (string) get_permalink( $ended ), $this->purged );
		$this->assertContains( home_url( '/events/' ), $this->purged );
		$this->assertNotContains( (string) get_permalink( $later ), $this->purged );
		$this->assertNotContains( (string) get_permalink( $draft ), $this->purged );
		$this->assertSame( '2026-11-13 12:00', get_option( EventCache::OPTION ) );
	}

	public function test_REQ_262_rollover_with_nothing_due_purges_nothing(): void {
		$this->event( 'publish', '2030-01-01 10:00' );
		update_option( EventCache::OPTION, '2026-11-12 12:00', false );
		$this->purged = array();

		$this->assertSame( array(), EventCache::rollover( '2026-11-13 12:00' ) );
		$this->assertSame( array(), $this->purged );
	}

	public function test_REQ_264_first_rollover_run_only_records_the_time(): void {
		$this->event( 'publish', '2026-01-01 10:00' );

		$this->assertSame( array(), EventCache::rollover( '2026-11-13 12:00' ) );
		$this->assertSame( '2026-11-13 12:00', get_option( EventCache::OPTION ) );
	}
}
