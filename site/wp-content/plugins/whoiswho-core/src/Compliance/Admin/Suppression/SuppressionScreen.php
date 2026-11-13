<?php
/**
 * The Suppression list screen (REQ-207, REQ-147, REQ-244; prototype A22): the suppressed names with type, how the entry arose, who
 * added it and when, and the reason (staff only). Viewing needs wiw_view_suppression (Reviewer, Editor, Administrator). Adding or
 * removing an entry by hand needs wiw_edit_suppression (Administrator) and, for a removal, a reason; both are audited by the
 * Suppression service. The form controls are not printed for a user without wiw_edit_suppression.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Admin\Suppression;

use WhoIsWho\Core\Compliance\Suppression\SnapshotExport;
use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Suppression screen.
 */
final class SuppressionScreen {

	public const SCREEN     = 'wiw-suppression';
	public const CAPABILITY = 'wiw_view_suppression';
	public const EDIT_CAP   = Suppression::EDIT_CAP;
	public const ADD        = 'wiw_suppression_add';
	public const REMOVE     = 'wiw_suppression_remove';

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
					__( 'Suppression list', 'whoiswho' ),
					self::CAPABILITY,
					static function (): void {
						echo ( new self() )->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					}
				);
				$registry->hook();
				ActionGuard::register( self::ADD, self::EDIT_CAP, array( self::class, 'handle_add' ) );
				ActionGuard::register( self::REMOVE, self::EDIT_CAP, array( self::class, 'handle_remove' ) );
			},
			1
		);
	}

	/**
	 * Add handler (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_add( Request $request ): void {
		$type = $request->string( 'entity_type' );
		$name = trim( $request->string( 'name' ) );
		if ( '' === $name || '' === trim( $request->string( 'reason' ) ) || ! in_array( $type, StateRepository::ENTITY_TYPES, true ) ) {
			self::back( 'refused', 'A name, a type and a reason are required.' );
		}
		( new Suppression( AuditLogger::for_site() ) )->add_manual( $name, $type, array(), $request->string( 'reason' ) );
		self::back( 'added' );
	}

	/**
	 * Remove handler (capability and nonce already checked): a reason is required.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_remove( Request $request ): void {
		$reason = trim( $request->string( 'reason' ) );
		if ( '' === $reason ) {
			self::back( 'refused', 'A reason is required to remove an entry.' );
		}
		( new Suppression( AuditLogger::for_site() ) )->remove_manual( $request->int( 'id' ), $reason );
		self::back( 'removed' );
	}

	/**
	 * Redirect back to the screen.
	 *
	 * @param string $result Result word.
	 * @param string $why    A refusal message fit to show.
	 */
	private static function back( string $result, string $why = '' ): void {
		$url = add_query_arg(
			array(
				'page'   => self::SCREEN,
				'result' => $result,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( '' === $why ? $url : add_query_arg( 'why', rawurlencode( $why ), $url ) );
		exit;
	}

	/**
	 * The entries, newest first, for display.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function entries(): array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Suppression::table() . ' ORDER BY id DESC LIMIT 500', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request (result, why).
	 * @throws \DomainException Without the capability.
	 */
	public function html( Request $request ): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to view the suppression list.' );
		}
		$can_edit = current_user_can( self::EDIT_CAP ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
		$out      = '<div class="wrap"><h1>' . esc_html__( 'Suppression list', 'whoiswho' ) . '</h1>' . $this->notice( $request );
		$out     .= '<p>' . esc_html__( 'Names that are never imported or published. The reason is for staff only.', 'whoiswho' ) . '</p>';
		if ( current_user_can( SnapshotExport::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			$out .= '<p><a class="button" href="' . esc_url( SnapshotExport::url() ) . '">' . esc_html__( 'Download snapshot for acquisition', 'whoiswho' ) . '</a> ' . esc_html__( 'The file contains personal data: keep it out of shared folders and delete it after the run.', 'whoiswho' ) . '</p>';
		}
		if ( $can_edit ) {
			$out .= $this->add_form();
		}
		$out .= '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Name', 'Type', 'How added', 'Added by', 'Added on', 'Reason' ) as $heading ) {
			$out .= '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		$out .= $can_edit ? '<th scope="col"></th>' : '';
		$out .= '</tr></thead><tbody>';
		$rows = $this->entries();
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="' . ( $can_edit ? '7' : '6' ) . '">' . esc_html__( 'No names are suppressed.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$by   = (int) $row['added_by'] > 0 ? get_the_author_meta( 'display_name', (int) $row['added_by'] ) : __( 'System', 'whoiswho' );
			$out .= '<tr><th scope="row">' . esc_html( (string) $row['name'] ) . '</th><td>' . esc_html( (string) $row['entity_type'] ) . '</td><td>' . esc_html( (string) $row['source'] ) . '</td><td>' . esc_html( (string) $by ) . '</td><td>' . esc_html( (string) $row['created_at'] ) . '</td><td>' . esc_html( (string) $row['reason'] ) . '</td>';
			$out .= $can_edit ? '<td>' . $this->remove_form( (int) $row['id'] ) . '</td>' : '';
			$out .= '</tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * The result notice.
	 *
	 * @param Request $request Request.
	 */
	private function notice( Request $request ): string {
		$result = $request->string( 'result' );
		if ( 'refused' === $result ) {
			return '<div class="notice notice-error"><p>' . esc_html( rawurldecode( $request->string( 'why' ) ) ) . '</p></div>';
		}
		$words = array(
			'added'   => __( 'Entry added.', 'whoiswho' ),
			'removed' => __( 'Entry removed.', 'whoiswho' ),
		);
		return isset( $words[ $result ] ) ? '<div class="notice notice-success"><p>' . esc_html( $words[ $result ] ) . '</p></div>' : '';
	}

	/**
	 * The add form.
	 */
	private function add_form(): string {
		$out  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ADD ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ADD ) ) . '" />';
		$out .= '<p><label>' . esc_html__( 'Name', 'whoiswho' ) . ' <input type="text" name="name" class="regular-text" required="required" /></label> <label>' . esc_html__( 'Type', 'whoiswho' ) . ' <select name="entity_type">';
		foreach ( StateRepository::ENTITY_TYPES as $type ) {
			$out .= '<option value="' . esc_attr( $type ) . '">' . esc_html( $type ) . '</option>';
		}
		return $out . '</select></label> <label>' . esc_html__( 'Reason', 'whoiswho' ) . ' <input type="text" name="reason" class="regular-text" required="required" /></label> <button class="button button-primary">' . esc_html__( 'Add to the list', 'whoiswho' ) . '</button></p></form>';
	}

	/**
	 * The remove form of one row.
	 *
	 * @param int $id Entry id.
	 */
	private function remove_form( int $id ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::REMOVE ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::REMOVE ) ) . '" /><input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" /><input type="text" name="reason" placeholder="' . esc_attr__( 'Reason', 'whoiswho' ) . '" required="required" /> <button class="button">' . esc_html__( 'Remove', 'whoiswho' ) . '</button></form>';
	}
}
