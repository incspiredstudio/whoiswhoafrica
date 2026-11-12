<?php
/**
 * Daily profile view counts (REQ-236). Needs the WordPress test suite; rows are removed in tearDown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Directory\Views\ViewCounter;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\WpTestCase;

final class ViewCounterTest extends WpTestCase {

	private const PROFILE = 987002;

	protected function setUp(): void {
		parent::setUp();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$this->wipe();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		$this->wipe();
		unset( $_SERVER['HTTP_USER_AGENT'] );
		parent::tearDown();
	}

	private function wipe(): void {
		global $wpdb;
		$wpdb->delete( ViewCounter::table(), array( 'profile_id' => self::PROFILE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function test_REQ_236_view_count_increments_without_storing_ip(): void {
		global $wpdb;
		$counter = new ViewCounter();
		$agent   = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';
		$this->assertTrue( $counter->record( self::PROFILE, $agent, '2026-11-10' ) );
		$this->assertTrue( $counter->record( self::PROFILE, $agent, '2026-11-10' ) );
		$this->assertTrue( $counter->record( self::PROFILE, $agent, '2026-11-11' ) );
		$this->assertSame(
			array(
				'2026-11-11' => 1,
				'2026-11-10' => 2,
			),
			$counter->daily( self::PROFILE )
		);
		$this->assertSame( 3, $counter->total( self::PROFILE ) );

		$columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . ViewCounter::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame( array( 'profile_id', 'view_day', 'views' ), $columns, 'No IP, user agent or identifier column.' );
	}

	public function test_REQ_236_bot_views_are_not_counted(): void {
		$counter = new ViewCounter();
		foreach ( array( 'Googlebot/2.1 (+http://www.google.com/bot.html)', 'Mozilla/5.0 (compatible; bingbot/2.0)', 'curl/8.4.0', 'facebookexternalhit/1.1', '' ) as $agent ) {
			$this->assertFalse( $counter->record( self::PROFILE, $agent ), $agent );
		}
		$this->assertSame( 0, $counter->total( self::PROFILE ) );
	}

	public function test_REQ_236_only_a_served_profile_page_is_counted(): void {
		global $wp_query;
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Firefox/120.0';
		$counter                    = new ViewCounter();

		$wp_query->query_vars = array();
		$counter->count_request();
		$this->assertSame( 0, $counter->total( self::PROFILE ), 'A page that is not a profile is not counted.' );

		$wp_query->set( ProfileRoute::ID_VAR, self::PROFILE );
		$wp_query->is_404 = true;
		$counter->count_request();
		$this->assertSame( 0, $counter->total( self::PROFILE ), 'A 404 or 410 is not counted.' );

		$wp_query->is_404 = false;
		$counter->count_request();
		$this->assertSame( 1, $counter->total( self::PROFILE ), 'A served profile page is counted.' );
		$wp_query->query_vars = array();
	}
}
