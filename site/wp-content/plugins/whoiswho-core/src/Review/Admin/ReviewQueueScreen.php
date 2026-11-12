<?php
/**
 * The staff Review queue screen and the dashboard review tiles (REQ-084, REQ-222, REQ-239, REQ-244). The screen needs
 * wiw_review_records; the tiles need wiw_view_dashboard_review. Assign to me is an audited admin action.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Admin;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

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
			}
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
		$profile_id = $request->int( 'profile_id' );
		$user_id    = get_current_user_id();
		if ( $profile_id > 0 && $user_id > 0 && null !== get_post( $profile_id ) ) {
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
		}
		wp_safe_redirect( add_query_arg( 'page', self::SCREEN, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			wp_die( esc_html__( 'You are not allowed to see this page.', 'whoiswho' ), '', array( 'response' => 403 ) );
		}
		$page   = max( 1, Request::from_globals()->int( 'paged' ) );
		$result = ( new ReviewQueue() )->items( $page );
		$kinds  = array(
			ReviewQueue::KIND_NEW        => __( 'New profile', 'whoiswho' ),
			ReviewQueue::KIND_CLAIM      => __( 'Claim', 'whoiswho' ),
			ReviewQueue::KIND_SUBMISSION => __( 'Customer submission', 'whoiswho' ),
			ReviewQueue::KIND_REVISION   => __( 'Pending revision', 'whoiswho' ),
			ReviewQueue::KIND_FLAGGED    => __( 'Flagged change', 'whoiswho' ),
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'Review queue', 'whoiswho' ) . '</h1>';
		/* translators: %d: number of items. */
		echo '<p>' . esc_html( sprintf( _n( '%d item to review', '%d items to review', $result['total'], 'whoiswho' ), $result['total'] ) ) . '</p>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Item', 'Kind', 'Type', 'State', 'Waiting since', 'Marks', 'Assigned', '' ) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( array() === $result['items'] ) {
			echo '<tr><td colspan="8">' . esc_html__( 'Nothing to review.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $result['items'] as $item ) {
			$edit  = get_edit_post_link( $item['profile_id'] );
			$marks = array_filter(
				array(
					$item['pilot'] ? __( 'Pilot', 'whoiswho' ) : '',
					$item['ai_drafted'] ? __( 'AI-drafted', 'whoiswho' ) : '',
					$item['edited'] ? __( 'Edited', 'whoiswho' ) : '',
				)
			);
			$who   = $item['assignee'] > 0 ? get_userdata( $item['assignee'] ) : false;
			echo '<tr><td>' . ( $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $item['name'] ) . '</a>' : esc_html( $item['name'] ) ) . '</td>';
			echo '<td>' . esc_html( $kinds[ $item['kind'] ] ?? $item['kind'] ) . '</td><td>' . esc_html( $item['type'] ) . '</td><td>' . esc_html( $item['state'] ) . '</td>';
			echo '<td>' . esc_html( $item['since'] ) . '</td><td>' . esc_html( implode( ', ', $marks ) ) . '</td>';
			echo '<td>' . esc_html( $who ? $who->display_name : '' ) . '</td><td>';
			$url = add_query_arg(
				array(
					'action'     => self::ASSIGN_ACT,
					'profile_id' => $item['profile_id'],
					'_wpnonce'   => ActionGuard::nonce_for( self::ASSIGN_ACT ),
				),
				admin_url( 'admin-post.php' )
			);
			echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Assign to me', 'whoiswho' ) . '</a></td></tr>';
		}//end foreach
		echo '</tbody></table>';
		if ( $result['pages'] > 1 ) {
			echo '<p class="wiw-pager">';
			for ( $i = 1; $i <= $result['pages']; $i++ ) {
				$url = add_query_arg(
					array(
						'page'  => self::SCREEN,
						'paged' => $i,
					),
					admin_url( 'admin.php' )
				);
				echo $i === $page ? '<strong>' . esc_html( (string) $i ) . '</strong> ' : '<a href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> ';
			}
			echo '</p>';
		}
		echo '</div>';
	}
}
