<?php
/**
 * The one way to add a screen to the WHOISWHO admin menus. Every screen needs a capability: there is no way to register one
 * without it. The menus follow the user's effective capabilities, and a direct URL without the capability is refused by
 * WordPress with a 403, because the page is registered with that capability. Screens sit in menu groups by job; a group with
 * nothing the user can open is not shown to that user.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Admin;

/**
 * Screen registry.
 */
final class ScreenRegistry {

	/**
	 * The default group, used by a screen that names none. Its menu slug is this value.
	 */
	public const MENU_SLUG     = 'wiw';
	public const DEFAULT_GROUP = 'wiw';

	/**
	 * The menu groups: id => icon and position. Titles are translated in groups(). A screen picks one id, so putting a screen
	 * in a menu is one argument to register().
	 */
	private const GROUPS = array(
		self::DEFAULT_GROUP => array( 'dashicons-groups', 3.0 ),
		'directory'         => array( 'dashicons-id-alt', 3.1 ),
		'review'            => array( 'dashicons-yes-alt', 3.2 ),
		'imports'           => array( 'dashicons-database-import', 3.3 ),
		'audience'          => array( 'dashicons-email-alt', 3.4 ),
		'commercial'        => array( 'dashicons-megaphone', 3.5 ),
		'awards'            => array( 'dashicons-awards', 3.6 ),
		'compliance'        => array( 'dashicons-shield', 3.7 ),
		'settings'          => array( 'dashicons-admin-generic', 3.8 ),
	);

	/**
	 * The registry used by the plugin.
	 *
	 * @var ScreenRegistry|null
	 */
	private static ?ScreenRegistry $shared = null;

	/**
	 * Registered screens by slug.
	 *
	 * @var array<string, array{title: string, capability: string, callback: callable, hidden: bool, group: string, current: string}>
	 */
	private array $screens = array();

	/**
	 * The shared registry.
	 */
	public static function shared(): self {
		if ( null === self::$shared ) {
			self::$shared = new self();
		}
		return self::$shared;
	}

	/**
	 * The menu groups: id => title, icon, position.
	 *
	 * @return array<string, array{title: string, icon: string, position: float}>
	 */
	public static function groups(): array {
		$titles = array(
			self::DEFAULT_GROUP => __( 'WHOISWHO', 'whoiswho' ),
			'directory'         => __( 'Directory', 'whoiswho' ),
			'review'            => __( 'Review', 'whoiswho' ),
			'imports'           => __( 'Imports', 'whoiswho' ),
			'audience'          => __( 'Audience', 'whoiswho' ),
			'commercial'        => __( 'Commercial', 'whoiswho' ),
			'awards'            => __( 'Awards', 'whoiswho' ),
			'compliance'        => __( 'Compliance', 'whoiswho' ),
			'settings'          => __( 'Site settings', 'whoiswho' ),
		);
		$out    = array();
		foreach ( self::GROUPS as $id => $meta ) {
			$out[ $id ] = array(
				'title'    => $titles[ $id ],
				'icon'     => $meta[0],
				'position' => $meta[1],
			);
		}
		return $out;
	}

	/**
	 * The top-level menu slug of a group.
	 *
	 * @param string $group Group id.
	 */
	public static function menu_slug( string $group ): string {
		return self::DEFAULT_GROUP === $group ? self::MENU_SLUG : 'wiw-menu-' . $group;
	}

	/**
	 * Attach hooks.
	 */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_menu', array( $this, 'hide_empty_menu' ), 999 );
	}

	/**
	 * Register a screen.
	 *
	 * @param string   $slug       Unique slug (lowercase letters, digits, dashes). It never changes: links and audit entries use it.
	 * @param string   $title      Menu and page title.
	 * @param string   $capability A named capability, for example wiw_review_records. Required.
	 * @param callable $callback   Renders the screen.
	 * @param bool     $hidden     True for a page reached by a link (a detail screen): it has no menu item but is guarded the same way.
	 * @param string   $group      The menu group (directory, review, imports, audience, commercial, awards, compliance, settings); the default group when omitted.
	 * @param string   $current    For a detail screen: the slug of the menu item to show as current while it is open (its list).
	 * @throws \InvalidArgumentException When the slug is invalid or taken, the capability is empty or the group is unknown.
	 */
	public function register( string $slug, string $title, string $capability, callable $callback, bool $hidden = false, string $group = self::DEFAULT_GROUP, string $current = '' ): void {
		if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
			throw new \InvalidArgumentException( 'A screen slug is lowercase letters, digits and dashes.' );
		}
		if ( '' === trim( $capability ) ) {
			throw new \InvalidArgumentException( 'A screen cannot be registered without a capability.' );
		}
		if ( ! isset( self::GROUPS[ $group ] ) ) {
			throw new \InvalidArgumentException( 'That menu group does not exist.' );
		}
		if ( isset( $this->screens[ $slug ] ) ) {
			throw new \InvalidArgumentException( 'That screen slug is already registered.' );
		}
		$this->screens[ $slug ] = array(
			'title'      => $title,
			'capability' => $capability,
			'callback'   => $callback,
			'hidden'     => $hidden,
			'group'      => $group,
			'current'    => $current,
		);
	}

	/**
	 * Registered screens.
	 *
	 * @return array<string, array{title: string, capability: string, callback: callable, hidden: bool, group: string, current: string}>
	 */
	public function screens(): array {
		return $this->screens;
	}

	/**
	 * Slugs of the screens a user can open.
	 *
	 * @param int $user_id User.
	 * @return string[]
	 */
	public function visible_slugs( int $user_id ): array {
		$out = array();
		foreach ( $this->screens as $slug => $screen ) {
			if ( user_can( $user_id, $screen['capability'] ) ) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	/**
	 * Ids of the groups with at least one menu item the user can open (hidden detail screens do not count).
	 *
	 * @param int $user_id User.
	 * @return string[]
	 */
	public function visible_groups( int $user_id ): array {
		$out = array();
		foreach ( $this->screens as $screen ) {
			if ( ! $screen['hidden'] && user_can( $user_id, $screen['capability'] ) ) {
				$out[ $screen['group'] ] = true;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Add one top-level menu per group that has screens, and one item per screen. Each item carries its own capability, so
	 * WordPress hides it from users without it and answers 403 to its URL, whichever group it is in.
	 */
	public function add_menu(): void {
		$used = array();
		foreach ( $this->screens as $screen ) {
			if ( ! $screen['hidden'] ) {
				$used[ $screen['group'] ] = true;
			}
		}
		foreach ( self::groups() as $id => $group ) {
			if ( isset( $used[ $id ] ) ) {
				add_menu_page( $group['title'], $group['title'], 'read', self::menu_slug( $id ), '__return_null', $group['icon'], $group['position'] );
			}
		}
		foreach ( $this->screens as $slug => $screen ) {
			$hook = add_submenu_page( $screen['hidden'] ? null : self::menu_slug( $screen['group'] ), $screen['title'], $screen['title'], $screen['capability'], $slug, $screen['callback'] );
			if ( $screen['hidden'] && is_string( $hook ) && '' !== $hook ) {
				// A detail screen has no menu item, so WordPress cannot find its title: set it, and keep its group's menu open.
				add_action( 'load-' . $hook, self::detail_screen_loader( $screen['title'], self::menu_slug( $screen['group'] ), $slug, $screen['current'] ) );
			}
		}
		foreach ( array_keys( $used ) as $id ) {
			remove_submenu_page( self::menu_slug( $id ), self::menu_slug( $id ) );
		}
	}

	/**
	 * What runs when a detail screen (no menu item) loads: its page title for the browser tab and heading, its group's menu open
	 * and highlighted, and the menu item of its list marked current.
	 *
	 * WordPress keeps a page registered without a parent under an empty parent key of the submenu list. After the parent_file
	 * filter, the menu code calls get_admin_page_parent(), which finds the page there and resets the parent to "", so the filter
	 * alone never highlights anything. This runs on load-{hook}, after WordPress has checked the capability (the entry has done its
	 * job) and before the menu is drawn, so the entry is dropped here and the filtered parent stays.
	 *
	 * @param string $title      Screen title.
	 * @param string $group_menu Group menu slug.
	 * @param string $slug       The detail screen's slug.
	 * @param string $current    The menu item to mark current, or "".
	 */
	public static function detail_screen_loader( string $title, string $group_menu, string $slug = '', string $current = '' ): callable {
		return static function () use ( $title, $group_menu, $slug, $current ): void {
			global $submenu;
			$GLOBALS['title'] = $title; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress reads the page title from this global.
			add_filter( 'parent_file', static fn (): string => $group_menu );
			if ( '' !== $current ) {
				add_filter( 'submenu_file', static fn (): string => $current );
			}
			if ( '' !== $slug && isset( $submenu[''] ) && is_array( $submenu[''] ) ) {
				foreach ( $submenu[''] as $index => $item ) {
					if ( isset( $item[2] ) && $slug === $item[2] ) {
						unset( $submenu[''][ $index ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- see above: the access check is done.
					}
				}
			}
		};
	}

	/**
	 * A user who can open no screen of a group sees no menu for it at all.
	 */
	public function hide_empty_menu(): void {
		$visible = $this->visible_groups( get_current_user_id() );
		foreach ( array_keys( self::GROUPS ) as $id ) {
			if ( ! in_array( $id, $visible, true ) ) {
				remove_menu_page( self::menu_slug( $id ) );
			}
		}
	}
}
