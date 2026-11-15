<?php
/**
 * The staff Review queue screen and the dashboard review tiles (REQ-084, REQ-222, REQ-239, REQ-244, REQ-275). The screen needs
 * wiw_review_records; the tiles need wiw_view_dashboard_review. Assign to me is an audited admin action. The queue is a shared
 * staff list table (ReviewQueueTable, REQ-275) with row quick actions and bulk actions.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Admin;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Review\Metrics\ReviewMetrics;

/**
 * Review queue screen.
 */
final class ReviewQueueScreen {

	public const SCREEN     = 'wiw-review';
	public const CAPABILITY = 'wiw_review_records';
	public const TILES_CAP  = 'wiw_view_dashboard_review';
	public const ASSIGN_ACT = 'wiw_review_assign';

	/**
	 * Register the screen.
	 *
	 * @param ScreenRegistry|null $registry Registry; the shared one by default.
	 */
	public static function register( ?ScreenRegistry $registry = null ): void {
		( $registry ?? ScreenRegistry::shared() )->register(
			self::SCREEN,
			__( 'Review queue', 'whoiswho' ),
			self::CAPABILITY,
			static function (): void {
				( new self() )->render();
			},
			false,
			'review'
		);
	}

	/**
	 * Hook registration into the admin.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				self::register();
				ScreenRegistry::shared()->hook();
				ActionGuard::register( self::ASSIGN_ACT, self::CAPABILITY, array( self::class, 'handle_assign' ) );
				ReviewQueueTable::register_bulk_handler();
			},
			1
		);
		add_action( 'wp_dashboard_setup', array( self::class, 'add_dashboard_widget' ) );
	}

	/**
	 * Add the tiles to the WordPress dashboard for a user who holds the dashboard capability.
	 */
	public static function add_dashboard_widget(): void {
		if ( ! current_user_can( self::TILES_CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return;
		}
		wp_add_dashboard_widget( 'wiw_review_tiles', __( 'Review', 'whoiswho' ), array( new self(), 'tiles' ) );
	}

	/**
	 * Print the four counts.
	 */
	public function tiles(): void {
		if ( ! current_user_can( self::TILES_CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return;
		}
		$labels = array(
			'imported'  => __( 'Imported', 'whoiswho' ),
			'pending'   => __( 'Pending review', 'whoiswho' ),
			'reviewed'  => __( 'Reviewed', 'whoiswho' ),
			'published' => __( 'Published', 'whoiswho' ),
		);
		echo '<ul class="wiw-review-tiles">';
		foreach ( ( new ReviewQueue() )->tiles() as $key => $count ) {
			echo '<li data-tile="' . esc_attr( $key ) . '"><strong>' . esc_html( (string) $count ) . '</strong> ' . esc_html( $labels[ $key ] ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Handle "assign to me".
	 *
	 * @param Request $request Request.
	 */
	public static function handle_assign( Request $request ): void {
		self::assign_to_current_user( $request->int( 'profile_id' ) );
		wp_safe_redirect( add_query_arg( 'page', self::SCREEN, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Assign a queue item's profile to the current user, with an audit row. The single action and the bulk action both use it.
	 *
	 * @param int $profile_id Profile.
	 * @return bool Whether it was assigned.
	 */
	public static function assign_to_current_user( int $profile_id ): bool {
		$user_id = get_current_user_id();
		if ( $profile_id <= 0 || $user_id <= 0 || null === get_post( $profile_id ) || ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return false;
		}
		( new ReviewQueue() )->assign( $profile_id, $user_id );
		AuditLogger::for_site()->log(
			'review.assign',
			array(
				'type' => 'profile',
				'id'   => $profile_id,
			),
			null,
			array( 'assignee' => $user_id )
		);
		return true;
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		echo $this->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
	}

	/**
	 * The screen markup: the count, the review metrics and the queue table (REQ-275).
	 *
	 * @param Request $request Request.
	 */
	public function html( Request $request ): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			wp_die( esc_html__( 'You are not allowed to see this page.', 'whoiswho' ), '', array( 'response' => 403 ) );
		}
		$total = (int) ( new ReviewQueue() )->items( 1, 1 )['total'];
		$out   = '<div class="wrap wiw-staff"><h1>' . esc_html__( 'Review queue', 'whoiswho' ) . '</h1>';
		/* translators: %d: number of items. */
		$out .= '<p class="wiw-lead">' . esc_html( sprintf( _n( '%d item to review', '%d items to review', $total, 'whoiswho' ), $total ) ) . '</p>';
		$out .= ( new ReviewMetrics() )->html();
		return $out . ( new ReviewQueueTable() )->html( $request ) . '</div>';
	}
}
