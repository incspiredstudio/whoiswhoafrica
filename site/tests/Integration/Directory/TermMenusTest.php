<?php
/**
 * Admin menu entries for the classification lists (REQ-009, REQ-240): Directory > Industries, Countries & cities and Organisation
 * types point at the native term screens for profiles, the right item is current while a term screen is open, and the industry
 * list under Posts and Events is labelled as the shared list. Also the 0.3.0 release-test fixes around them: the stored plugin
 * version follows the code (REQ-056), the sector chip reads the sector's name and the Other industries hint follows the chips.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Admin\ClassificationPicker;
use WhoIsWho\Core\Directory\Admin\TermMenus;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Activation;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\WpTestCase;

final class TermMenusTest extends WpTestCase {

	/**
	 * Saved globals.
	 *
	 * @var array<string, mixed>
	 */
	private array $saved = array();

	protected function setUp(): void {
		parent::setUp();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( 'menu', 'submenu', '_wp_real_parent_file', '_wp_submenu_nopriv', '_registered_pages', '_parent_pages', 'typenow', 'taxnow', 'pagenow' ) as $name ) {
			$this->saved[ $name ] = $GLOBALS[ $name ] ?? null;
		}
		$GLOBALS['menu']    = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored in tearDown.
		$GLOBALS['submenu'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored in tearDown.
	}

	protected function tearDown(): void {
		foreach ( $this->saved as $name => $value ) {
			$GLOBALS[ $name ] = $value; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring.
		}
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Build the menu of a fresh registry with the term links, as a user with a role.
	 *
	 * @param string $role Role.
	 * @return array<int, array<int, string>> The Directory group's submenu items.
	 */
	private function directory_menu( string $role ): array {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		$registry = new ScreenRegistry();
		$registry->register( 'wiw-qwv-profiles', 'Profiles', 'read_private_wiw_profiles', '__return_null', false, 'directory' );
		TermMenus::register( $registry );
		$registry->add_menu();
		global $submenu;
		return array_values( $submenu[ ScreenRegistry::menu_slug( 'directory' ) ] ?? array() );
	}

	public function test_REQ_240_directory_lists_industries_places_and_organisation_types(): void {
		$items = array_column( $this->directory_menu( 'wiw_editor' ), 0, 2 );
		$this->assertSame( 'Industries', $items['edit-tags.php?taxonomy=wiw_industry&post_type=wiw_profile'] ?? null );
		$this->assertSame( 'Countries & cities', $items['edit-tags.php?taxonomy=wiw_place&post_type=wiw_profile'] ?? null );
		$this->assertSame( 'Organisation types', $items['edit-tags.php?taxonomy=wiw_org_type&post_type=wiw_profile'] ?? null );
		$this->assertSame( array( 'wiw-qwv-profiles', TermMenus::url( Taxonomies::INDUSTRY ), TermMenus::url( Taxonomies::PLACE ), TermMenus::url( Taxonomies::ORG_TYPE ) ), array_keys( $items ), 'In menu order, after Profiles.' );
	}

	public function test_REQ_240_the_term_links_follow_the_capability_and_the_registry_refuses_odd_links(): void {
		$this->assertFalse( user_can( self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) ), TermMenus::CAPABILITY ), 'Reviewers only assign terms.' );
		$registry = new ScreenRegistry();
		TermMenus::register( $registry );
		$this->assertContains( 'directory', $registry->visible_groups( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) ) );
		$this->expectException( \InvalidArgumentException::class );
		$registry->register_link( 'https://example.org/x', 'X', 'manage_terms', 'directory' );
	}

	public function test_REQ_240_the_open_term_screen_highlights_its_own_item(): void {
		global $typenow, $taxnow, $pagenow;
		$typenow = ProfileType::POST_TYPE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored in tearDown.
		foreach ( array( 'edit-tags.php', 'term.php' ) as $screen ) {
			foreach ( array_keys( TermMenus::labels() ) as $taxonomy ) {
				$pagenow = $screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored in tearDown.
				$taxnow  = $taxonomy; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored in tearDown.
				$this->assertSame( ScreenRegistry::menu_slug( 'directory' ), ProfileType::menu_parent( 'edit.php?post_type=wiw_profile' ) );
				$this->assertSame( TermMenus::url( $taxonomy ), ProfileType::menu_current( null ), $screen . ' ' . $taxonomy );
			}
		}
		$pagenow = 'edit.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored in tearDown.
		$taxnow  = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored in tearDown.
		$this->assertSame( 'wiw-profiles', ProfileType::menu_current( null ), 'The profile list keeps Profiles current.' );
	}

	public function test_REQ_240_the_industry_list_under_posts_and_events_says_it_is_shared(): void {
		global $submenu;
		$submenu = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored in tearDown.
			'edit.php'                     => array( array( 'Industries', 'manage_terms', 'edit-tags.php?taxonomy=wiw_industry' ) ),
			'edit.php?post_type=wiw_event' => array( array( 'Industries', 'manage_terms', 'edit-tags.php?taxonomy=wiw_industry&amp;post_type=wiw_event' ) ),
		);
		TermMenus::label_shared_lists();
		$this->assertSame( 'Industries (shared with profiles)', $submenu['edit.php'][0][0] );
		$this->assertSame( 'Industries (shared with profiles)', $submenu['edit.php?post_type=wiw_event'][0][0] );
	}

	public function test_REQ_056_the_stored_version_follows_the_plugin_after_an_upgrade(): void {
		update_option( 'wiw_core_version', '0.2.0', false );
		Activation::sync_version();
		$this->assertSame( WIW_CORE_VERSION, get_option( 'wiw_core_version' ) );
		Activation::sync_version();
		$this->assertSame( WIW_CORE_VERSION, get_option( 'wiw_core_version' ), 'Nothing changes when they match.' );
		$this->assertNotFalse( has_action( 'admin_init', array( Activation::class, 'sync_version' ) ), 'It runs on admin_init.' );
	}

	public function test_REQ_009_the_help_text_names_the_real_menu_labels_and_the_hint_can_follow_the_chips(): void {
		$picker = new ClassificationPicker();
		$html   = implode(
			'',
			array_column(
				$picker->rows(
					array(
						'primary_industry' => 0,
						'industries'       => array(),
						'org_type'         => 0,
						'country'          => 0,
						'city'             => 0,
					),
					'organisation'
				),
				'html'
			)
		);
		$this->assertStringContainsString( 'Directory &gt; Industries', $html );
		$this->assertStringContainsString( 'Directory &gt; Countries &amp; cities', $html );
		$this->assertStringContainsString( 'Directory &gt; Organisation types', $html );
		$this->assertStringNotContainsString( 'Directory &gt; Places', $html );
		$this->assertMatchesRegularExpression( '#data-wiw-chips-hint="[^"]+" data-hint-none="None yet[^"]*" data-hint-some="Untick#', $html );
	}

	public function test_REQ_007_the_sector_chip_reads_the_sector_name(): void {
		switch_theme( 'whoiswho-child' );
		$state = wiw_filter_bar_state(
			array(
				'action'   => '/directory/',
				'controls' => array(
					array(
						'name'    => 'industry',
						'label'   => 'Industry',
						'value'   => 'arts-culture-sport',
						'options' => array(
							'group:arts-culture-sport' => array(
								'label'   => 'Arts, Culture & Sport',
								'options' => array(
									'arts-culture-sport' => 'All Arts, Culture & Sport',
									'music'              => 'Music',
								),
							),
						),
					),
				),
			)
		);
		$this->assertSame( 'Arts, Culture & Sport', $state['chips'][0]['value'] );
	}
}
