<?php
/**
 * The Nominations list as a shared staff list table (REQ-202, REQ-267, REQ-268, REQ-275). Rows are the filtered nominations,
 * newest first, 50 a page as before. Each row keeps its inline Stage and Status choosers and its email buttons; they post to the
 * screen's one guarded action through small forms printed after the table (the HTML form attribute links each control to its
 * form, so no form sits inside another). Row actions: Details, Change stage, Change status and Remove, the last three opening the
 * confirmation step for that one row. Bulk actions repeat the same single actions through the same NominationService and
 * NominationEmails calls, so the capability is checked and the audit row written per nomination: Set stage and Set status (one
 * shared value chosen in the confirmation step), Remove (destructive, confirmed), Send nomination email and Send winner email (a
 * row without a Contact email, or not a Winner, is skipped with the service's own reason). No nominator and no Contact email are
 * shown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Nominations table.
 */
final class NominationsTable extends ListTable {

	public const BULK = 'wiw_nominations_bulk';

	/**
	 * Cleaned filters.
	 *
	 * @var array<string, string>
	 */
	private array $filters;

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
	 * The per-row control forms, printed after the table.
	 *
	 * @var array<int, string>
	 */
	private array $control_forms = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, string>  $filters Filters (cleaned again here).
	 * @param NominationService|null $service Service; this site's by default.
	 * @param NominationEmails|null  $emails  Emails; this site's by default.
	 */
	public function __construct( array $filters = array(), ?NominationService $service = null, ?NominationEmails $emails = null ) {
		$this->filters = NominationList::clean( $filters );
		$this->service = $service ?? NominationService::for_site();
		$this->emails  = $emails ?? NominationEmails::for_site();
		parent::__construct( 'nomination', 'nominations' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bulk_action(): string {
		return self::BULK;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function capability(): string {
		return NominationsScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return NominationsSwitch::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'nominee'  => __( 'Nominee', 'whoiswho' ),
			'category' => __( 'Category', 'whoiswho' ),
			'source'   => __( 'Source', 'whoiswho' ),
			'stage'    => __( 'Stage', 'whoiswho' ),
			'status'   => __( 'Status', 'whoiswho' ),
			'emails'   => __( 'Emails', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'nominee';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function per_page(): int {
		return NominationList::PER_PAGE;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page (NominationList pages by its own size).
	 */
	protected function fetch( int $page, int $per_page ): array {
		unset( $per_page );
		$result = ( new NominationList() )->run( $this->filters, $page );
		return array(
			'items' => $result['rows'],
			'total' => $result['total'],
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_id( array $item ): int {
		return (int) $item['id'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_name( array $item ): string {
		return self::nominee_text( (string) ( $item['nominee'] ?? '' ) );
	}

	/**
	 * The nominee and category for the confirmation step; never the nominator.
	 *
	 * @param int $id Nomination.
	 */
	protected function name_for_id( int $id ): string {
		$row = $this->service->find( $id );
		if ( null === $row ) {
			return '#' . $id;
		}
		$profile = (int) $row['profile_id'];
		return self::nominee_text( $profile > 0 ? get_the_title( $profile ) : (string) ( $row['nominee_name'] ?? '' ) ) . ' (#' . $id . ')';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No nominations match.', 'whoiswho' );
	}

	/**
	 * Keep the filters and the page when coming back.
	 *
	 * @param Request $request Request.
	 * @return array<string, string|int>
	 */
	protected function screen_args( Request $request ): array {
		return array_merge( $this->filters, parent::screen_args( $request ) );
	}

	/**
	 * A nominee name, or the words for a nomination with no linked profile.
	 *
	 * @param string $name Name.
	 */
	private static function nominee_text( string $name ): string {
		return '' === $name ? __( '(no linked profile)', 'whoiswho' ) : $name;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		$id = (int) $item['id'];
		switch ( $column ) {
			case 'nominee':
				return '<a class="row-title" href="' . esc_url( NominationDetailScreen::url( $id ) ) . '">' . esc_html( self::nominee_text( (string) ( $item['nominee'] ?? '' ) ) ) . '</a>';
			case 'category':
				return esc_html( (string) $item['category'] );
			case 'source':
				return esc_html( NominationsScreen::label( (string) $item['source'] ) );
			case 'stage':
				return $this->chooser( 'stage', $id, (string) $item['stage'], AwardsTables::STAGES, $this->item_name( $item ) );
			case 'status':
				return $this->chooser( 'status', $id, (string) $item['status'], AwardsTables::STATUSES, $this->item_name( $item ) );
			case 'emails':
				return $this->email_buttons( $item );
		}
		return '';
	}

	/**
	 * An inline Stage or Status chooser: a select and a Save button tied to their own small form by the form attribute.
	 *
	 * @param string   $op      stage or status.
	 * @param int      $id      Nomination.
	 * @param string   $current Current value.
	 * @param string[] $values  Allowed values.
	 * @param string   $name    The row's nominee, for the control's accessible name.
	 */
	private function chooser( string $op, int $id, string $current, array $values, string $name ): string {
		$form  = 'wiw-nomination-' . $op . '-' . $id;
		$label = 'stage' === $op ? __( 'Stage', 'whoiswho' ) : __( 'Status', 'whoiswho' );
		/* translators: 1: Stage or Status, 2: nominee. */
		$aria = sprintf( __( '%1$s for %2$s', 'whoiswho' ), $label, $name );
		$out  = '<span class="wiw-inline-choice"><select name="value" form="' . esc_attr( $form ) . '" aria-label="' . esc_attr( $aria ) . '">';
		foreach ( $values as $value ) {
			$out .= '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( NominationsScreen::label( $value ) ) . '</option>';
		}
		$out .= '</select> <button type="submit" form="' . esc_attr( $form ) . '" class="button button-small">' . esc_html__( 'Save', 'whoiswho' ) . '</button></span>';

		$this->control_forms[] = $this->control_form( $form, $op, $id );
		return $out;
	}

	/**
	 * The email buttons a row allows, tied to the row's email form.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	private function email_buttons( array $item ): string {
		$id      = (int) $item['id'];
		$form    = 'wiw-nomination-email-' . $id;
		$buttons = '';
		if ( $this->emails->row_allows( $item, NominationEmails::KIND_NOMINATION ) ) {
			$buttons .= '<button type="submit" form="' . esc_attr( $form ) . '" name="op" value="email_nomination" class="button button-small">' . esc_html__( 'Send nomination email', 'whoiswho' ) . '</button> ';
		}
		if ( $this->emails->row_allows( $item, NominationEmails::KIND_WINNER ) ) {
			$buttons .= '<button type="submit" form="' . esc_attr( $form ) . '" name="op" value="email_winner" class="button button-small">' . esc_html__( 'Send winner email', 'whoiswho' ) . '</button>';
		}
		if ( '' === $buttons ) {
			return '';
		}
		$this->control_forms[] = $this->control_form( $form, '', $id );
		return '<span class="wiw-inline-choice">' . $buttons . '</span>';
	}

	/**
	 * One control form: the guarded action, its nonce, the operation and the nomination.
	 *
	 * @param string $dom Form id.
	 * @param string $op  Operation, or empty when the buttons carry it.
	 * @param int    $id  Nomination.
	 */
	private function control_form( string $dom, string $op, int $id ): string {
		$out  = '<form id="' . esc_attr( $dom ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wiw-control-form">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( NominationsScreen::ACTION ) . '" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( NominationsScreen::ACTION ) ) . '" />';
		$out .= '' !== $op ? '<input type="hidden" name="op" value="' . esc_attr( $op ) . '" />' : '';
		return $out . '<input type="hidden" name="nomination_id" value="' . esc_attr( (string) $id ) . '" /></form>';
	}

	/**
	 * The table, then the small forms its inline controls post through (outside the bulk form).
	 *
	 * @param Request $request Request.
	 */
	public function html( Request $request ): string {
		$this->control_forms = array();
		$out                 = parent::html( $request );
		$forms               = $this->take_control_forms();
		return $out . ( array() === $forms ? '' : '<div class="wiw-control-forms">' . implode( '', $forms ) . '</div>' );
	}

	/**
	 * The control forms gathered while the rows were drawn, once.
	 *
	 * @return array<int, string>
	 */
	private function take_control_forms(): array {
		$forms               = $this->control_forms;
		$this->control_forms = array();
		return $forms;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$id = (int) $item['id'];
		return array(
			'details'    => array(
				'label'      => __( 'Details', 'whoiswho' ),
				'url'        => NominationDetailScreen::url( $id ),
				'capability' => NominationDetailScreen::CAP,
			),
			'wiw-stage'  => array(
				'label'      => __( 'Change stage', 'whoiswho' ),
				'url'        => $this->step_url( 'stage', array( $id ) ),
				'capability' => NominationsScreen::CAP,
			),
			'wiw-status' => array(
				'label'      => __( 'Change status', 'whoiswho' ),
				'url'        => $this->step_url( 'status', array( $id ) ),
				'capability' => NominationsScreen::CAP,
			),
			'wiw-remove' => array(
				'label'       => __( 'Remove', 'whoiswho' ),
				'url'         => $this->step_url( 'remove', array( $id ) ),
				'capability'  => NominationsScreen::CAP,
				'destructive' => true,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function bulk_action_definitions(): array {
		$service = $this->service;
		$emails  = $this->emails;
		return array(
			'stage'            => array(
				'label'      => __( 'Set stage', 'whoiswho' ),
				'capability' => NominationsScreen::CAP,
				/* translators: %d: number of nominations changed. */
				'done'       => __( '%d stages saved', 'whoiswho' ),
				'fields'     => static fn (): string => self::value_field( __( 'Stage', 'whoiswho' ), AwardsTables::STAGES ),
				'handler'    => static function ( int $id, Request $request ) use ( $service ): bool {
					$service->set_stage( $id, $request->string( 'value' ) );
					return true;
				},
			),
			'status'           => array(
				'label'      => __( 'Set status', 'whoiswho' ),
				'capability' => NominationsScreen::CAP,
				/* translators: %d: number of nominations changed. */
				'done'       => __( '%d statuses saved', 'whoiswho' ),
				'fields'     => static fn (): string => self::value_field( __( 'Status', 'whoiswho' ), AwardsTables::STATUSES ),
				'handler'    => static function ( int $id, Request $request ) use ( $service ): bool {
					$service->set_status( $id, $request->string( 'value' ) );
					return true;
				},
			),
			'remove'           => array(
				'label'       => __( 'Remove', 'whoiswho' ),
				'capability'  => NominationsScreen::CAP,
				'destructive' => true,
				/* translators: %d: number of nominations removed. */
				'done'        => __( '%d removed', 'whoiswho' ),
				// The confirmation step is the removal confirmation: the same token the single Remove asks for.
				'handler'     => static function ( int $id ) use ( $service ): bool {
					$service->remove( $id, $service->confirmation_token( $id ) );
					return true;
				},
			),
			'email_nomination' => array(
				'label'      => __( 'Send nomination email', 'whoiswho' ),
				'capability' => NominationsScreen::CAP,
				/* translators: %d: number of emails queued. */
				'done'       => __( '%d nomination emails queued', 'whoiswho' ),
				'handler'    => static function ( int $id ) use ( $emails ): bool {
					$emails->send( $id, NominationEmails::KIND_NOMINATION );
					return true;
				},
			),
			'email_winner'     => array(
				'label'      => __( 'Send winner email', 'whoiswho' ),
				'capability' => NominationsScreen::CAP,
				/* translators: %d: number of emails queued. */
				'done'       => __( '%d winner emails queued', 'whoiswho' ),
				'handler'    => static function ( int $id ) use ( $emails ): bool {
					$emails->send( $id, NominationEmails::KIND_WINNER );
					return true;
				},
			),
		);
	}

	/**
	 * The shared Stage or Status choice of the confirmation step.
	 *
	 * @param string   $label  Label.
	 * @param string[] $values Values.
	 */
	private static function value_field( string $label, array $values ): string {
		$options = array();
		foreach ( $values as $value ) {
			$options[ $value ] = NominationsScreen::label( $value );
		}
		return Form::select(
			'value',
			$label,
			$options,
			'',
			array(
				'required' => true,
				'help'     => __( 'The same value is set on every nomination listed above; each one still passes its own checks.', 'whoiswho' ),
			)
		);
	}
}
