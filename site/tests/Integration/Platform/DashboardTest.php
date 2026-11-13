<?php
/**
 * The role-based dashboard (REQ-239, REQ-240, REQ-235): tiles follow effective capabilities and no tile type offers a target or
 * progress bar. Needs the WordPress test suite and the real profile state table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Dashboard\DashboardScreen;
use WhoIsWho\Core\Platform\Dashboard\TileRegistry;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Tests\WpTestCase;

final class DashboardTest extends WpTestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
	}

	/**
	 * The dashboard as a user of a role sees it.
	 *
	 * @param string $role Role.
	 */
	private function html_for( string $role ): string {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		return ( new DashboardScreen() )->html();
	}

	public function test_REQ_239_reviewer_dashboard_shows_no_commercial_tiles(): void {
		$html = $this->html_for( 'wiw_reviewer' );
		$this->assertStringContainsString( 'data-tile="profiles_published_pending"', $html );
		$this->assertStringNotContainsString( 'commercial_placeholder', $html );
		$this->assertStringContainsString( 'commercial_placeholder', $this->html_for( 'administrator' ) );
	}

	public function test_REQ_239_dashboard_shows_plain_counts_and_no_target_or_progress_bar(): void {
		$html = $this->html_for( 'administrator' );
		$this->assertStringContainsString( 'wiw-count', $html );
		$this->assertStringNotContainsString( '<progress', $html );
		$this->assertStringNotContainsString( 'role="progressbar"', $html );
		$this->assertStringNotContainsString( 'target', strtolower( $html ) );
	}

	public function test_REQ_239_no_tile_type_offers_a_target_or_progress_bar(): void {
		$this->assertSame( array( 'count', 'list' ), TileRegistry::TYPES );
		$tiles = new TileRegistry();
		foreach ( array( 'progress', 'target', 'goal', 'bar' ) as $type ) {
			try {
				$tiles->register( 't_' . $type, 'Tile', 'wiw_view_dashboard_review', $type, '__return_zero' );
				$this->fail( 'the ' . $type . ' tile type must not exist' );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'no target or progress', $e->getMessage() );
			}
		}
		$this->expectException( \InvalidArgumentException::class );
		$tiles->register( 'no_cap', 'Tile', '', 'count', '__return_zero' );
	}

	public function test_REQ_239_tile_follows_effective_capability(): void {
		$tiles = new TileRegistry();
		$tiles->register( 'sales', 'Sales', 'wiw_view_dashboard_commercial', TileRegistry::COUNT, static fn (): int => 3 );
		$user = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$this->assertArrayNotHasKey( 'sales', $tiles->visible_to( $user ) );
		get_userdata( $user )->add_cap( 'wiw_view_dashboard_commercial' );
		$this->assertArrayHasKey( 'sales', $tiles->visible_to( $user ) );
		get_userdata( $user )->remove_cap( 'wiw_view_dashboard_commercial' );
		$this->assertArrayNotHasKey( 'sales', $tiles->visible_to( $user ) );
	}

	/**
	 * Ids of the WHOISWHO widgets on the WordPress Dashboard for a user.
	 *
	 * @param int $user_id User.
	 * @return string[]
	 */
	private function widget_ids( int $user_id ): array {
		global $wp_meta_boxes;
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );
		$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the test starts from an empty Dashboard.
		DashboardScreen::add_widgets();
		$ids = array();
		foreach ( $wp_meta_boxes['dashboard'] ?? array() as $contexts ) {
			foreach ( $contexts as $priorities ) {
				$ids = array_merge( $ids, array_keys( $priorities ) );
			}
		}
		return $ids;
	}

	public function test_REQ_239_tiles_appear_as_widgets_on_the_wordpress_dashboard(): void {
		$ids = $this->widget_ids( self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) ) );
		$this->assertContains( DashboardScreen::WIDGET . 'profiles_published_pending', $ids );
		$this->assertNotContains( DashboardScreen::WIDGET . 'commercial_placeholder', $ids, 'a reviewer has no commercial widget' );
		$this->assertContains( DashboardScreen::WIDGET . 'commercial_placeholder', $this->widget_ids( self::factory()->user->create( array( 'role' => 'administrator' ) ) ) );
	}

	public function test_REQ_240_a_user_without_the_capability_gets_no_widget(): void {
		$this->assertSame( array(), array_filter( $this->widget_ids( self::factory()->user->create( array( 'role' => 'subscriber' ) ) ), static fn ( $id ) => str_starts_with( (string) $id, DashboardScreen::WIDGET ) ) );
		$revoked = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		( new \WP_User( $revoked ) )->add_cap( DashboardScreen::CAPABILITY, false );
		$this->assertNotContains( DashboardScreen::WIDGET . 'profiles_published_pending', $this->widget_ids( $revoked ) );
	}

	public function test_REQ_239_each_widget_follows_its_own_capability(): void {
		$user = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		( new \WP_User( $user ) )->add_cap( DashboardScreen::COMMERCIAL, false );
		$ids = $this->widget_ids( $user );
		$this->assertNotContains( DashboardScreen::WIDGET . 'commercial_placeholder', $ids );
		$this->assertContains( DashboardScreen::WIDGET . 'profiles_published_pending', $ids );
	}

	public function test_REQ_240_the_old_dashboard_screen_url_redirects_to_the_wordpress_dashboard(): void {
		$request = new Request( array( 'page' => DashboardScreen::SCREEN ) );
		$this->assertSame( admin_url( 'index.php' ), DashboardScreen::old_screen_target( $request, self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) ) ) );
		$this->assertSame( admin_url( 'index.php' ), DashboardScreen::old_screen_target( $request, self::factory()->user->create( array( 'role' => 'administrator' ) ) ) );
		$this->assertNull( DashboardScreen::old_screen_target( $request, self::factory()->user->create( array( 'role' => 'subscriber' ) ) ), 'no access before, nothing to redirect' );
		$this->assertNull( DashboardScreen::old_screen_target( new Request( array( 'page' => 'wiw-claims' ) ), self::factory()->user->create( array( 'role' => 'administrator' ) ) ) );
	}

	public function test_REQ_240_the_dashboard_refuses_a_user_without_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->expectException( \DomainException::class );
		( new DashboardScreen() )->html();
	}
}
