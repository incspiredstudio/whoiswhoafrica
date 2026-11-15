<?php
/**
 * The Suppression list screen (REQ-207, REQ-147, REQ-244; prototype A22): the suppressed names with type, how the entry arose, who
 * added it and when, and the reason (staff only). Viewing needs wiw_view_suppression (Reviewer, Editor, Administrator). Adding or
 * removing an entry by hand needs wiw_edit_suppression (Administrator) and, for a removal, a reason; both are audited by the
 * Suppression service. The form controls are not printed for a user without wiw_edit_suppression. The list is a shared staff list
 * table (SuppressionTable, REQ-275): Remove is a row action and a bulk action with one shared reason; the add form uses the kit.
 * The single remove handler stays registered for any form that still posts to it.
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
use WhoIsWho\Core\Platform\Admin\Ui\Form;
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
					},
					false,
					'compliance'
				);
				$registry->hook();
				ActionGuard::register( self::ADD, self::EDIT_CAP, array( self::class, 'handle_add' ) );
				ActionGuard::register( self::REMOVE, self::EDIT_CAP, array( self::class, 'handle_remove' ) );
				SuppressionTable::register_bulk_handler();
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
		$out      = '<div class="wrap wiw-staff"><h1>' . esc_html__( 'Suppression list', 'whoiswho' ) . '</h1>' . $this->notice( $request );
		$out     .= '<p>' . esc_html__( 'Names that are never imported or published. The reason is for staff only.', 'whoiswho' ) . '</p>';
		if ( current_user_can( SnapshotExport::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			$out .= '<p><a class="button" href="' . esc_url( SnapshotExport::url() ) . '">' . esc_html__( 'Download snapshot for acquisition', 'whoiswho' ) . '</a> ' . esc_html__( 'The file contains personal data: keep it out of shared folders and delete it after the run.', 'whoiswho' ) . '</p>';
		}
		if ( $can_edit ) {
			$out .= $this->add_form();
		}
		return $out . ( new SuppressionTable() )->html( $request ) . '</div>';
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
	 * The add form, in the staff form kit.
	 */
	private function add_form(): string {
		$hidden = '<input type="hidden" name="action" value="' . esc_attr( self::ADD ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ADD ) ) . '" />';
		$fields = '<div class="wiw-field-grid">'
			. Form::text( 'name', __( 'Name', 'whoiswho' ), '', array( 'required' => true ) )
			. Form::select( 'entity_type', __( 'Type', 'whoiswho' ), array_combine( StateRepository::ENTITY_TYPES, StateRepository::ENTITY_TYPES ) )
			. Form::text(
				'reason',
				__( 'Reason', 'whoiswho' ),
				'',
				array(
					'required' => true,
					'help'     => __( 'For staff only.', 'whoiswho' ),
				)
			)
			. '</div>';
		return Form::section(
			__( 'Add a name', 'whoiswho' ),
			Form::open( $hidden ) . $fields . Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Add to the list', 'whoiswho' ) . '</button>' ) . '</form>',
			array(
				'id'          => 'wiw-suppression-add',
				'description' => __( 'The name is never imported or published while it is on the list.', 'whoiswho' ),
			)
		);
	}
}
