<?php
/**
 * The dashboard tile registry (REQ-239, REQ-235). A tile has an id, a title, the capability that shows it and a renderer. There
 * are exactly two tile types, a count and a list: no type offers a target, a goal or a progress bar, so a directory-size target
 * cannot be drawn. Which tiles a user sees follows their effective capabilities.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Dashboard;

/**
 * Tile registry.
 */
final class TileRegistry {

	public const COUNT = 'count';
	public const LIST  = 'list';

	/**
	 * The only tile types.
	 */
	public const TYPES = array( self::COUNT, self::LIST );

	/**
	 * Tiles by id.
	 *
	 * @var array<string, array{title: string, capability: string, type: string, renderer: callable}>
	 */
	private array $tiles = array();

	/**
	 * Register a tile.
	 *
	 * @param string   $id         Unique id (lowercase letters, digits, dashes, underscores).
	 * @param string   $title      Title shown on the tile.
	 * @param string   $capability The capability that shows it, for example wiw_view_dashboard_review. Required.
	 * @param string   $type       count or list.
	 * @param callable $renderer   count: fn(): int. list: fn(): array of short text lines.
	 * @throws \InvalidArgumentException For a bad id, a missing capability, an unknown type or a taken id.
	 */
	public function register( string $id, string $title, string $capability, string $type, callable $renderer ): void {
		if ( ! preg_match( '/^[a-z0-9_-]+$/', $id ) ) {
			throw new \InvalidArgumentException( 'A tile id is lowercase letters, digits, dashes and underscores.' );
		}
		if ( '' === trim( $capability ) ) {
			throw new \InvalidArgumentException( 'A tile cannot be registered without a capability.' );
		}
		if ( ! in_array( $type, self::TYPES, true ) ) {
			throw new \InvalidArgumentException( 'A tile is a count or a list: there is no target or progress tile.' );
		}
		if ( isset( $this->tiles[ $id ] ) ) {
			throw new \InvalidArgumentException( 'That tile id is already registered.' );
		}
		$this->tiles[ $id ] = array(
			'title'      => $title,
			'capability' => $capability,
			'type'       => $type,
			'renderer'   => $renderer,
		);
	}

	/**
	 * Every registered tile.
	 *
	 * @return array<string, array{title: string, capability: string, type: string, renderer: callable}>
	 */
	public function all(): array {
		return $this->tiles;
	}

	/**
	 * The tiles a user can see, by effective capability.
	 *
	 * @param int $user_id User.
	 * @return array<string, array{title: string, capability: string, type: string, renderer: callable}>
	 */
	public function visible_to( int $user_id ): array {
		$out = array();
		foreach ( $this->tiles as $id => $tile ) {
			if ( user_can( $user_id, $tile['capability'] ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- named wiw_* capability from config/capabilities.php.
				$out[ $id ] = $tile;
			}
		}
		return $out;
	}
}
