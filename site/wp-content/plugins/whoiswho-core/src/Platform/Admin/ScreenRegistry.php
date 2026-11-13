<?php
/**
 * The one way to add a screen to the WHOISWHO admin menu. Every screen needs a capability: there is no way to register one
 * without it. The menu follows the user's effective capabilities, and a direct URL without the capability is refused by
 * WordPress with a 403, because the page is registered with that capability.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Admin;

/**
 * Screen registry.
 */
final class ScreenRegistry {

	public const MENU_SLUG = 'wiw';

	/**
	 * The registry used by the plugin.
	 *
	 * @var ScreenRegistry|null
	 */
	private static ?ScreenRegistry $shared = null;

	/**
	 * Registered screens by slug.
	 *
	 * @var array<string, array{title: string, capability: string, callback: callable, hidden: bool}>
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
	 * Attach hooks.
	 */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_menu', array( $this, 'hide_empty_menu' ), 999 );
	}

	/**
	 * Register a screen.
	 *
	 * @param string   $slug       Unique slug (lowercase letters, digits, dashes).
	 * @param string   $title      Menu and page title.
	 * @param string   $capability A named capability, for example wiw_review_records. Required.
	 * @param callable $callback   Renders the screen.
	 * @param bool     $hidden     True for a page reached by a link (a detail screen): it has no menu item but is guarded the same way.
	 * @throws \InvalidArgumentException When the slug is invalid or taken, or the capability is empty.
	 */
	public function register( string $slug, string $title, string $capability, callable $callback, bool $hidden = false ): void {
		if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
			throw new \InvalidArgumentException( 'A screen slug is lowercase letters, digits and dashes.' );
		}
		if ( '' === trim( $capability ) ) {
			throw new \InvalidArgumentException( 'A screen cannot be registered without a capability.' );
		}
		if ( isset( $this->screens[ $slug ] ) ) {
			throw new \InvalidArgumentException( 'That screen slug is already registered.' );
		}
		$this->screens[ $slug ] = array(
			'title'      => $title,
			'capability' => $capability,
			'callback'   => $callback,
			'hidden'     => $hidden,
		);
	}

	/**
	 * Registered screens.
	 *
	 * @return array<string, array{title: string, capability: string, callback: callable, hidden: bool}>
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
	 * Add the top-level menu and one item per screen. Each item carries its own capability, so WordPress hides it from
	 * users without it and answers 403 to its URL.
	 */
	public function add_menu(): void {
		if ( array() === $this->screens ) {
			return;
		}
		add_menu_page( 'WHOISWHO', 'WHOISWHO', 'read', self::MENU_SLUG, '__return_null', 'dashicons-groups', 3 );
		foreach ( $this->screens as $slug => $screen ) {
			add_submenu_page( $screen['hidden'] ? null : self::MENU_SLUG, $screen['title'], $screen['title'], $screen['capability'], $slug, $screen['callback'] );
		}
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * A user who can open no screen sees no WHOISWHO menu at all.
	 */
	public function hide_empty_menu(): void {
		if ( array() === $this->visible_slugs( get_current_user_id() ) ) {
			remove_menu_page( self::MENU_SLUG );
		}
	}
}
