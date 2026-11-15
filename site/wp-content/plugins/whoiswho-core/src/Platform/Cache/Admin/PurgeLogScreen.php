<?php
/**
 * The Purge log screen (REQ-209): read only, newest first, for holders of wiw_view_audit. It shows ids, counts and results. The
 * rows are a read-only staff list table (PurgeLogTable, REQ-275): no checkboxes, no bulk actions and no row actions.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache\Admin;

use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Cache\PurgeLog;
use WhoIsWho\Core\Platform\Cache\WpdbPurgeLog;

/**
 * Purge log screen.
 */
final class PurgeLogScreen {

	public const SCREEN   = 'wiw-purge-log';
	public const CAP      = 'wiw_view_audit';
	public const PER_PAGE = 50;
	public const STATUSES = array( 'ok', 'skipped', 'failed' );

	/**
	 * Log.
	 *
	 * @var PurgeLog
	 */
	private PurgeLog $log;

	/**
	 * Constructor.
	 *
	 * @param PurgeLog $log Purge log.
	 */
	public function __construct( PurgeLog $log ) {
		$this->log = $log;
	}

	/**
	 * Register in the admin only.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register_admin' ), 1 );
	}

	/**
	 * Register in the admin menu.
	 */
	public static function register_admin(): void {
		if ( ! is_admin() ) {
			return;
		}
		$registry = ScreenRegistry::shared();
		$registry->register(
			self::SCREEN,
			__( 'Purge log', 'whoiswho' ),
			self::CAP,
			static function (): void {
				echo ( new self( new WpdbPurgeLog() ) )->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			},
			false,
			'compliance'
		);
		$registry->hook();
	}

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request carrying the page.
	 * @throws \DomainException Without the capability.
	 */
	public function html( Request $request ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to use the purge log.' );
		}
		$status = $request->string( 'status' );
		$from   = $this->day( $request->string( 'from' ) );
		$to     = $this->day( $request->string( 'to' ) );
		$status = in_array( $status, self::STATUSES, true ) ? $status : '';
		$out    = '<div class="wrap wiw-staff wiw-purge-log"><h1>' . esc_html__( 'Purge log', 'whoiswho' ) . '</h1>';
		$out   .= '<p class="wiw-lead">' . esc_html__( 'Every request to clear cached pages after content was unpublished, removed or declined. Without an edge cache the result is skipped.', 'whoiswho' ) . '</p>';
		$out   .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="wiw-filters"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out   .= '<label>' . esc_html__( 'Status', 'whoiswho' ) . ' <select name="status"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
		foreach ( self::STATUSES as $option ) {
			$out .= '<option value="' . esc_attr( $option ) . '"' . selected( $status, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		$out  .= '</select></label> <label>' . esc_html__( 'From', 'whoiswho' ) . ' <input type="date" name="from" value="' . esc_attr( $from ) . '" /></label> ';
		$out  .= '<label>' . esc_html__( 'To', 'whoiswho' ) . ' <input type="date" name="to" value="' . esc_attr( $to ) . '" /></label> <button type="submit" class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
		$table = new PurgeLogTable(
			$this->log,
			array(
				'status' => $status,
				'from'   => $from,
				'to'     => $to,
			)
		);
		return $out . $table->html( $request ) . '</div>';
	}

	/**
	 * A date as Y-m-d, or an empty string.
	 *
	 * @param string $value Raw value.
	 */
	private function day( string $value ): string {
		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
}
