<?php
/**
 * The Corrections list as a shared staff list table (REQ-271, REQ-157, REQ-158, REQ-275). Columns: reference, profile, suggestion,
 * received, due, status and overdue. Row actions: Open, and for an open correction Apply (the profile's edit screen with the
 * request as the reason), Mark resolved and Decline, which open the confirmation step for that one row. Bulk actions repeat the
 * same single actions through CorrectionsScreen, which checks wiw_handle_disputes and writes one audit row per item and emails
 * the requester: Mark resolved, and Decline with one shared reason (kept as a private staff note on every item, never sent).
 * Closed corrections have no checkbox.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Admin\Corrections;

use WhoIsWho\Core\Compliance\Admin\EnquiriesTable;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Directory\Admin\ProfileEditScreen;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Corrections table.
 */
final class CorrectionsTable extends ListTable {

	public const BULK = 'wiw_corrections_bulk';

	/**
	 * The status filter, or empty.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * The screen.
	 *
	 * @var CorrectionsScreen|null
	 */
	private ?CorrectionsScreen $host;

	/**
	 * Constructor.
	 *
	 * @param string                 $status Status filter.
	 * @param CorrectionsScreen|null $screen Screen; the site's by default.
	 */
	public function __construct( string $status = '', ?CorrectionsScreen $screen = null ) {
		parent::__construct( 'correction', 'corrections' );
		$this->status = in_array( $status, ItemStatus::all(), true ) ? $status : '';
		$this->host   = $screen;
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
		return CorrectionsScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return CorrectionsScreen::SCREEN;
	}

	/**
	 * The screen.
	 */
	private function host(): CorrectionsScreen {
		if ( null === $this->host ) {
			$this->host = CorrectionsScreen::for_site();
		}
		return $this->host;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'reference'  => __( 'Reference', 'whoiswho' ),
			'profile'    => __( 'Profile', 'whoiswho' ),
			'suggestion' => __( 'Suggestion', 'whoiswho' ),
			'received'   => __( 'Received', 'whoiswho' ),
			'due'        => __( 'Due', 'whoiswho' ),
			'status'     => __( 'Status', 'whoiswho' ),
			'overdue'    => __( 'Overdue', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'reference';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		$rows = $this->host()->rows( $this->status );
		return array(
			'items' => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
			'total' => count( $rows ),
		);
	}

	/**
	 * Only an open correction can be decided, so only an open one has a checkbox.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_id( array $item ): int {
		return ItemStatus::is_closed( (string) $item['status'] ) ? 0 : (int) $item['id'];
	}

	/**
	 * The reference: the checkbox label and the confirmation list carry no names.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_name( array $item ): string {
		return (string) $item['reference'];
	}

	/**
	 * The reference, for the confirmation step.
	 *
	 * @param int $id Item id.
	 */
	protected function name_for_id( int $id ): string {
		$item = $this->host()->item( $id );
		return null === $item ? '#' . $id : (string) $item['reference'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No corrections.', 'whoiswho' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Request $request Request.
	 */
	protected function screen_args( Request $request ): array {
		return array_merge( '' !== $this->status ? array( 'status' => $this->status ) : array(), parent::screen_args( $request ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		switch ( $column ) {
			case 'reference':
				return '<a class="row-title" href="' . esc_url( CorrectionsScreen::item_url( (int) $item['id'] ) ) . '">' . esc_html( (string) $item['reference'] ) . '</a>';
			case 'profile':
				return esc_html( (string) $item['profile_name'] );
			case 'suggestion':
				return esc_html( (string) $item['suggestion'] );
			case 'received':
				return esc_html( (string) $item['created_at'] );
			case 'due':
				return esc_html( (string) $item['due_at'] );
			case 'status':
				return EnquiriesTable::status_pill( (string) $item['status'] );
			case 'overdue':
				return ! empty( $item['breach'] ) ? self::pill( __( 'Overdue', 'whoiswho' ), 'danger' ) : '';
		}
		return '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$id      = (int) $item['id'];
		$actions = array(
			'open' => array(
				'label'      => __( 'Open', 'whoiswho' ),
				'url'        => CorrectionsScreen::item_url( $id ),
				'capability' => CorrectionsScreen::CAP,
			),
		);
		if ( ItemStatus::is_closed( (string) $item['status'] ) ) {
			return $actions;
		}
		if ( null !== $item['profile_id'] ) {
			$actions['wiw-apply'] = array(
				'label'      => __( 'Apply', 'whoiswho' ),
				'url'        => $this->host()->apply_url( $item ),
				'capability' => ProfileEditScreen::CAPABILITY,
			);
		}
		$actions['wiw-resolve'] = array(
			'label'      => __( 'Mark resolved', 'whoiswho' ),
			'url'        => $this->step_url( 'resolve', array( $id ) ),
			'capability' => CorrectionsScreen::CAP_DISPUTES,
		);
		$actions['wiw-decline'] = array(
			'label'      => __( 'Decline', 'whoiswho' ),
			'url'        => $this->step_url( 'decline', array( $id ) ),
			'capability' => CorrectionsScreen::CAP_DISPUTES,
		);
		return $actions;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function bulk_action_definitions(): array {
		return array(
			'resolve' => array(
				'label'      => __( 'Mark resolved', 'whoiswho' ),
				'capability' => CorrectionsScreen::CAP_DISPUTES,
				/* translators: %d: number of corrections resolved. */
				'done'       => __( '%d marked resolved', 'whoiswho' ),
				'handler'    => array( $this, 'resolve_one' ),
			),
			'decline' => array(
				'label'       => __( 'Decline', 'whoiswho' ),
				'capability'  => CorrectionsScreen::CAP_DISPUTES,
				'destructive' => true,
				/* translators: %d: number of corrections declined. */
				'done'        => __( '%d declined', 'whoiswho' ),
				'fields'      => array( self::class, 'decline_fields' ),
				'handler'     => array( $this, 'decline_one' ),
			),
		);
	}

	/**
	 * Mark one correction resolved, as the single Mark resolved does.
	 *
	 * @param int $id Item id.
	 * @return bool|\WP_Error
	 */
	public function resolve_one( int $id ) {
		try {
			$this->host()->mark_resolved( $id );
		} catch ( \InvalidArgumentException | \DomainException $e ) {
			return new \WP_Error( 'wiw_correction_refused', $e->getMessage() );
		}
		return true;
	}

	/**
	 * Decline one correction with the shared reason, as the single Decline does.
	 *
	 * @param int     $id      Item id.
	 * @param Request $request Request: reason.
	 * @return bool|\WP_Error
	 */
	public function decline_one( int $id, Request $request ) {
		try {
			$this->host()->decline( $id, $request->text( 'reason' ) );
		} catch ( \InvalidArgumentException | \DomainException $e ) {
			return new \WP_Error( 'wiw_correction_refused', $e->getMessage() );
		}
		return true;
	}

	/**
	 * The shared reason for a bulk decline.
	 */
	public static function decline_fields(): string {
		return Form::text(
			'reason',
			__( 'Reason (kept private)', 'whoiswho' ),
			'',
			array(
				'required'  => true,
				'maxlength' => 1000,
				'help'      => __( 'Kept as a private staff note on every correction listed above. It is never shown on the site or sent to the requester.', 'whoiswho' ),
			)
		);
	}
}
