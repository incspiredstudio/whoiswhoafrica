<?php
/**
 * The staff dashboard (REQ-239, REQ-240, REQ-235): the tiles the user's capabilities show, as plain counts and short lists, on the
 * normal WordPress Dashboard (wp-admin/index.php), one dashboard widget per tile. A widget exists only for a user who holds the
 * tile's own named capability, so nobody without it sees it. Placeholder tiles give the Dashboard data until the owning modules
 * add real ones through the wiw_dashboard_tiles action, which receives the registry. Commercial tiles need
 * wiw_view_dashboard_commercial. The old WHOISWHO > Dashboard screen (wiw-dashboard) is gone: its URL redirects to the Dashboard.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Dashboard;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Dashboard widgets.
 */
final class DashboardScreen {

	public const SCREEN     = 'wiw-dashboard';
	public const CAPABILITY = 'wiw_view_dashboard_review';
	public const COMMERCIAL = 'wiw_view_dashboard_commercial';
	public const WIDGET     = 'wiw_tile_';

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
	 * Add the widgets to the WordPress Dashboard and redirect the old screen URL.
	 */
	public static function hooks(): void {
		add_action( 'wp_dashboard_setup', array( self::class, 'add_widgets' ) );
		add_action( 'admin_init', array( self::class, 'redirect_old_screen' ) );
	}

	/**
	 * One dashboard widget per tile the current user may see. A tile whose capability the user lacks gets no widget.
	 */
	public static function add_widgets(): void {
		$screen = new self();
		foreach ( $screen->tiles->visible_to( get_current_user_id() ) as $id => $tile ) {
			wp_add_dashboard_widget(
				self::WIDGET . $id,
				$tile['title'],
				static function () use ( $screen, $id ): void {
					echo $screen->tile_html( (string) $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
				}
			);
		}
	}

	/**
	 * Where the old WHOISWHO > Dashboard URL (admin.php?page=wiw-dashboard) goes for this user, or null for any other request or
	 * a user who never had access.
	 *
	 * @param Request $request Request.
	 * @param int     $user_id User.
	 */
	public static function old_screen_target( Request $request, int $user_id ): ?string {
		if ( self::SCREEN !== $request->string( 'page' ) ) {
			return null;
		}
		if ( ! user_can( $user_id, self::CAPABILITY ) && ! user_can( $user_id, self::COMMERCIAL ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return null;
		}
		return admin_url( 'index.php' );
	}

	/**
	 * Redirect the old screen URL to the Dashboard.
	 */
	public static function redirect_old_screen(): void {
		$target = self::old_screen_target( Request::from_globals(), get_current_user_id() );
		if ( null !== $target ) {
			wp_safe_redirect( $target );
			exit;
		}
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
	 * The markup of one tile for the current user: a count or a list. Empty when the tile is unknown or the user may not see it.
	 *
	 * @param string $id Tile id.
	 */
	public function tile_html( string $id ): string {
		$tile = $this->tiles->visible_to( get_current_user_id() )[ $id ] ?? null;
		if ( null === $tile ) {
			return '';
		}
		$value = ( $tile['renderer'] )();
		if ( TileRegistry::LIST === $tile['type'] ) {
			$out = '<ul>';
			foreach ( (array) $value as $line ) {
				$out .= '<li>' . esc_html( (string) $line ) . '</li>';
			}
			return $out . '</ul>';
		}
		return '<p class="wiw-count" style="font-size:2em;margin:0">' . esc_html( number_format_i18n( (int) $value ) ) . '</p>';
	}

	/**
	 * Every tile the current user may see, as one block of markup (used by the widgets' tests and by anything that lists them).
	 *
	 * @throws \DomainException Without the dashboard capability.
	 */
	public function html(): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to see the dashboard.' );
		}
		$out = '<div class="wiw-tiles">';
		foreach ( $this->tiles->visible_to( get_current_user_id() ) as $id => $tile ) {
			$out .= '<section class="card" style="max-width:none" data-tile="' . esc_attr( (string) $id ) . '"><h2>' . esc_html( $tile['title'] ) . '</h2>' . $this->tile_html( (string) $id ) . '</section>';
		}
		return $out . '</div>';
	}
}
