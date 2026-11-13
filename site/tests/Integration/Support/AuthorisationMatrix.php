<?php
/**
 * Reusable harness: given a screen, say what each kind of user gets (200 or 403) and what their menu shows. Any module's test
 * adds one line per screen. It asks WordPress the same questions WordPress asks when a page is requested.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.GlobalVariablesOverride,WordPress.NamingConventions.PrefixAllGlobals -- the harness resets the admin menu globals the way a fresh admin request would.

namespace WhoIsWho\Tests\Integration\Support;

use WhoIsWho\Core\Platform\Admin\ScreenRegistry;

/**
 * Authorisation matrix.
 */
final class AuthorisationMatrix {

	/**
	 * Create the five kinds of user the matrix covers.
	 *
	 * @param \WP_UnitTest_Factory $factory     WordPress test factory.
	 * @param string               $granted_cap Capability granted to the "granted" user.
	 * @param string               $revoked_cap Capability the "revoked" user has an explicit false for.
	 * @return array<string, int> Label => user id: reviewer, editor, administrator, granted, revoked.
	 */
	public static function users( $factory, string $granted_cap, string $revoked_cap ): array {
		$granted = $factory->user->create( array( 'role' => 'wiw_reviewer' ) );
		( new \WP_User( $granted ) )->add_cap( $granted_cap );
		$revoked = $factory->user->create( array( 'role' => 'wiw_editor' ) );
		( new \WP_User( $revoked ) )->add_cap( $revoked_cap, false );

		return array(
			'reviewer'      => $factory->user->create( array( 'role' => 'wiw_reviewer' ) ),
			'editor'        => $factory->user->create( array( 'role' => 'wiw_editor' ) ),
			'administrator' => $factory->user->create( array( 'role' => 'administrator' ) ),
			'granted'       => $granted,
			'revoked'       => $revoked,
		);
	}

	/**
	 * What WordPress answers for a direct request to a screen: 200 or 403.
	 *
	 * @param int    $user_id User.
	 * @param string $slug    Screen slug.
	 */
	public static function status( int $user_id, string $slug ): int {
		global $plugin_page, $pagenow, $_wp_submenu_nopriv, $_registered_pages, $menu, $submenu, $_wp_menu_nopriv, $admin_page_hooks;
		wp_set_current_user( 0 ); // WordPress caches the current user: reset it so granted or revoked capabilities are seen.
		wp_set_current_user( $user_id );
		$menu               = array();
		$submenu            = array();
		$_wp_menu_nopriv    = array();
		$_wp_submenu_nopriv = array();
		$_registered_pages  = array();
		$admin_page_hooks   = array();
		do_action( 'admin_menu' );

		$pagenow     = 'admin.php';
		$plugin_page = $slug;
		return user_can_access_admin_page() ? 200 : 403;
	}

	/**
	 * Slugs shown in the WHOISWHO menus (every group together) for a user.
	 *
	 * @param int $user_id User.
	 * @return string[]
	 */
	public static function menu( int $user_id ): array {
		return array_merge( array(), ...array_values( self::menu_groups( $user_id ) ) );
	}

	/**
	 * The WHOISWHO top-level menus a user sees, as group id => the screen slugs in it. A group with no item for the user, or whose
	 * top-level entry WordPress removed, is not in the result.
	 *
	 * @param int $user_id User.
	 * @return array<string, string[]>
	 */
	public static function menu_groups( int $user_id ): array {
		global $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv, $_registered_pages, $admin_page_hooks;
		wp_set_current_user( 0 ); // WordPress caches the current user: reset it so granted or revoked capabilities are seen.
		wp_set_current_user( $user_id );
		$menu               = array();
		$submenu            = array();
		$_wp_menu_nopriv    = array();
		$_wp_submenu_nopriv = array();
		$_registered_pages  = array();
		$admin_page_hooks   = array();
		do_action( 'admin_menu' );

		$shown = array();
		foreach ( $menu as $entry ) {
			$shown[ (string) $entry[2] ] = true;
		}
		$out = array();
		foreach ( array_keys( ScreenRegistry::groups() ) as $group ) {
			$parent = ScreenRegistry::menu_slug( $group );
			if ( ! isset( $shown[ $parent ] ) ) {
				continue;
			}
			$slugs = array();
			foreach ( $submenu[ $parent ] ?? array() as $item ) {
				if ( current_user_can( $item[1] ) ) {
					$slugs[] = $item[2];
				}
			}
			if ( array() !== $slugs ) {
				$out[ $group ] = $slugs;
			}
		}
		return $out;
	}

	/**
	 * Assert a screen's 200 or 403 for every kind of user.
	 *
	 * @param \PHPUnit\Framework\TestCase $test     Test case.
	 * @param string                      $slug     Screen slug.
	 * @param array<string, int>          $users    Label => user id, from users().
	 * @param array<string, int>          $expected Label => expected status.
	 */
	public static function assert_statuses( $test, string $slug, array $users, array $expected ): void {
		foreach ( $expected as $label => $status ) {
			$test->assertSame( $status, self::status( $users[ $label ], $slug ), "{$slug} for {$label}" );
		}
	}
}
