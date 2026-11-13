<?php
/**
 * The Dashboard screen (REQ-239, REQ-240, REQ-235): the tiles the user's capabilities show, as plain counts and short lists.
 * Registered under wiw_view_dashboard_review. Placeholder tiles give the screen data until the owning modules add real ones
 * through the wiw_dashboard_tiles action, which receives the registry. Commercial tiles need wiw_view_dashboard_commercial.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Dashboard;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;

/**
 * Dashboard screen.
 */
final class DashboardScreen {

	public const SCREEN     = 'wiw-dashboard';
	public const CAPABILITY = 'wiw_view_dashboard_review';
	public const COMMERCIAL = 'wiw_view_dashboard_commercial';

	/**
	 * Tile registry.
	 *
	 * @var TileRegistry
	 */
	private TileRegistry $tiles;

	/**
	 * Constructor.
	 *
	 * @param TileRegistry|null $tiles Registry; null builds the default one with the placeholder tiles.
	 */
	public function __construct( ?TileRegistry $tiles = null ) {
		$this->tiles = $tiles ?? self::default_tiles();
	}

	/**
	 * Register the screen in the admin.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				$registry = ScreenRegistry::shared();
				$registry->register(
					self::SCREEN,
					__( 'Dashboard', 'whoiswho' ),
					self::CAPABILITY,
					static function (): void {
						echo ( new self() )->html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					}
				);
				$registry->hook();
			},
			1
		);
	}

	/**
	 * The default registry: review tiles for everyone with the dashboard capability, a commercial placeholder for those with the
	 * commercial one, then whatever other modules add through the wiw_dashboard_tiles action.
	 */
	public static function default_tiles(): TileRegistry {
		$tiles = new TileRegistry();
		foreach ( array(
			PubState::PUBLISHED_PENDING   => __( 'Published, waiting for review', 'whoiswho' ),
			PubState::IMPORTED_NOT_PUBLIC => __( 'Imported, not public', 'whoiswho' ),
			PubState::PUBLISHED_REVIEWED  => __( 'Published and reviewed', 'whoiswho' ),
		) as $state => $title ) {
			$tiles->register(
				'profiles_' . $state,
				$title,
				self::CAPABILITY,
				TileRegistry::COUNT,
				static fn (): int => ( new StateRepository() )->count( array( StateRepository::STATE_COLUMN => $state ) )
			);
		}
		$tiles->register( 'commercial_placeholder', __( 'Commercial figures', 'whoiswho' ), self::COMMERCIAL, TileRegistry::COUNT, static fn (): int => 0 );
		do_action( 'wiw_dashboard_tiles', $tiles );
		return $tiles;
	}

	/**
	 * The screen markup for the current user.
	 *
	 * @throws \DomainException Without the dashboard capability.
	 */
	public function html(): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to see the dashboard.' );
		}
		$out = '<div class="wrap"><h1>' . esc_html__( 'Dashboard', 'whoiswho' ) . '</h1><div class="wiw-tiles">';
		foreach ( $this->tiles->visible_to( get_current_user_id() ) as $id => $tile ) {
			$out  .= '<section class="card" style="max-width:none" data-tile="' . esc_attr( $id ) . '"><h2>' . esc_html( $tile['title'] ) . '</h2>';
			$value = ( $tile['renderer'] )();
			if ( TileRegistry::LIST === $tile['type'] ) {
				$out .= '<ul>';
				foreach ( (array) $value as $line ) {
					$out .= '<li>' . esc_html( (string) $line ) . '</li>';
				}
				$out .= '</ul>';
			} else {
				$out .= '<p class="wiw-count" style="font-size:2em;margin:0">' . esc_html( number_format_i18n( (int) $value ) ) . '</p>';
			}
			$out .= '</section>';
		}
		return $out . '</div></div>';
	}
}
