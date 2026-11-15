<?php
/**
 * The bulk unpublish interface on the Profiles list (REQ-193, REQ-244): it acts on exactly the filter set the list shows, the
 * "Needs classification" view included, and its count is the list's count of published profiles. The live match count, a
 * mandatory reason, a confirmation that shows the count and the first five names, and a Recent bulk actions list with Restore
 * this batch. It plugs into the
 * `wiw_profiles_bulk_host` slot of the Profiles screen and is visible only with wiw_bulk_unpublish. The single-profile Unpublish
 * row action of the same screen is handled here too, with wiw_unpublish_profiles. The panel is a staff kit card; Recent bulk
 * actions is a read-only shared staff list table (BulkBatchesTable, REQ-275) whose one row action is Restore this batch.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Admin;

use WhoIsWho\Core\Directory\Admin\ProfilesQuery;
use WhoIsWho\Core\Directory\Admin\ProfilesScreen;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Review\Bulk\BulkFilter;
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
						self::finish( self::start( $request, get_current_user_id() ), self::filter_from( $request ) );
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
		return true === self::unpublish_id( $request->int( 'profile_id' ), $actor ) ? 'unpublished' : 'refused';
	}

	/**
	 * Unpublish one profile with the Profiles list's fixed reason, through the review service (which checks the capability and
	 * the state; the state machine writes the audit row). The row action and the list's bulk Unpublish selected (REQ-275) use it.
	 *
	 * @param int $profile_id Profile.
	 * @param int $actor      Acting user.
	 * @return true|\WP_Error
	 */
	public static function unpublish_id( int $profile_id, int $actor ) {
		try {
			( new ReviewService() )->unpublish( $profile_id, __( 'Unpublished from the Profiles list', 'whoiswho' ), $actor );
		} catch ( StateException $e ) {
			return new \WP_Error( 'wiw_unpublish_refused', $e->getMessage() );
		}
		return true;
	}

	/**
	 * Go back to the Profiles screen with a notice, on the same list view and filters when there were any.
	 *
	 * @param string                $notice  Notice code.
	 * @param array<string, string> $filters The clean filter the action used.
	 */
	private static function finish( string $notice, array $filters = array() ): void {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					$filters,
					array(
						'page'           => ProfilesScreen::SCREEN,
						self::NOTICE_VAR => $notice,
					)
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
		foreach ( BulkFilter::KEYS as $key ) {
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

		ob_start();
		$this->notice();
		$body = (string) ob_get_clean();
		/* translators: %d: number of published profiles matching the filter. */
		$body .= '<p class="wiw-bulk-count">' . esc_html( sprintf( _n( '%d published profile matches the filter.', '%d published profiles match the filter.', $count, 'whoiswho' ), $count ) ) . '</p>';
		// The list below shows profiles in every state; only the published ones among them can be unpublished.
		$listed = ( new ProfilesQuery() )->count( $clean );
		if ( $listed !== $count ) {
			/* translators: 1: number of profiles in the list, 2: how many of them are published. */
			$body .= '<p class="wiw-bulk-listed">' . esc_html( sprintf( _n( 'The list shows %1$d profile; %2$d of them are published, and only those are unpublished.', 'The list shows %1$d profiles; %2$d of them are published, and only those are unpublished.', $listed, 'whoiswho' ), $listed, $count ) ) . '</p>';
		}
		if ( $count > 0 ) {
			$hidden = '<input type="hidden" name="action" value="' . esc_attr( self::START_ACT ) . '" />'
				. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::START_ACT ) ) . '" />'
				. '<input type="hidden" name="confirmed" value="' . esc_attr( (string) $count ) . '" />';
			foreach ( $clean as $key => $value ) {
				$hidden .= '<input type="hidden" name="' . esc_attr( 'f_' . $key ) . '" value="' . esc_attr( $value ) . '" />';
			}
			$body .= Form::open( $hidden );
			/* translators: 1: number of profiles, 2: the first five names. */
			$body .= '<p class="wiw-bulk-confirmation">' . esc_html( sprintf( __( 'You are about to unpublish %1$d profiles, starting with: %2$s. Each will answer 410 Gone and leave search and the sitemap.', 'whoiswho' ), $count, implode( ', ', $names ) ) ) . '</p>';
			$body .= Form::text(
				'reason',
				__( 'Reason (required, no personal data)', 'whoiswho' ),
				'',
				array(
					'id'       => 'wiw-f-bulk-reason',
					'required' => true,
				)
			);
			$body .= Form::actions( '<button type="submit" class="button button-primary wiw-button--danger">' . esc_html__( 'Unpublish these profiles', 'whoiswho' ) . '</button>' ) . '</form>';
		}//end if
		$body .= '<h3 class="wiw-subhead">' . esc_html__( 'Recent bulk actions', 'whoiswho' ) . '</h3>' . ( new BulkBatchesTable() )->html( new Request( array() ) );
		$html  = Form::section(
			__( 'Bulk unpublish', 'whoiswho' ),
			$body,
			array(
				'id'          => 'wiw-bulk-unpublish',
				'class'       => 'wiw-bulk-unpublish',
				'description' => __( 'Unpublishes every published profile in the filtered list below, in the background. Restore a batch from Recent bulk actions.', 'whoiswho' ),
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
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
}
