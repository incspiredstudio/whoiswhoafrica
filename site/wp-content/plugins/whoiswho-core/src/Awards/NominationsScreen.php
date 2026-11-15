<?php
/**
 * The Nominations screen (REQ-267, REQ-194, REQ-031, REQ-202): the one screen that runs the awards. At the top, the
 * "Nominations page: Open / Closed" toggle (read-only for an Editor, from NominationsSwitch); below it a list filtered by stage,
 * status, category and source, a stage control, a status control and Remove behind a confirmation step. Every control posts to one
 * guarded action, and the handler only calls NominationService, which checks the capability again and writes the audit row. There
 * is no Feature flag screen and no Shortlist and winners screen. The list shows the nominee and category only: no nominator and
 * no Contact email. The list is a shared staff list table (NominationsTable, REQ-275): bulk Set stage, Set status, Remove and the
 * two emails repeat the single controls through the same services, and the row actions are Details, Change stage, Change status
 * and Remove.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Awards\Admin\AddProfileForm;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

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
	 * The list table's own address values (its notice and confirmation step), passed through from the request.
	 *
	 * @var array<string, string>
	 */
	private array $list_args = array();

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
				NominationsTable::register_bulk_handler();
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
		$out  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wiw-inline-form">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out .= '<input type="hidden" name="op" value="' . esc_attr( $op ) . '" />';
		foreach ( $fields as $name => $value ) {
			$out .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
		}
		return $out . $inner . '</form>';
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
		$out = '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="wiw-filters"><input type="hidden" name="page" value="' . esc_attr( NominationsSwitch::SCREEN ) . '" />';
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
	 * The removal confirmation for one nomination, asked by the remove_ask control: the nominee and a Confirm removal button that
	 * carries the service's confirmation token.
	 *
	 * @param int $id Nomination.
	 */
	private function confirm_html( int $id ): string {
		$row = $this->service->find( $id );
		if ( null === $row ) {
			return '';
		}
		$profile = (int) $row['profile_id'];
		$name    = $profile > 0 ? get_the_title( $profile ) : (string) ( $row['nominee_name'] ?? '' );
		$cancel  = add_query_arg( 'page', NominationsSwitch::SCREEN, admin_url( 'admin.php' ) );
		$button  = $this->form(
			'remove',
			array(
				'nomination_id' => $id,
				'token'         => $this->service->confirmation_token( $id ),
			),
			Form::actions( '<button type="submit" class="button button-primary wiw-button--danger">' . esc_html__( 'Confirm removal', 'whoiswho' ) . '</button> <a class="button" href="' . esc_url( $cancel ) . '">' . esc_html__( 'Cancel', 'whoiswho' ) . '</a>' )
		);
		/* translators: 1: nominee, 2: nomination number. */
		$text = sprintf( __( '%1$s (nomination #%2$d) will be removed. The change history keeps the removal.', 'whoiswho' ), '' === $name ? __( '(no linked profile)', 'whoiswho' ) : $name, $id );
		return Form::section( __( 'Remove this nomination?', 'whoiswho' ), '<p>' . esc_html( $text ) . '</p>' . $button, array( 'id' => 'wiw-remove-confirm' ) );
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
		$out     = '<div class="wrap wiw-staff"><h1 class="wp-heading-inline">' . esc_html__( 'Nominations', 'whoiswho' ) . '</h1>';
		if ( current_user_can( NominationExport::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			$out .= ' <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . NominationExport::ACTION ), 'wiw_action_' . NominationExport::ACTION ) ) . '">' . esc_html__( 'Export nominations (CSV)', 'whoiswho' ) . '</a>';
		}
		$out .= '<hr class="wp-header-end" />';
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		$values = array_merge( $filters, $this->list_args, $page > 1 ? array( 'paged' => (string) $page ) : array() );
		$table  = new NominationsTable( $filters, $this->service, $this->emails );
		if ( isset( $this->list_args[ ListTable::STEP_ACTION ] ) ) {
			// A confirmation step of the list: show only the step.
			return $out . $table->html( new Request( $values ) ) . '</div>';
		}
		$out .= $this->toggle->body_html( $on );
		if ( $confirm > 0 ) {
			$out .= $this->confirm_html( $confirm );
		}
		$out  .= ( new AddProfileForm( $this->service ) )->html( $profile_q );
		$out  .= $this->filter_form( $filters );
		$total = ( new NominationList() )->run( $filters, 1 )['total'];
		/* translators: %d: number of nominations. */
		$out .= '<p class="wiw-lead">' . esc_html( sprintf( _n( '%d nomination', '%d nominations', $total, 'whoiswho' ), $total ) ) . '</p>';
		$out .= $table->html( new Request( $values ) );
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
		foreach ( array( ListTable::NOTICE_VAR, ListTable::STEP_ACTION, ListTable::STEP_IDS ) as $key ) {
			if ( '' !== $request->string( $key ) ) {
				$this->list_args[ $key ] = $request->string( $key );
			}
		}
		echo $this->html( $filters, max( 1, $request->int( 'paged' ) ), $request->string( 'wiw_notice' ), $request->int( 'confirm' ), null, $request->string( 'profile_q' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
	}
}
