<?php
/**
 * The Purge log screen (REQ-209): read only, newest first, for holders of wiw_view_audit. It shows ids, counts and results.
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
			}
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
		$page   = max( 1, $request->int( 'paged' ) );
		$status = $request->string( 'status' );
		$from   = $this->day( $request->string( 'from' ) );
		$to     = $this->day( $request->string( 'to' ) );
		$status = in_array( $status, self::STATUSES, true ) ? $status : '';
		$rows   = $this->log->rows(
			self::PER_PAGE,
			( $page - 1 ) * self::PER_PAGE,
			array(
				'status' => $status,
				'from'   => $from,
				'to'     => $to,
			)
		);
		$out    = '<div class="wrap"><h1>' . esc_html__( 'Purge log', 'whoiswho' ) . '</h1>';
		$out   .= '<p>' . esc_html__( 'Every request to clear cached pages after content was unpublished, removed or declined. Without an edge cache the result is skipped.', 'whoiswho' ) . '</p>';
		$out   .= '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out   .= '<label>' . esc_html__( 'Status', 'whoiswho' ) . ' <select name="status"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
		foreach ( self::STATUSES as $option ) {
			$out .= '<option value="' . esc_attr( $option ) . '"' . selected( $status, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		$out .= '</select></label> <label>' . esc_html__( 'From', 'whoiswho' ) . ' <input type="date" name="from" value="' . esc_attr( $from ) . '" /></label> ';
		$out .= '<label>' . esc_html__( 'To', 'whoiswho' ) . ' <input type="date" name="to" value="' . esc_attr( $to ) . '" /></label> <button class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
		$out .= '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Time (UTC)', 'whoiswho' ) . '</th><th>' . esc_html__( 'Subject', 'whoiswho' ) . '</th><th>' . esc_html__( 'Reason', 'whoiswho' ) . '</th><th>' . esc_html__( 'URLs', 'whoiswho' ) . '</th><th>' . esc_html__( 'Status', 'whoiswho' ) . '</th></tr></thead><tbody>';
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="5">' . esc_html__( 'Nothing purged yet.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$out .= '<tr><td>' . esc_html( $row['created_at'] ) . '</td><td>' . esc_html( $row['subject_type'] . ' #' . $row['subject_id'] ) . '</td><td>' . esc_html( $row['reason'] ) . '</td><td>' . esc_html( (string) $row['url_count'] ) . '</td><td>' . esc_html( $row['result'] ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
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
