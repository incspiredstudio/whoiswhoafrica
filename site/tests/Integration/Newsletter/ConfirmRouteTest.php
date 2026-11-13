<?php
/**
 * The newsletter confirmation link (REQ-249, REQ-061). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Newsletter;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Content\Newsletter\ConfirmRoute;
use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\TestCase;

final class ConfirmRouteTest extends TestCase {

	/**
	 * Time the repository sees.
	 *
	 * @var string
	 */
	private string $now = '2026-11-12 10:00:00';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		\WhoIsWho\Core\Platform\Migrations\Schema::create_table( ConsentRecorder::TABLE, ConsentRecorder::TABLE_DEFINITION );
		$wpdb->query( 'DELETE FROM ' . SubscriberRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
	}

	private function repo(): SubscriberRepository {
		return new SubscriberRepository( fn (): string => $this->now, new SettingsService( SettingsService::default_registry() ) );
	}

	public function test_REQ_249_confirm_link_is_single_use(): void {
		$repo  = $this->repo();
		$token = (string) $repo->add_pending( 'ada@example.test' )['token'];
		$route = new ConfirmRoute( $repo );
		$this->assertFalse( $repo->is_subscribed( 'ada@example.test' ) );
		$first = $route->respond( $token );
		$this->assertSame( ConfirmRoute::CONFIRMED, $first['state'] );
		$this->assertTrue( $repo->is_subscribed( 'ada@example.test' ) );
		$second = $route->respond( $token );
		$this->assertSame( ConfirmRoute::EXPIRED, $second['state'] );
		$this->assertSame( 'Link expired or already used.', $second['message'] );
	}

	public function test_REQ_249_confirm_link_expires(): void {
		$repo      = $this->repo();
		$token     = (string) $repo->add_pending( 'late@example.test' )['token'];
		$this->now = '2026-11-20 10:00:00';
		$this->assertSame( ConfirmRoute::EXPIRED, ( new ConfirmRoute( $repo ) )->respond( $token )['state'] );
		$this->assertFalse( $repo->is_subscribed( 'late@example.test' ) );
	}

	public function test_REQ_249_a_resend_gives_a_fresh_link_and_voids_the_old_one(): void {
		$repo      = $this->repo();
		$old       = (string) $repo->add_pending( 'again@example.test' )['token'];
		$this->now = '2026-11-18 10:00:00';
		$new       = (string) $repo->add_pending( 'again@example.test' )['token'];
		$route     = new ConfirmRoute( $repo );
		$this->assertSame( ConfirmRoute::EXPIRED, $route->respond( $old )['state'] );
		$this->assertSame( ConfirmRoute::CONFIRMED, $route->respond( $new )['state'] );
	}

	public function test_REQ_061_unknown_token_is_expired_and_unconfirmed_address_is_not_subscribed(): void {
		$repo = $this->repo();
		$repo->add_pending( 'pending@example.test' );
		$this->assertSame( ConfirmRoute::EXPIRED, ( new ConfirmRoute( $repo ) )->respond( 'nonsense-token-nonsense-token' )['state'] );
		$this->assertFalse( $repo->is_subscribed( 'pending@example.test' ), 'Pending addresses get no marketing email.' );
		$this->assertSame( array(), $repo->list() );
	}

	public function test_REQ_249_route_is_registered(): void {
		global $wp_rewrite;
		( new ConfirmRoute( $this->repo() ) )->add_rewrite();
		$this->assertArrayHasKey( '^confirm/([A-Za-z0-9_-]{20,64})/?$', $wp_rewrite->extra_rules_top );
	}
}
