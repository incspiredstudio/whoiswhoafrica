<?php
/**
 * The Nominations screen (REQ-267, REQ-194, REQ-031, REQ-202): the one screen that runs the awards. At the top, the
 * "Nominations page: Open / Closed" toggle (read-only for an Editor, from NominationsSwitch); below it a list filtered by stage,
 * status, category and source, a stage control, a status control and Remove behind a confirmation step. Every control posts to one
 * guarded action, and the handler only calls NominationService, which checks the capability again and writes the audit row. There
 * is no Feature flag screen and no Shortlist and winners screen. The list shows the nominee and category only: no nominator and
 * no Contact email.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Awards\Admin\AddProfileForm;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Nominations screen.
 */
final class NominationsScreen {

	public const ACTION = 'wiw_nomination_op';
	public const CAP    = 'wiw_manage_awards';
	public const OPS    = array( 'stage', 'status', 'remove_ask', 'remove', 'email_nomination', 'email_winner' );

	/**
	 * Switch.
	 *
	 * @var NominationsSwitch
	 */
	private NominationsSwitch $toggle;

	/**
	 * Nomination service.
	 *
	 * @var NominationService
	 */
	private NominationService $service;

	/**
	 * Nomination and winner emails.
	 *
	 * @var NominationEmails
	 */
	private NominationEmails $emails;

	/**
	 * Constructor.
	 *
	 * @param NominationsSwitch     $toggle  The toggle.
	 * @param NominationService     $service The service every control calls.
	 * @param NominationEmails|null $emails  The email sender; this site's by default.
	 */
	public function __construct( NominationsSwitch $toggle, NominationService $service, ?NominationEmails $emails = null ) {
		$this->toggle  = $toggle;
		$this->service = $service;
		$this->emails  = $emails ?? NominationEmails::for_site();
	}

	/**
	 * Register the control action, in the admin only. The screen itself is registered by NominationsSwitch.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				ActionGuard::register(
					self::ACTION,
					self::CAP,
					static function ( Request $request ): void {
						$screen = new self( new NominationsSwitch( \WhoIsWho\Core\Platform\Settings\SettingsService::for_site() ), NominationService::for_site() );
						$result = $screen->handle( $request );
						$args   = array(
							'page'       => NominationsSwitch::SCREEN,
							'wiw_notice' => rawurlencode( $result['notice'] ),
						);
						if ( $result['confirm'] > 0 ) {
							$args['confirm'] = $result['confirm'];
						}
						wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
						exit;
					}
				);
			},
			1
		);
	}

	/**
	 * Run one control (capability and nonce already checked by ActionGuard; the service checks the capability again).
	 *
	 * @param Request $request Request.
	 * @return array{notice: string, confirm: int} Message for the person, and a nomination to ask a removal confirmation for.
	 */
	public function handle( Request $request ): array {
		$op = $request->string( 'op' );
		$id = $request->int( 'nomination_id' );
		try {
			switch ( $op ) {
				case 'stage':
					$this->service->set_stage( $id, $request->string( 'value' ) );
					return $this->done( 'Stage saved.' );
				case 'status':
					$this->service->set_status( $id, $request->string( 'value' ) );
					return $this->done( 'Status saved.' );
				case 'remove_ask':
					if ( null === $this->service->find( $id ) ) {
						return $this->done( 'That nomination does not exist.' );
					}
					return array(
						'notice'  => 'Confirm the removal below.',
						'confirm' => $id,
					);
				case 'remove':
					$this->service->remove( $id, $request->string( 'token' ) );
					return $this->done( 'Nomination removed.' );
				case 'email_nomination':
					$this->emails->send( $id, NominationEmails::KIND_NOMINATION );
					return $this->done( 'The nomination email is queued.' );
				case 'email_winner':
					$this->emails->send( $id, NominationEmails::KIND_WINNER );
					return $this->done( 'The winner email is queued.' );
			}//end switch
		} catch ( \DomainException $refusal ) {
			return $this->done( $refusal->getMessage() );
		}//end try
		return $this->done( 'Nothing was changed.' );
	}

	/**
	 * A result with no confirmation to ask.
	 *
	 * @param string $notice Message.
	 * @return array{notice: string, confirm: int}
	 */
	private function done( string $notice ): array {
		return array(
			'notice'  => $notice,
			'confirm' => 0,
		);
	}

	/**
	 * A small control form.
	 *
	 * @param string               $op     Operation.
	 * @param array<string, mixed> $fields Hidden fields.
	 * @param string               $inner  Inner markup (already escaped).
	 */
	private function form( string $op, array $fields, string $inner ): string {
		$out  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out .= '<input type="hidden" name="op" value="' . esc_attr( $op ) . '" />';
		foreach ( $fields as $name => $value ) {
			$out .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
		}
		return $out . $inner . '</form> ';
	}

	/**
	 * A select that submits a stage or status choice.
	 *
	 * @param string   $op      stage or status.
	 * @param int      $id      Nomination.
	 * @param string   $current Current value.
	 * @param string[] $values  Allowed values.
	 */
	private function chooser( string $op, int $id, string $current, array $values ): string {
		$select = '<select name="value" aria-label="' . esc_attr( 'stage' === $op ? __( 'Stage', 'whoiswho' ) : __( 'Status', 'whoiswho' ) ) . '">';
		foreach ( $values as $value ) {
			$select .= '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( self::label( $value ) ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		return $this->form( $op, array( 'nomination_id' => $id ), $select . '</select><button type="submit" class="button">' . esc_html__( 'Save', 'whoiswho' ) . '</button>' );
	}

	/**
	 * Words for stage, status and source values.
	 *
	 * @param string $value Stored value.
	 */
	public static function label( string $value ): string {
		$labels = array(
			'nominated'   => __( 'Nominated', 'whoiswho' ),
			'winner'      => __( 'Winner', 'whoiswho' ),
			'live'        => __( 'Live', 'whoiswho' ),
			'not_live'    => __( 'Not live', 'whoiswho' ),
			'public_form' => __( 'Public form', 'whoiswho' ),
			'staff'       => __( 'Staff-added', 'whoiswho' ),
		);
		return $labels[ $value ] ?? $value;
	}

	/**
	 * The filter form (GET, so the filter state is in the address).
	 *
	 * @param array<string, string> $filters Cleaned filters.
	 */
	private function filter_form( array $filters ): string {
		$out = '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( NominationsSwitch::SCREEN ) . '" />';
		foreach ( array(
			'stage'  => array( __( 'Stage', 'whoiswho' ), AwardsTables::STAGES ),
			'status' => array( __( 'Status', 'whoiswho' ), AwardsTables::STATUSES ),
			'source' => array( __( 'Source', 'whoiswho' ), AwardsTables::SOURCES ),
		) as $name => $def ) {
			$out .= '<label>' . esc_html( $def[0] ) . ' <select name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
			foreach ( $def[1] as $value ) {
				$out .= '<option value="' . esc_attr( $value ) . '"' . selected( $filters[ $name ] ?? '', $value, false ) . '>' . esc_html( self::label( $value ) ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
			}
			$out .= '</select></label> ';
		}
		$out .= '<label>' . esc_html__( 'Category', 'whoiswho' ) . ' <select name="category"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
		foreach ( CategoryService::for_site()->grouped( true ) as $group ) {
			foreach ( $group['categories'] as $category ) {
				$out .= '<option value="' . esc_attr( (string) $category['id'] ) . '"' . selected( $filters['category'] ?? '', (string) $category['id'], false ) . '>' . esc_html( (string) $category['title'] ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
			}
		}
		return $out . '</select></label> <button type="submit" class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
	}

	/**
	 * The screen markup.
	 *
	 * @param array<string, string> $filters Raw filters from the address.
	 * @param int                   $page    Page.
	 * @param string                $notice  Message to show after an action.
	 * @param int                   $confirm Nomination awaiting a removal confirmation.
	 * @param bool|null             $on      Toggle state to show; the stored state when null.
	 * @param string                $profile_q Search text for the Add profile form.
	 * @throws \DomainException When the user may not manage awards.
	 */
	public function html( array $filters = array(), int $page = 1, string $notice = '', int $confirm = 0, ?bool $on = null, string $profile_q = '' ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to manage nominations.' );
		}
		$filters = NominationList::clean( $filters );
		$out     = '<div class="wrap"><h1>' . esc_html__( 'Nominations', 'whoiswho' ) . '</h1>';
		$out    .= $this->toggle->body_html( $on );
		if ( current_user_can( NominationExport::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			$out .= '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . NominationExport::ACTION ), 'wiw_action_' . NominationExport::ACTION ) ) . '">' . esc_html__( 'Export nominations (CSV)', 'whoiswho' ) . '</a></p>';
		}
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		$out   .= ( new AddProfileForm( $this->service ) )->html( $profile_q );
		$out   .= $this->filter_form( $filters );
		$result = ( new NominationList() )->run( $filters, $page );
		/* translators: %d: number of nominations. */
		$out .= '<p>' . esc_html( sprintf( _n( '%d nomination', '%d nominations', $result['total'], 'whoiswho' ), $result['total'] ) ) . '</p>';
		$out .= '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Nominee', 'whoiswho' ), __( 'Category', 'whoiswho' ), __( 'Source', 'whoiswho' ), __( 'Stage', 'whoiswho' ), __( 'Status', 'whoiswho' ), '' ) as $heading ) {
			$out .= '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		if ( array() === $result['rows'] ) {
			$out .= '<tr><td colspan="6">' . esc_html__( 'No nominations match.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $result['rows'] as $row ) {
			$id   = (int) $row['id'];
			$out .= '<tr><td>' . esc_html( '' === (string) $row['nominee'] ? __( '(no linked profile)', 'whoiswho' ) : (string) $row['nominee'] ) . '</td>';
			$out .= '<td>' . esc_html( (string) $row['category'] ) . '</td><td>' . esc_html( self::label( (string) $row['source'] ) ) . '</td>';
			$out .= '<td>' . $this->chooser( 'stage', $id, (string) $row['stage'], AwardsTables::STAGES ) . '</td>';
			$out .= '<td>' . $this->chooser( 'status', $id, (string) $row['status'], AwardsTables::STATUSES ) . '</td><td>';
			if ( $confirm === $id ) {
				$out .= $this->form(
					'remove',
					array(
						'nomination_id' => $id,
						'token'         => $this->service->confirmation_token( $id ),
					),
					'<button type="submit" class="button button-primary">' . esc_html__( 'Confirm removal', 'whoiswho' ) . '</button>'
				);
			} else {
				$out .= $this->form( 'remove_ask', array( 'nomination_id' => $id ), '<button type="submit" class="button">' . esc_html__( 'Remove', 'whoiswho' ) . '</button>' );
			}
			if ( $this->emails->row_allows( $row, NominationEmails::KIND_NOMINATION ) ) {
				$out .= $this->form( 'email_nomination', array( 'nomination_id' => $id ), '<button type="submit" class="button">' . esc_html__( 'Send nomination email', 'whoiswho' ) . '</button>' );
			}
			if ( $this->emails->row_allows( $row, NominationEmails::KIND_WINNER ) ) {
				$out .= $this->form( 'email_winner', array( 'nomination_id' => $id ), '<button type="submit" class="button">' . esc_html__( 'Send winner email', 'whoiswho' ) . '</button>' );
			}
			$out .= '</td></tr>';
		}//end foreach
		$out .= '</tbody></table>';
		for ( $i = 1; $result['pages'] > 1 && $i <= $result['pages']; $i++ ) {
			$url  = add_query_arg(
				array_merge(
					$filters,
					array(
						'page'  => NominationsSwitch::SCREEN,
						'paged' => $i,
					)
				),
				admin_url( 'admin.php' )
			);
			$out .= $i === $page ? '<strong>' . esc_html( (string) $i ) . '</strong> ' : '<a href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> ';
		}
		return $out . '</div>';
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to see this page.', 'whoiswho' ), '', array( 'response' => 403 ) );
		}
		$request = Request::from_globals();
		$filters = array();
		foreach ( array_keys( NominationList::FILTERS ) as $name ) {
			$filters[ $name ] = $request->string( $name );
		}
		echo wp_kses(
			$this->html( $filters, max( 1, $request->int( 'paged' ) ), $request->string( 'wiw_notice' ), $request->int( 'confirm' ), null, $request->string( 'profile_q' ) ),
			array(
				'div'      => array( 'class' => true ),
				'h1'       => array(),
				'h2'       => array(),
				'textarea' => array(
					'name'      => true,
					'rows'      => true,
					'cols'      => true,
					'maxlength' => true,
				),
				'p'        => array(),
				'br'       => array(),
				'strong'   => array(),
				'a'        => array(
					'href'  => true,
					'class' => true,
				),
				'label'    => array(),
				'table'    => array( 'class' => true ),
				'thead'    => array(),
				'tbody'    => array(),
				'tr'       => array(),
				'th'       => array( 'scope' => true ),
				'td'       => array( 'colspan' => true ),
				'form'     => array(
					'method' => true,
					'action' => true,
					'style'  => true,
				),
				'select'   => array(
					'name'       => true,
					'aria-label' => true,
				),
				'option'   => array(
					'value'    => true,
					'selected' => true,
				),
				'input'    => array(
					'type'      => true,
					'name'      => true,
					'value'     => true,
					'maxlength' => true,
					'class'     => true,
				),
				'button'   => array(
					'type'  => true,
					'class' => true,
				),
			)
		);
	}
}
