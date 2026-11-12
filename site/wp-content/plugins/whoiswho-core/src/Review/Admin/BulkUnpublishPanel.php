<?php
/**
 * The bulk unpublish interface on the Profiles list (REQ-193, REQ-244): the live match count, a mandatory reason, a confirmation
 * that shows the count and the first five names, and a Recent bulk actions list with Restore this batch. It plugs into the
 * `wiw_profiles_bulk_host` slot of the Profiles screen and is visible only with wiw_bulk_unpublish. The single-profile Unpublish
 * row action of the same screen is handled here too, with wiw_unpublish_profiles.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Admin;

use WhoIsWho\Core\Directory\Admin\ProfilesScreen;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Review\Bulk\BulkFilter;
use WhoIsWho\Core\Review\Bulk\BulkRepository;
use WhoIsWho\Core\Review\Bulk\BulkUnpublish;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateException;

/**
 * Bulk unpublish panel and actions.
 */
final class BulkUnpublishPanel {

	public const START_ACT   = 'wiw_bulk_unpublish_start';
	public const RESTORE_ACT = 'wiw_bulk_unpublish_restore';
	public const NOTICE_VAR  = 'wiw_bulk';

	/**
	 * Hook the panel into the Profiles screen and register the actions.
	 */
	public static function hooks(): void {
		add_action( 'wiw_profiles_bulk_host', array( new self(), 'render' ), 10, 1 );
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				ActionGuard::register(
					self::START_ACT,
					BulkUnpublish::CAPABILITY,
					static function ( Request $request ): void {
						self::finish( self::start( $request, get_current_user_id() ) );
					}
				);
				ActionGuard::register(
					self::RESTORE_ACT,
					BulkUnpublish::CAPABILITY,
					static function ( Request $request ): void {
						self::finish( self::restore( $request, get_current_user_id() ) );
					}
				);
				ActionGuard::register(
					ProfilesScreen::UNPUBLISH_ACT,
					ProfilesScreen::UNPUBLISH_CAP,
					static function ( Request $request ): void {
						self::finish( self::unpublish_one( $request, get_current_user_id() ) );
					}
				);
			},
			2
		);
	}

	/**
	 * Start a bulk unpublish from a confirmed form. Returns a notice code: started or refused.
	 *
	 * @param Request $request Request: the filter keys, reason and confirmed.
	 * @param int     $actor   Acting user.
	 */
	public static function start( Request $request, int $actor ): string {
		try {
			BulkUnpublish::for_site()->start( self::filter_from( $request ), $request->string( 'reason' ), $request->int( 'confirmed' ), $actor );
		} catch ( StateException $e ) {
			return 'refused';
		}
		return 'started';
	}

	/**
	 * Restore one batch. Returns a notice code: restored or refused.
	 *
	 * @param Request $request Request: batch.
	 * @param int     $actor   Acting user.
	 */
	public static function restore( Request $request, int $actor ): string {
		try {
			BulkUnpublish::for_site()->restore( $request->string( 'batch' ), $actor );
		} catch ( StateException $e ) {
			return 'refused';
		}
		return 'restored';
	}

	/**
	 * Unpublish one profile from the row action. The reason is a fixed line because the row link carries none; a profile that needs
	 * a particular reason is unpublished from its own review screen.
	 *
	 * @param Request $request Request: profile_id.
	 * @param int     $actor   Acting user.
	 */
	public static function unpublish_one( Request $request, int $actor ): string {
		try {
			( new ReviewService() )->unpublish( $request->int( 'profile_id' ), __( 'Unpublished from the Profiles list', 'whoiswho' ), $actor );
		} catch ( StateException $e ) {
			return 'refused';
		}
		return 'unpublished';
	}

	/**
	 * Go back to the Profiles screen with a notice.
	 *
	 * @param string $notice Notice code.
	 */
	private static function finish( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => ProfilesScreen::SCREEN,
					self::NOTICE_VAR => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * The filter keys of a request.
	 *
	 * @param Request $request Request.
	 * @return array<string, string>
	 */
	private static function filter_from( Request $request ): array {
		$raw = array();
		foreach ( array( 'source', 'batch', 'type', 'state', 'origin', 'name' ) as $key ) {
			$raw[ $key ] = $request->string( 'f_' . $key );
		}
		return BulkFilter::clean( $raw );
	}

	/**
	 * Print the panel for the current filter. Prints nothing without wiw_bulk_unpublish.
	 *
	 * @param array<string, string> $filters The Profiles screen's sanitised filters.
	 */
	public function render( array $filters ): void {
		if ( ! current_user_can( BulkUnpublish::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return;
		}
		$service = BulkUnpublish::for_site();
		$clean   = BulkFilter::clean( $filters );
		$count   = $service->count( $clean );
		$names   = $service->first_names( $clean, 5 );

		echo '<div class="wiw-bulk-unpublish"><h2>' . esc_html__( 'Bulk unpublish', 'whoiswho' ) . '</h2>';
		$this->notice();
		/* translators: %d: number of published profiles matching the filter. */
		echo '<p class="wiw-bulk-count">' . esc_html( sprintf( _n( '%d published profile matches the filter.', '%d published profiles match the filter.', $count, 'whoiswho' ), $count ) ) . '</p>';
		if ( $count > 0 ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::START_ACT ) . '" />';
			echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::START_ACT ) ) . '" />';
			echo '<input type="hidden" name="confirmed" value="' . esc_attr( (string) $count ) . '" />';
			foreach ( $clean as $key => $value ) {
				echo '<input type="hidden" name="' . esc_attr( 'f_' . $key ) . '" value="' . esc_attr( $value ) . '" />';
			}
			echo '<p class="wiw-bulk-confirmation">';
			/* translators: 1: number of profiles, 2: the first five names. */
			echo esc_html( sprintf( __( 'You are about to unpublish %1$d profiles, starting with: %2$s. Each will answer 410 Gone and leave search and the sitemap.', 'whoiswho' ), $count, implode( ', ', $names ) ) );
			echo '</p>';
			echo '<p><label>' . esc_html__( 'Reason (required, no personal data)', 'whoiswho' ) . ' <input type="text" name="reason" required="required" class="regular-text" /></label> ';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Unpublish these profiles', 'whoiswho' ) . '</button></p></form>';
		}
		$this->recent();
		echo '</div>';
	}

	/**
	 * The notice from the last action.
	 */
	private function notice(): void {
		$code     = Request::from_globals()->string( self::NOTICE_VAR );
		$messages = array(
			'started'     => __( 'Bulk unpublish started. Profiles are unpublished in the background.', 'whoiswho' ),
			'restored'    => __( 'Restore started. Profiles are restored in the background.', 'whoiswho' ),
			'unpublished' => __( 'Profile unpublished.', 'whoiswho' ),
			'refused'     => __( 'That was not done: check the reason and that the match count has not changed.', 'whoiswho' ),
		);
		if ( isset( $messages[ $code ] ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html( $messages[ $code ] ) . '</p></div>';
		}
	}

	/**
	 * Recent bulk actions with Restore this batch.
	 */
	private function recent(): void {
		$rows = ( new BulkRepository() )->recent( 10 );
		echo '<h3>' . esc_html__( 'Recent bulk actions', 'whoiswho' ) . '</h3>';
		if ( array() === $rows ) {
			echo '<p>' . esc_html__( 'No bulk actions yet.', 'whoiswho' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'When', 'Reason', 'Profiles', 'Status', '' ) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( (string) $row['created_at'] ) . '</td><td>' . esc_html( (string) $row['reason'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['match_count'] ) . '</td><td>' . esc_html( (string) $row['status'] ) . '</td><td>';
			if ( in_array( $row['status'], array( BulkRepository::DONE, BulkRepository::RUNNING ), true ) ) {
				$url = add_query_arg(
					array(
						'action'   => self::RESTORE_ACT,
						'batch'    => (string) $row['batch_ref'],
						'_wpnonce' => ActionGuard::nonce_for( self::RESTORE_ACT ),
					),
					admin_url( 'admin-post.php' )
				);
				echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Restore this batch', 'whoiswho' ) . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
