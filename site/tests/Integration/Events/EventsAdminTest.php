<?php
/**
 * The Events admin screen: capability, audit, purge and the list columns (REQ-262, REQ-244, REQ-051). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Events;

use WhoIsWho\Core\Content\Events\Admin\EventLifecycle;
use WhoIsWho\Core\Content\Events\Admin\EventListScreen;
use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class EventsAdminTest extends TestCase {

	/**
	 * Posts created.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Audit floor.
	 *
	 * @var int
	 */
	private int $audit_floor = 0;

	/**
	 * Purged URL lists seen.
	 *
	 * @var array<int, string[]>
	 */
	private array $purged = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		EventPostType::register();
		wp_set_current_user( 0 );
		global $wpdb;
		$this->audit_floor = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wiw_audit_log" ); // phpcs:ignore WordPress.DB
		$this->purged      = array();
		add_action(
			'wiw_purge_urls',
			function ( array $urls ): void {
				$this->purged[] = $urls;
			}
		);
	}

	protected function tearDown(): void {
		remove_all_actions( 'wiw_purge_urls' );
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		$this->posts = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'ev_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	private function event( string $status = 'draft' ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => EventPostType::TYPE,
				'post_title'  => 'Admin test summit',
				'post_status' => $status,
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * Audit actions written for an event since the test began.
	 *
	 * @param int $id Event.
	 * @return string[]
	 */
	private function actions( int $id ): array {
		global $wpdb;
		return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT action FROM {$wpdb->prefix}wiw_audit_log WHERE subject_type = 'event' AND subject_id = %s AND id > %d ORDER BY id ASC", (string) $id, $this->audit_floor ) ) ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_244_events_screen_returns_403_without_capability(): void {
		$id = $this->event();
		wp_set_current_user( $this->user( 'wiw_reviewer' ) );
		$type = get_post_type_object( EventPostType::TYPE );
		$this->assertNotNull( $type );
		$this->assertFalse( current_user_can( $type->cap->edit_posts ), 'the list screen needs the type capability, so WordPress answers 403' ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- from the type.
		$this->assertFalse( current_user_can( 'edit_post', $id ) );
		$this->assertFalse( current_user_can( 'publish_posts' ) );
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$this->assertTrue( current_user_can( $type->cap->edit_posts ) ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- from the type.
	}

	public function test_REQ_262_event_changes_are_audited(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$id = $this->event( 'draft' );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);
		wp_trash_post( $id );
		$this->assertSame( array( 'event.create', 'event.publish', 'event.unpublish', 'event.trash' ), $this->actions( $id ) );
	}

	public function test_REQ_262_scheduling_an_event_is_audited(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$id            = (int) wp_insert_post(
			array(
				'post_type'     => EventPostType::TYPE,
				'post_title'    => 'Scheduled summit',
				'post_status'   => 'future',
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() + 86400 * 30 ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + 86400 * 30 ),
			)
		);
		$this->posts[] = $id;
		$this->assertSame( array( 'event.create', 'event.schedule' ), $this->actions( $id ) );
	}

	public function test_REQ_262_saving_event_purges_list_and_home_section(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$id           = $this->event( 'publish' );
		$this->purged = array();
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Renamed summit',
			)
		);
		$this->assertNotEmpty( $this->purged, 'a saved published event announces a purge' );
		$urls = $this->purged[0];
		$this->assertContains( home_url( '/events/' ), $urls );
		$this->assertContains( home_url( '/' ), $urls );
		$this->assertContains( (string) get_permalink( $id ), $urls );
	}

	public function test_REQ_262_saving_a_draft_does_not_purge(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$id           = $this->event( 'draft' );
		$this->purged = array();
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Still a draft',
			)
		);
		$this->assertSame( array(), $this->purged );
	}

	public function test_REQ_262_unpublishing_purges_and_the_event_is_no_longer_public(): void {
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$id           = $this->event( 'publish' );
		$this->purged = array();
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);
		$this->assertNotEmpty( $this->purged );
		$this->assertSame( 'draft', get_post_status( $id ) );
	}

	public function test_REQ_262_audit_action_names(): void {
		$this->assertSame( 'event.publish', EventLifecycle::action_for( 'publish', 'draft' ) );
		$this->assertSame( 'event.unpublish', EventLifecycle::action_for( 'draft', 'publish' ) );
		$this->assertSame( 'event.trash', EventLifecycle::action_for( 'trash', 'draft' ) );
		$this->assertNull( EventLifecycle::action_for( 'draft', 'draft' ) );
		$this->assertNull( EventLifecycle::action_for( 'draft', 'pending' ) );
	}

	public function test_REQ_262_list_labels_status_and_upcoming_or_past(): void {
		$this->assertSame( 'Draft', EventListScreen::status_label( 'draft' ) );
		$this->assertSame( 'Scheduled', EventListScreen::status_label( 'future' ) );
		$this->assertSame( 'Published', EventListScreen::status_label( 'publish' ) );
		$now = (int) strtotime( '2027-03-04 12:00:00 UTC' );
		update_option( 'timezone_string', 'UTC' );
		$this->assertSame( 'Upcoming', EventListScreen::timing( '2027-03-04 10:00', '2027-03-04 17:00', $now ), 'upcoming until its end' );
		$this->assertSame( 'Past', EventListScreen::timing( '2027-03-04 10:00', '', $now ), 'no end: past after its start' );
		$this->assertSame( 'Upcoming', EventListScreen::timing( '2027-04-01 10:00', '', $now ) );
		$columns = EventListScreen::columns( array( 'cb' => '<input type="checkbox" />' ) );
		$this->assertArrayHasKey( 'wiw_city', $columns );
		$this->assertArrayHasKey( 'wiw_status', $columns );
	}
}
