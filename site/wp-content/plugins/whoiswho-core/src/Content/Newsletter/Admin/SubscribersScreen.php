<?php
/**
 * The Subscribers screen (REQ-058, REQ-061, REQ-051): stories newsletter sign-ups with status filter, counts and dates, and a
 * logged CSV export. Read only: addresses are never edited here. Needs wiw_manage_subscribers; the export needs wiw_export_data
 * and every export is written to the audit log (without any address).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Newsletter\Admin;

use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Subscribers screen.
 */
final class SubscribersScreen {

	public const SCREEN        = 'wiw-subscribers';
	public const CAP           = 'wiw_manage_subscribers';
	public const EXPORT_ACTION = 'wiw_export_subscribers';
	public const EXPORT_CAP    = 'wiw_export_data';
	public const STATES        = array( 'confirmed', 'pending', 'unsubscribed' );
	public const EXPORT_LIMIT  = 100000;

	/**
	 * Repository.
	 *
	 * @var SubscriberRepository
	 */
	private SubscriberRepository $subscribers;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param SubscriberRepository $subscribers Repository.
	 * @param AuditLogger          $audit       Audit logger.
	 */
	public function __construct( SubscriberRepository $subscribers, AuditLogger $audit ) {
		$this->subscribers = $subscribers;
		$this->audit       = $audit;
	}

	/**
	 * Screen for this site.
	 */
	public static function for_site(): self {
		return new self( new SubscriberRepository(), AuditLogger::for_site() );
	}

	/**
	 * Register the screen and the export action, in the admin only.
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
					__( 'Subscribers', 'whoiswho' ),
					self::CAP,
					static function (): void {
						self::for_site()->render( Request::from_globals() );
					}
				);
				$registry->hook();
				ActionGuard::register(
					self::EXPORT_ACTION,
					self::EXPORT_CAP,
					static function ( Request $request ): void {
						self::for_site()->send_export( $request->string( 'state' ) );
					}
				);
			},
			1
		);
	}

	/**
	 * A valid state, confirmed by default.
	 *
	 * @param string $state Requested state.
	 */
	public static function state( string $state ): string {
		return in_array( $state, self::STATES, true ) ? $state : 'confirmed';
	}

	/**
	 * Number of rows in each state.
	 *
	 * @return array<string, int>
	 */
	public function counts(): array {
		global $wpdb;
		$table  = SubscriberRepository::table();
		$where  = array(
			'confirmed'    => 'confirmed_at IS NOT NULL AND revoked_at IS NULL',
			'pending'      => 'confirmed_at IS NULL AND revoked_at IS NULL',
			'unsubscribed' => 'revoked_at IS NOT NULL',
		);
		$counts = array();
		foreach ( $where as $state => $clause ) {
			$counts[ $state ] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table . ' WHERE purpose = %s AND ' . $clause, SubscriberRepository::PURPOSE ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name and fixed clauses.
		}
		return $counts;
	}

	/**
	 * The screen markup.
	 *
	 * @param string $state Status filter.
	 * @throws \DomainException When the user may not open the screen.
	 */
	public function html( string $state = 'confirmed' ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to view subscribers.' );
		}
		$state  = self::state( $state );
		$counts = $this->counts();
		$out    = '<div class="wrap"><h1>' . esc_html__( 'Subscribers', 'whoiswho' ) . '</h1><ul class="subsubsub">';
		$links  = array();
		foreach ( self::STATES as $key ) {
			$label   = ucfirst( $key ) . ' (' . $counts[ $key ] . ')';
			$links[] = '<li><a href="' . esc_url( add_query_arg( 'state', $key, admin_url( 'admin.php?page=' . self::SCREEN ) ) ) . '"' . ( $key === $state ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
		}
		$out .= implode( ' | ', $links ) . '</ul><table class="widefat striped"><thead><tr>';
		foreach ( array( 'Email', 'Consent given', 'Confirmed', 'Unsubscribed', 'Source' ) as $heading ) {
			$out .= '<th>' . esc_html( $heading ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		$rows = $this->subscribers->list( $state, 500 );
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="5">' . esc_html__( 'No subscribers in this list.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$out .= '<tr><td>' . esc_html( (string) $row['email'] ) . '</td><td>' . esc_html( (string) $row['granted_at'] ) . '</td><td>' . esc_html( (string) $row['confirmed_at'] ) . '</td><td>' . esc_html( (string) $row['revoked_at'] ) . '</td><td>' . esc_html( (string) $row['source'] ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
		if ( current_user_can( self::EXPORT_CAP ) ) {
			$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$out .= '<input type="hidden" name="action" value="' . esc_attr( self::EXPORT_ACTION ) . '" />';
			$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::EXPORT_ACTION ) ) . '" />';
			$out .= '<input type="hidden" name="state" value="' . esc_attr( $state ) . '" />';
			$out .= '<p><button type="submit" class="button">' . esc_html__( 'Export this list as CSV', 'whoiswho' ) . '</button></p></form>';
		}
		return $out . '</div>';
	}

	/**
	 * Build the CSV of one list and write the audit entry (state and row count only, never an address).
	 *
	 * @param string $state Status filter.
	 * @return string CSV text.
	 * @throws \DomainException When the user may not export.
	 */
	public function export_csv( string $state ): string {
		if ( ! current_user_can( self::EXPORT_CAP ) ) {
			throw new \DomainException( 'You are not allowed to export subscribers.' );
		}
		$state = self::state( $state );
		$rows  = $this->subscribers->list( $state, self::EXPORT_LIMIT );
		$out   = self::line( array( 'email', 'status', 'consent_given_at', 'confirmed_at', 'unsubscribed_at', 'source' ) );
		foreach ( $rows as $row ) {
			$out .= self::line(
				array(
					(string) $row['email'],
					$state,
					(string) $row['granted_at'],
					(string) $row['confirmed_at'],
					(string) $row['revoked_at'],
					(string) $row['source'],
				)
			);
		}
		$this->audit->log(
			'subscribers.export',
			array(
				'type' => 'newsletter_subscribers',
				'id'   => $state,
			),
			null,
			array( 'rows' => count( $rows ) )
		);
		return $out;
	}

	/**
	 * One CSV line. A cell that could run as a formula in a spreadsheet is prefixed with a quote.
	 *
	 * @param string[] $cells Cells.
	 */
	private static function line( array $cells ): string {
		$out = array();
		foreach ( $cells as $cell ) {
			if ( 1 === preg_match( '/^[=+\-@\t\r]/', $cell ) ) {
				$cell = "'" . $cell;
			}
			$out[] = '"' . str_replace( '"', '""', $cell ) . '"';
		}
		return implode( ',', $out ) . "\r\n";
	}

	/**
	 * Send the export as a download.
	 *
	 * @param string $state Status filter.
	 */
	public function send_export( string $state ): void {
		$csv = $this->export_csv( $state );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="newsletter-subscribers-' . self::state( $state ) . '.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, cells quoted.
		exit;
	}

	/**
	 * Print the screen.
	 *
	 * @param Request $request Request.
	 */
	public function render( Request $request ): void {
		echo wp_kses(
			$this->html( $request->string( 'state' ) ),
			array(
				'div'    => array( 'class' => true ),
				'h1'     => array(),
				'p'      => array(),
				'ul'     => array( 'class' => true ),
				'li'     => array(),
				'a'      => array(
					'href'  => true,
					'class' => true,
				),
				'table'  => array( 'class' => true ),
				'thead'  => array(),
				'tbody'  => array(),
				'tr'     => array(),
				'th'     => array(),
				'td'     => array( 'colspan' => true ),
				'form'   => array(
					'method' => true,
					'action' => true,
				),
				'input'  => array(
					'type'  => true,
					'name'  => true,
					'value' => true,
				),
				'button' => array(
					'type'  => true,
					'class' => true,
				),
			)
		);
	}
}
