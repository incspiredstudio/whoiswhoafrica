<?php
/**
 * The admin menu is split by job (REQ-244, REQ-240, REQ-235): one top-level menu per group, each shown only to users who hold the
 * capability of at least one of its screens. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class AdminMenuGroupsTest extends WpTestCase {

	/**
	 * Users by label.
	 *
	 * @var array<string, int>
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();

		$registry = new ScreenRegistry();
		$registry->register( 'wiw-probe-review', 'Probe review', 'wiw_review_records', '__return_null', false, 'review' );
		$registry->register( 'wiw-probe-queue', 'Probe queue', 'wiw_review_records', '__return_null', false, 'review' );
		$registry->register( 'wiw-probe-imports', 'Probe imports', 'wiw_run_imports', '__return_null', false, 'imports' );
		$registry->register( 'wiw-probe-detail', 'Probe detail', 'wiw_run_imports', '__return_null', true, 'compliance' );
		$registry->register( 'wiw-probe-default', 'Probe default', 'wiw_run_imports', '__return_null' );
		$registry->hook();
		wp_set_current_user( 0 );
		$this->users               = AuthorisationMatrix::users( self::factory(), 'wiw_run_imports', 'wiw_review_records' );
		$this->users['subscriber'] = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	public function test_REQ_244_each_user_sees_only_the_groups_of_screens_they_can_open(): void {
		$this->assertSame( array( 'review' => array( 'wiw-probe-review', 'wiw-probe-queue' ) ), AuthorisationMatrix::menu_groups( $this->users['reviewer'] ) );
		$this->assertSame(
			array(
				ScreenRegistry::DEFAULT_GROUP => array( 'wiw-probe-default' ),
				'review'                      => array( 'wiw-probe-review', 'wiw-probe-queue' ),
				'imports'                     => array( 'wiw-probe-imports' ),
			),
			AuthorisationMatrix::menu_groups( $this->users['administrator'] )
		);
		$this->assertSame( array( ScreenRegistry::DEFAULT_GROUP, 'review', 'imports' ), array_keys( AuthorisationMatrix::menu_groups( $this->users['granted'] ) ), 'the granted user sees the imports group as well' );
	}

	public function test_REQ_244_no_group_shows_for_a_user_with_no_capability(): void {
		$this->assertSame( array(), AuthorisationMatrix::menu_groups( $this->users['subscriber'] ) );
		$this->assertSame( array(), AuthorisationMatrix::menu_groups( $this->users['revoked'] ) );
		$this->assertSame( array(), AuthorisationMatrix::menu( $this->users['subscriber'] ) );
	}

	public function test_REQ_240_a_detail_screen_adds_no_menu_and_the_top_level_menu_is_not_blank_for_it(): void {
		$this->assertArrayNotHasKey( 'compliance', AuthorisationMatrix::menu_groups( $this->users['administrator'] ), 'a hidden screen alone makes no group' );
		$this->assertSame( 200, AuthorisationMatrix::status( $this->users['administrator'], 'wiw-probe-detail' ), 'but it still opens for those with the capability' );
		$this->assertSame( 403, AuthorisationMatrix::status( $this->users['reviewer'], 'wiw-probe-detail' ) );
	}

	/**
	 * A detail screen has no menu item, but its page title still renders (#810).
	 */
	public function test_REQ_244_a_detail_screen_still_gets_its_page_title(): void {
		AuthorisationMatrix::menu_groups( $this->users['administrator'] );
		$hook = get_plugin_page_hookname( 'wiw-probe-detail', '' );
		$this->assertNotFalse( has_action( 'load-' . $hook ), 'a loader is hooked for the detail screen' );
		$before           = $GLOBALS['title'] ?? null;
		$GLOBALS['title'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test of the admin title.
		do_action( 'load-' . $hook );
		$this->assertSame( 'Probe detail', $GLOBALS['title'] );
		$this->assertSame( ScreenRegistry::menu_slug( 'compliance' ), apply_filters( 'parent_file', '' ) );
		$GLOBALS['title'] = $before; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore.
	}

	/**
	 * While a detail screen is open, its group's menu stays highlighted and its list's item is current, even after the menu code
	 * calls get_admin_page_parent() (which reset the parent before, issue 806). The capability check is unchanged.
	 */
	public function test_REQ_244_a_detail_screen_highlights_its_group_and_list_after_get_admin_page_parent(): void {
		global $pagenow, $plugin_page, $parent_file, $submenu_file, $submenu;
		$registry = new ScreenRegistry();
		$registry->register( 'wiw-probe-list', 'Probe list', 'wiw_run_imports', '__return_null', false, 'awards' );
		$registry->register( 'wiw-probe-item', 'Probe item', 'wiw_run_imports', '__return_null', true, 'awards', 'wiw-probe-list' );
		$registry->hook();
		$this->assertSame( 403, AuthorisationMatrix::status( $this->users['reviewer'], 'wiw-probe-item' ), 'still refused without the capability' );
		$this->assertSame( 200, AuthorisationMatrix::status( $this->users['administrator'], 'wiw-probe-item' ) );
		$this->assertContains( 'wiw-probe-item', array_column( $submenu[''] ?? array(), 2 ), 'WordPress keeps the page under an empty parent' );

		$before = array( $pagenow, $plugin_page, $parent_file, $submenu_file );
		$hook   = get_plugin_page_hookname( 'wiw-probe-item', '' );
		do_action( 'load-' . $hook );
		// What wp-admin/menu-header.php does before it draws the menu.
		$parent_file  = apply_filters( 'parent_file', '' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test of the admin menu.
		$submenu_file = apply_filters( 'submenu_file', null, $parent_file ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test of the admin menu.
		get_admin_page_parent();
		$this->assertSame( ScreenRegistry::menu_slug( 'awards' ), $parent_file );
		$this->assertSame( 'wiw-probe-list', $submenu_file );
		list( $pagenow, $plugin_page, $parent_file, $submenu_file ) = $before; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore.
	}

	/**
	 * There is one Profiles menu item, under Directory: the profile type adds no top-level menu, and WordPress's own profile
	 * screens highlight Directory > Profiles (issue 806).
	 */
	public function test_REQ_244_the_profile_type_has_no_top_level_menu(): void {
		global $typenow;
		if ( ! post_type_exists( \WhoIsWho\Core\Directory\ProfileType::POST_TYPE ) ) {
			( new \WhoIsWho\Core\Directory\ProfileType() )->register();
		}
		$type = get_post_type_object( \WhoIsWho\Core\Directory\ProfileType::POST_TYPE );
		$this->assertInstanceOf( \WP_Post_Type::class, $type );
		$this->assertFalse( $type->show_in_menu );
		$this->assertTrue( $type->show_ui, 'the type keeps its screens and capabilities' );
		$this->assertSame( 'edit_wiw_profiles', $type->cap->edit_posts );

		$before  = $typenow;
		$typenow = \WhoIsWho\Core\Directory\ProfileType::POST_TYPE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test of the admin menu.
		$this->assertSame( ScreenRegistry::menu_slug( 'directory' ), \WhoIsWho\Core\Directory\ProfileType::menu_parent( 'edit.php?post_type=wiw_profile' ) );
		$this->assertSame( \WhoIsWho\Core\Directory\Admin\ProfilesScreen::SCREEN, \WhoIsWho\Core\Directory\ProfileType::menu_current( null ) );
		$typenow = 'post'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test of the admin menu.
		$this->assertSame( 'edit.php', \WhoIsWho\Core\Directory\ProfileType::menu_parent( 'edit.php' ), 'other types are untouched' );
		$typenow = $before; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore.
	}

	/**
	 * The detail screens of the plugin (Edit profile, Review item) are not menu items; WordPress's own "Add profile" under its
	 * Profiles menu is gone and its address leads to Directory > Add profile (#810).
	 */
	public function test_REQ_244_detail_screens_and_core_add_new_are_not_in_the_menus(): void {
		foreach ( array( 'Directory/Admin/ProfileEditScreen.php', 'Review/Admin/ReviewItemScreen.php' ) as $file ) {
			$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->assertMatchesRegularExpression( '#true,\s*// A detail screen#', $source, "{$file} registers a detail screen" );
		}
		$screens = ScreenRegistry::shared()->screens();
		foreach ( array( \WhoIsWho\Core\Directory\Admin\ProfileEditScreen::SCREEN, \WhoIsWho\Core\Review\Admin\ReviewItemScreen::SCREEN ) as $slug ) {
			if ( isset( $screens[ $slug ] ) ) {
				$this->assertTrue( $screens[ $slug ]['hidden'], "{$slug} is a detail screen" );
			}
		}

		global $submenu;
		$parent             = 'edit.php?post_type=wiw_profile';
		$submenu[ $parent ] = array( array( 'Add profile', 'create_wiw_profiles', 'post-new.php?post_type=wiw_profile' ), array( 'Profiles', 'edit_wiw_profiles', $parent ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test of the admin menu.
		\WhoIsWho\Core\Directory\Admin\AddProfileScreen::hide_core_add_new();
		$this->assertSame( array( $parent ), array_column( array_values( $submenu[ $parent ] ), 2 ) );

		wp_set_current_user( $this->users['administrator'] );
		$this->assertSame( \WhoIsWho\Core\Directory\Admin\AddProfileScreen::url(), \WhoIsWho\Core\Directory\Admin\AddProfileScreen::core_add_new_target( 'wiw_profile' ) );
		$this->assertNull( \WhoIsWho\Core\Directory\Admin\AddProfileScreen::core_add_new_target( 'post' ), 'other types are untouched' );
		wp_set_current_user( $this->users['reviewer'] );
		$this->assertNull( \WhoIsWho\Core\Directory\Admin\AddProfileScreen::core_add_new_target( 'wiw_profile' ), 'without the capability WordPress answers as before' );
		wp_set_current_user( 0 );
	}

	public function test_REQ_240_screens_keep_their_slug_and_their_403_in_any_group(): void {
		$this->assertSame( 200, AuthorisationMatrix::status( $this->users['reviewer'], 'wiw-probe-review' ) );
		$this->assertSame( 403, AuthorisationMatrix::status( $this->users['reviewer'], 'wiw-probe-imports' ) );
		$this->assertSame( 200, AuthorisationMatrix::status( $this->users['administrator'], 'wiw-probe-imports' ) );
	}

	public function test_REQ_235_a_screen_cannot_name_a_group_that_does_not_exist(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new ScreenRegistry() )->register( 'wiw-x', 'X', 'read', '__return_null', false, 'nowhere' );
	}

	public function test_REQ_244_the_groups_have_distinct_titles_icons_and_positions(): void {
		$groups = ScreenRegistry::groups();
		foreach ( array( 'directory', 'review', 'imports', 'audience', 'commercial', 'awards', 'compliance', 'settings' ) as $id ) {
			$this->assertArrayHasKey( $id, $groups );
		}
		$this->assertCount( count( $groups ), array_unique( array_column( $groups, 'title' ) ) );
		$this->assertCount( count( $groups ), array_unique( array_column( $groups, 'position' ) ) );
	}

	public function test_REQ_244_our_settings_group_is_called_site_settings_so_it_never_looks_like_the_wordpress_settings_menu(): void {
		$groups = ScreenRegistry::groups();
		$this->assertSame( 'Site settings', $groups['settings']['title'] );
		$this->assertNotContains( 'Settings', array_column( $groups, 'title' ), 'WordPress already has a Settings menu (wpDiscuz and FluentSMTP add to it)' );
	}
}
