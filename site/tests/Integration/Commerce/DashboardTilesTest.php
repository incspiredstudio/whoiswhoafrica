<?php
/**
 * The commercial dashboard tiles (REQ-181, REQ-189, REQ-272). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\DashboardTiles;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Dashboard\DashboardScreen;
use WhoIsWho\Core\Platform\Dashboard\TileRegistry;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\TestCase;

final class DashboardTilesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		foreach ( array( CustomerRepository::table(), ArrangementRepository::table(), PlacementRepository::table() ) as $table ) {
			$wpdb->query( 'TRUNCATE TABLE ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		}
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::SPRINT_TRACKING );
	}

	protected function tearDown(): void {
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::SPRINT_TRACKING );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A user with a role.
	 *
	 * @param string $role Role.
	 */
	private function user( string $role ): int {
		return (int) wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
	}

	private function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( '2026-12-10 10:00:00', new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Registry with the commercial tiles and today fixed to 2026-12-10.
	 */
	private function tiles(): TileRegistry {
		$tiles = new TileRegistry();
		DashboardTiles::register_into( $tiles, new SettingsService( SettingsService::default_registry() ), new ArrangementRepository(), new PlacementRepository(), static fn (): string => '2026-12-10' );
		return $tiles;
	}

	/**
	 * Add a deal.
	 *
	 * @param bool $paid Whether it counts.
	 */
	private function deal( int $amount, bool $paid = true ): int {
		$fields = array(
			'customer_id'  => 1,
			'amount_minor' => $amount,
			'agreed'       => true,
		);
		if ( $paid ) {
			$fields += array(
				'method'       => 'transfer',
				'reference'    => 'R',
				'received_on'  => '2026-12-01',
				'confirmed_by' => 1,
			);
		}
		return ( new ArrangementRepository() )->insert( $fields, $this->now() );
	}

	public function test_REQ_181_dashboard_shows_counted_deals_and_revenue_to_admin_only(): void {
		$this->deal( 50000000 );
		$this->deal( 20000000 );
		$this->deal( 99900000, false );
		$tiles = $this->tiles()->all();
		$this->assertSame( 2, ( $tiles['deals_counted']['renderer'] )() );
		$this->assertSame( 700000, ( $tiles['revenue_confirmed']['renderer'] )(), 'Whole naira of counted deals only.' );
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$this->assertSame( array(), array_intersect( array( 'deals_counted', 'revenue_confirmed', 'slots_ending' ), array_keys( $this->tiles()->visible_to( get_current_user_id() ) ) ) );
		wp_set_current_user( $this->user( 'administrator' ) );
		$this->assertSame( array( 'deals_counted', 'revenue_confirmed', 'slots_ending' ), array_values( array_intersect( array( 'deals_counted', 'revenue_confirmed', 'slots_ending' ), array_keys( $this->tiles()->visible_to( get_current_user_id() ) ) ) ) );
		$this->assertStringContainsString( 'data-tile="deals_counted"', ( new DashboardScreen( $this->tiles() ) )->html() );
	}

	public function test_REQ_189_slots_ending_in_30_days_listed_without_action(): void {
		$deal   = $this->deal( 100 );
		$one    = static fn ( string $sponsor, string $slot, string $from, string $to, int $deal_id, int $story ): array => array(
			'arrangement_id' => $deal_id,
			'story_id'       => $story,
			'sponsor_name'   => $sponsor,
			'slot'           => $slot,
			'starts_on'      => $from,
			'ends_on'        => $to,
		);
		$places = new PlacementRepository();
		$places->insert( $one( 'Soon Ltd', 'home', '2026-12-01', '2026-12-20', $deal, 11 ), $this->now() );
		$places->insert( $one( 'Later Ltd', 'stories', '2026-12-01', '2027-03-01', $deal, 12 ), $this->now() );
		$places->insert( $one( 'Over Ltd', 'stories', '2026-11-01', '2026-12-05', $deal, 13 ), $this->now() );
		$lines = DashboardTiles::slots_ending( $places, '2026-12-10' );
		$this->assertSame( array( 'Soon Ltd (home) ends 2026-12-20' ), $lines );
		$this->assertSame( 3, count( $places->all_with_deal_state() ), 'Listing changes nothing.' );
	}

	public function test_REQ_272_sprint_card_hidden_when_tracking_off(): void {
		update_option( SettingsService::OPTION_PREFIX . SettingsService::SPRINT_TRACKING, '0' );
		$ids = array_keys( $this->tiles()->all() );
		$this->assertNotContains( 'deals_counted', $ids );
		$this->assertNotContains( 'revenue_confirmed', $ids );
		$this->assertContains( 'slots_ending', $ids );
	}
}
