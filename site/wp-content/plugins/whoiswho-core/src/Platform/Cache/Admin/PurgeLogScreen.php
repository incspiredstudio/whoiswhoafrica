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
		$page = max( 1, $request->int( 'paged' ) );
		$rows = $this->log->rows( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );
		$out  = '<div class="wrap"><h1>' . esc_html__( 'Purge log', 'whoiswho' ) . '</h1>';
		$out .= '<p>' . esc_html__( 'Every request to clear cached pages after content was unpublished. Without an edge cache the result is skipped.', 'whoiswho' ) . '</p>';
		$out .= '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Time (UTC)', 'whoiswho' ) . '</th><th>' . esc_html__( 'Subject', 'whoiswho' ) . '</th><th>' . esc_html__( 'URLs', 'whoiswho' ) . '</th><th>' . esc_html__( 'Result', 'whoiswho' ) . '</th></tr></thead><tbody>';
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="4">' . esc_html__( 'Nothing purged yet.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$out .= '<tr><td>' . esc_html( $row['created_at'] ) . '</td><td>' . esc_html( $row['subject_type'] . ' #' . $row['subject_id'] ) . '</td><td>' . esc_html( (string) $row['url_count'] ) . '</td><td>' . esc_html( $row['result'] ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}
}
