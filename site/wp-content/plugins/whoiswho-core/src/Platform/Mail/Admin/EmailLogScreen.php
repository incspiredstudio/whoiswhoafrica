<?php
/**
 * The Email log screen (REQ-075, REQ-051): every send, bounce and complaint with time, event, result and a short reference of
 * the recipient. It shows no message body and no address (the log stores only a keyed hash). An Administrator can record a
 * bounce or complaint found by hand against a row; that adds a log event and an audit row.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail\Admin;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;

/**
 * Email log screen.
 */
final class EmailLogScreen {

	public const SCREEN   = 'wiw-email-log';
	public const CAP      = 'wiw_view_audit';
	public const ACTION   = 'wiw_email_record';
	public const PER_PAGE = 50;
	public const KINDS    = array( 'bounce', 'complaint' );

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Log store the recorded events are added to.
	 *
	 * @var EmailLogStore
	 */
	private EmailLogStore $log;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger   $audit Audit logger.
	 * @param EmailLogStore $log   Email log store.
	 */
	public function __construct( AuditLogger $audit, EmailLogStore $log ) {
		$this->audit = $audit;
		$this->log   = $log;
	}

	/**
	 * Screen for this site.
	 */
	public static function for_site(): self {
		return new self( AuditLogger::for_site(), new \WhoIsWho\Core\Platform\Mail\WpdbEmailLog() );
	}

	/**
	 * Register the screen and the record action, in the admin only.
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
			__( 'Email log', 'whoiswho' ),
			self::CAP,
			static function (): void {
				echo self::for_site()->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			},
			false,
			'audience'
		);
		$registry->hook();
		ActionGuard::register(
			self::ACTION,
			self::CAP,
			static function ( Request $request ): void {
				self::for_site()->handle( $request );
			}
		);
	}

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wiw_email_events';
	}

	/**
	 * Rows, newest first.
	 *
	 * @param string $event  Event filter, or empty.
	 * @param string $result Result filter, or empty.
	 * @param int    $page   Page, from 1.
	 * @return array<int, array<string, mixed>>
	 * @throws \DomainException Without the capability.
	 */
	public function rows( string $event = '', string $result = '', int $page = 1 ): array {
		global $wpdb;
		$this->require_capability();
		$sql  = 'SELECT id, message_id, event, recipient_ref, type, result, created_at FROM ' . self::table() . ' WHERE 1 = 1';
		$args = array();
		if ( '' !== $event ) {
			$sql   .= ' AND event = %s';
			$args[] = $event;
		}
		if ( '' !== $result ) {
			$sql   .= ' AND result = %s';
			$args[] = $result;
		}
		$sql   .= ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$args[] = self::PER_PAGE;
		$args[] = max( 0, $page - 1 ) * self::PER_PAGE;
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name; values prepared.
	}

	/**
	 * Record a bounce or complaint found by hand against a row.
	 *
	 * @param int    $id   Log row id.
	 * @param string $kind bounce or complaint.
	 * @throws \InvalidArgumentException For an unknown kind or row (a \DomainException without the capability).
	 */
	public function record( int $id, string $kind ): void {
		global $wpdb;
		$this->require_capability();
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			throw new \InvalidArgumentException( 'Record a bounce or a complaint.' );
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT message_id, event, recipient_ref FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		if ( ! is_array( $row ) ) {
			throw new \InvalidArgumentException( 'That log row does not exist.' );
		}
		$this->log->add( (string) $row['message_id'], (string) $row['event'], (string) $row['recipient_ref'], $kind, 'recorded' );
		$this->audit->log(
			'email.record_' . $kind,
			array(
				'type' => 'email_event',
				'id'   => $id,
			),
			null,
			array(
				'event' => (string) $row['event'],
				'kind'  => $kind,
			)
		);
	}

	/**
	 * The admin-post handler (capability and nonce already checked by ActionGuard).
	 *
	 * @param Request $request Request.
	 */
	public function handle( Request $request ): void {
		try {
			$this->record( $request->int( 'row' ), $request->string( 'kind' ) );
		} catch ( \InvalidArgumentException $e ) {
			unset( $e );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SCREEN ) );
		exit;
	}

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request carrying the filters and page.
	 */
	public function html( Request $request ): string {
		$event  = $request->string( 'event' );
		$result = $request->string( 'result' );
		$rows   = $this->rows( $event, $result, max( 1, $request->int( 'paged' ) ) );

		$out  = '<div class="wrap"><h1>' . esc_html__( 'Email log', 'whoiswho' ) . '</h1>';
		$out .= '<p>' . esc_html__( 'Every message the system sent, with bounces and complaints. Recipients appear as a short reference; no address and no message text is shown.', 'whoiswho' ) . '</p>';
		$out .= '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out .= '<label>' . esc_html__( 'Event', 'whoiswho' ) . ' <input type="text" name="event" value="' . esc_attr( $event ) . '" /></label> ';
		$out .= '<label>' . esc_html__( 'Result', 'whoiswho' ) . ' <select name="result"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
		foreach ( array( 'queued', 'sent', 'failed', 'recorded' ) as $option ) {
			$out .= '<option value="' . esc_attr( $option ) . '"' . selected( $result, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		$out .= '</select></label> <button class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
		$out .= '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Time (UTC)', 'whoiswho' ) . '</th><th>' . esc_html__( 'Event', 'whoiswho' ) . '</th><th>' . esc_html__( 'Recipient', 'whoiswho' ) . '</th><th>' . esc_html__( 'Type', 'whoiswho' ) . '</th><th>' . esc_html__( 'Result', 'whoiswho' ) . '</th><th>' . esc_html__( 'Record', 'whoiswho' ) . '</th></tr></thead><tbody>';
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="6">' . esc_html__( 'Nothing logged yet.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$failed = 'failed' === $row['result'];
			$out   .= '<tr' . ( $failed ? ' class="wiw-failed" style="background:#fcf0f1"' : '' ) . '>';
			$out   .= '<td>' . esc_html( (string) $row['created_at'] ) . '</td>';
			$out   .= '<td>' . esc_html( (string) $row['event'] ) . '</td>';
			$out   .= '<td><code>' . esc_html( substr( (string) $row['recipient_ref'], 0, 10 ) ) . '</code></td>';
			$out   .= '<td>' . esc_html( (string) $row['type'] ) . '</td>';
			$out   .= '<td>' . ( $failed ? '<strong>' . esc_html__( 'Failed', 'whoiswho' ) . '</strong>' : esc_html( (string) $row['result'] ) ) . '</td>';
			$out   .= '<td>' . $this->record_forms( (int) $row['id'] ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * The two small forms that record a bounce or a complaint on one row.
	 *
	 * @param int $id Row id.
	 */
	private function record_forms( int $id ): string {
		$out = '';
		foreach ( self::KINDS as $kind ) {
			$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
			$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
			$out .= '<input type="hidden" name="row" value="' . esc_attr( (string) $id ) . '" />';
			$out .= '<input type="hidden" name="kind" value="' . esc_attr( $kind ) . '" />';
			$out .= '<button class="button-link">' . esc_html( 'bounce' === $kind ? __( 'Record bounce', 'whoiswho' ) : __( 'Record complaint', 'whoiswho' ) ) . '</button></form> ';
		}
		return $out;
	}

	/**
	 * Only holders of the capability may read or record.
	 *
	 * @throws \DomainException Without it.
	 */
	private function require_capability(): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to use the email log.' );
		}
	}
}
