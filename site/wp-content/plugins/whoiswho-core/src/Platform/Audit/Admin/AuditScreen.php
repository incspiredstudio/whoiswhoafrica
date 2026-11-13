<?php
/**
 * The Audit log screen (REQ-051, REQ-188): newest first, paged, filtered by actor, action and date, read only. A "Verify chain"
 * action checks the hash chain and reports the first broken row. Viewing is not audited; verifying is. There is no edit or delete
 * control, and the capability is wiw_view_audit.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Audit\Admin;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditExport;
use WhoIsWho\Core\Platform\Audit\AuditGuard;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Audit screen.
 */
final class AuditScreen {

	public const SCREEN   = 'wiw-audit-log';
	public const CAP      = 'wiw_view_audit';
	public const ACTION   = 'wiw_audit_verify';
	public const PER_PAGE = 50;

	/**
	 * Logger (also used to record a verification).
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Table name without the prefix.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger $audit Logger.
	 * @param string      $table Table name without the prefix.
	 */
	public function __construct( AuditLogger $audit, string $table = 'wiw_audit_log' ) {
		$this->audit = $audit;
		$this->table = $table;
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
				$registry = ScreenRegistry::shared();
				$registry->register(
					self::SCREEN,
					__( 'Audit log', 'whoiswho' ),
					self::CAP,
					static function (): void {
						echo ( new self( AuditLogger::for_site() ) )->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					}
				);
				$registry->hook();
				ActionGuard::register( self::ACTION, self::CAP, array( self::class, 'handle_verify' ) );
			},
			1
		);
	}

	/**
	 * The verify handler (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_verify( Request $request ): void {
		unset( $request );
		$result = ( new self( AuditLogger::for_site() ) )->verify();
		$args   = array( 'page' => self::SCREEN );
		if ( $result['intact'] ) {
			$args['result'] = 'intact';
		} else {
			$args['result'] = 'broken';
			$args['row']    = (string) $result['broken_id'];
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Check the chain and record that it was checked.
	 *
	 * @return array{intact: bool, broken_id: int|null}
	 * @throws \DomainException Without the capability.
	 */
	public function verify(): array {
		$this->require_capability();
		$result = $this->audit->verify();
		$this->audit->log(
			'audit.verify',
			array(
				'type' => 'audit_log',
				'id'   => 'chain',
			),
			null,
			array(
				'intact'    => $result['intact'],
				'broken_id' => $result['broken_id'],
			)
		);
		return $result;
	}

	/**
	 * Rows, newest first.
	 *
	 * @param array{actor?: int, action?: string, from?: string, to?: string} $filters Filters.
	 * @param int                                                             $page    Page, from 1.
	 * @return array<int, array<string, mixed>>
	 * @throws \DomainException Without the capability.
	 */
	public function rows( array $filters, int $page = 1 ): array {
		global $wpdb;
		$this->require_capability();
		$sql  = 'SELECT id, at, actor_id, action, subject_type, subject_id, reason, batch_ref FROM ' . $wpdb->prefix . $this->table . ' WHERE 1 = 1';
		$args = array();
		if ( ! empty( $filters['actor'] ) ) {
			$sql   .= ' AND actor_id = %d';
			$args[] = (int) $filters['actor'];
		}
		if ( ! empty( $filters['action'] ) ) {
			$sql   .= ' AND action LIKE %s';
			$args[] = $wpdb->esc_like( (string) $filters['action'] ) . '%';
		}
		if ( ! empty( $filters['from'] ) ) {
			$sql   .= ' AND at >= %s';
			$args[] = $filters['from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['to'] ) ) {
			$sql   .= ' AND at <= %s';
			$args[] = $filters['to'] . ' 23:59:59';
		}
		$sql   .= ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$args[] = self::PER_PAGE;
		$args[] = max( 0, $page - 1 ) * self::PER_PAGE;
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name; values prepared.
	}

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request carrying the filters, page and the result of a verification.
	 */
	public function html( Request $request ): string {
		$this->require_capability();
		$filters = array(
			'actor'  => $request->int( 'actor' ),
			'action' => $request->string( 'action' ),
			'from'   => $this->day( $request->string( 'from' ) ),
			'to'     => $this->day( $request->string( 'to' ) ),
		);
		$rows    = $this->rows( $filters, max( 1, $request->int( 'paged' ) ) );

		$out  = '<div class="wrap"><h1>' . esc_html__( 'Audit log', 'whoiswho' ) . '</h1>';
		$out .= '<p>' . esc_html__( 'Every staff action that matters, newest first. Rows cannot be changed here.', 'whoiswho' ) . '</p>';
		$out .= $this->notice( $request );
		$last = AuditExport::last_run();
		/* translators: 1: on or off, 2: time of the last export, or never. */
		$out .= '<p>' . esc_html( sprintf( __( 'Database triggers: %1$s. Last export: %2$s.', 'whoiswho' ), AuditGuard::status(), $last > 0 ? gmdate( 'Y-m-d H:i', $last ) . ' UTC' : __( 'never', 'whoiswho' ) ) ) . '</p>';
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" /><button class="button">' . esc_html__( 'Verify chain', 'whoiswho' ) . '</button></form>';
		$out .= '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out .= '<label>' . esc_html__( 'Actor (user id)', 'whoiswho' ) . ' <input type="number" name="actor" value="' . esc_attr( $filters['actor'] > 0 ? (string) $filters['actor'] : '' ) . '" /></label> ';
		$out .= '<label>' . esc_html__( 'Action starts with', 'whoiswho' ) . ' <input type="text" name="action" value="' . esc_attr( $filters['action'] ) . '" /></label> ';
		$out .= '<label>' . esc_html__( 'From', 'whoiswho' ) . ' <input type="date" name="from" value="' . esc_attr( $filters['from'] ) . '" /></label> ';
		$out .= '<label>' . esc_html__( 'To', 'whoiswho' ) . ' <input type="date" name="to" value="' . esc_attr( $filters['to'] ) . '" /></label> ';
		$out .= '<button class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
		$out .= '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Time (UTC)', 'whoiswho' ), __( 'Actor', 'whoiswho' ), __( 'Action', 'whoiswho' ), __( 'Subject', 'whoiswho' ), __( 'Reason', 'whoiswho' ), __( 'Batch', 'whoiswho' ) ) as $heading ) {
			$out .= '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="6">' . esc_html__( 'Nothing logged yet.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$out .= '<tr><td>' . esc_html( (string) $row['at'] ) . '</td><td>' . esc_html( (string) $row['actor_id'] ) . '</td><td>' . esc_html( (string) $row['action'] ) . '</td><td>' . esc_html( $row['subject_type'] . ' ' . $row['subject_id'] ) . '</td><td>' . esc_html( (string) $row['reason'] ) . '</td><td>' . esc_html( (string) $row['batch_ref'] ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * The result of the last verification, from the query string.
	 *
	 * @param Request $request Request.
	 */
	private function notice( Request $request ): string {
		$result = $request->string( 'result' );
		if ( 'intact' === $result ) {
			return '<div class="notice notice-success"><p>' . esc_html__( 'The chain is intact.', 'whoiswho' ) . '</p></div>';
		}
		if ( 'broken' === $result ) {
			/* translators: %d: id of the first row that does not fit. */
			return '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'The chain is broken at row %d.', 'whoiswho' ), $request->int( 'row' ) ) ) . '</p></div>';
		}
		return '';
	}

	/**
	 * A date as Y-m-d, or an empty string.
	 *
	 * @param string $value Raw value.
	 */
	private function day( string $value ): string {
		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	/**
	 * Only holders of wiw_view_audit.
	 *
	 * @throws \DomainException Without it.
	 */
	private function require_capability(): void {
		if ( ! current_user_can( self::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to use the audit log.' );
		}
	}
}
