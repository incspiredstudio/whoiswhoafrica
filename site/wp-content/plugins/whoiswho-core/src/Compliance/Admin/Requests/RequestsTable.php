<?php
/**
 * The Requests and reports list as a shared staff list table (REQ-270, REQ-059, REQ-090, REQ-158, REQ-275). Columns: reference,
 * kind, status, due and breach. Row actions: Open, and for an open item the single actions that need no input: Resolve and
 * Dismiss on a report, Close on a data request, and Escalate. Bulk actions repeat the same single actions through the same
 * RequestsScreen methods, which check the capability (wiw_handle_disputes for a report decision, wiw_handle_requests otherwise)
 * and write one audit row per item; an item of another kind is skipped. Removal decisions (Valid: hide and suppress, and Not
 * valid with a reason sent to the requester), Hide the profile now and Send information stay on the item's own page: each is a
 * decision about one person's record. Closed items have no checkbox.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Admin\Requests;

use WhoIsWho\Core\Compliance\Admin\EnquiriesTable;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Requests and reports table.
 */
final class RequestsTable extends ListTable {

	public const BULK = 'wiw_requests_bulk';

	/**
	 * Filters: kind and status.
	 *
	 * @var array<string, string>
	 */
	private array $filters;

	/**
	 * The screen.
	 *
	 * @var RequestsScreen|null
	 */
	private ?RequestsScreen $host;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $filters Filters (kind, status).
	 * @param RequestsScreen|null   $screen  Screen; the site's by default.
	 */
	public function __construct( array $filters = array(), ?RequestsScreen $screen = null ) {
		parent::__construct( 'request', 'requests' );
		$this->filters = array_filter(
			array(
				'kind'   => (string) ( $filters['kind'] ?? '' ),
				'status' => (string) ( $filters['status'] ?? '' ),
			),
			static fn ( string $value ): bool => '' !== $value
		);
		$this->host    = $screen;
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
		return RequestsScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return RequestsScreen::SCREEN;
	}

	/**
	 * The screen.
	 */
	private function host(): RequestsScreen {
		if ( null === $this->host ) {
			$this->host = RequestsScreen::for_site();
		}
		return $this->host;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'reference' => __( 'Reference', 'whoiswho' ),
			'kind'      => __( 'Kind', 'whoiswho' ),
			'status'    => __( 'Status', 'whoiswho' ),
			'due'       => __( 'Due', 'whoiswho' ),
			'breach'    => __( 'Breach', 'whoiswho' ),
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
		$rows = $this->host()->rows( $this->filters['kind'] ?? '', $this->filters['status'] ?? '' );
		return array(
			'items' => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
			'total' => count( $rows ),
		);
	}

	/**
	 * Only an open item can be decided, so only an open item has a checkbox.
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
	 * The reference and kind, for the confirmation step.
	 *
	 * @param int $id Item id.
	 */
	protected function name_for_id( int $id ): string {
		$item = $this->host()->item( $id );
		return null === $item ? '#' . $id : (string) $item['reference'] . ' (' . (string) $item['kind'] . ')';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No requests or reports.', 'whoiswho' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Request $request Request.
	 */
	protected function screen_args( Request $request ): array {
		return array_merge( $this->filters, parent::screen_args( $request ) );
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
				return '<a class="row-title" href="' . esc_url( RequestsScreen::item_url( (int) $item['id'] ) ) . '">' . esc_html( (string) $item['reference'] ) . '</a>';
			case 'kind':
				return esc_html( (string) $item['kind'] );
			case 'status':
				return EnquiriesTable::status_pill( (string) $item['status'] );
			case 'due':
				return esc_html( (string) $item['due_at'] );
			case 'breach':
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
				'url'        => RequestsScreen::item_url( $id ),
				'capability' => RequestsScreen::CAP,
			),
		);
		if ( ItemStatus::is_closed( (string) $item['status'] ) ) {
			return $actions;
		}
		$definitions = $this->bulk_action_definitions();
		$keys        = array(
			ItemType::REPORT       => array( 'resolve', 'dismiss' ),
			ItemType::DATA_REQUEST => array( 'close' ),
		);
		foreach ( array_merge( $keys[ (string) $item['item_type'] ] ?? array(), array( 'escalate' ) ) as $key ) {
			$actions[ 'wiw-' . $key ] = array(
				'label'      => $definitions[ $key ]['label'],
				'url'        => $this->step_url( $key, array( $id ) ),
				'capability' => $definitions[ $key ]['capability'],
			);
		}
		return $actions;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function bulk_action_definitions(): array {
		return array(
			'resolve'  => array(
				'label'      => __( 'Resolve report', 'whoiswho' ),
				'capability' => RequestsScreen::CAP_DISPUTES,
				/* translators: %d: number of reports resolved. */
				'done'       => __( '%d resolved', 'whoiswho' ),
				'handler'    => array( $this, 'resolve_one' ),
			),
			'dismiss'  => array(
				'label'       => __( 'Dismiss report', 'whoiswho' ),
				'capability'  => RequestsScreen::CAP_DISPUTES,
				'destructive' => true,
				/* translators: %d: number of reports dismissed. */
				'done'        => __( '%d dismissed', 'whoiswho' ),
				'handler'     => array( $this, 'dismiss_one' ),
			),
			'close'    => array(
				'label'      => __( 'Close data request: fulfilled', 'whoiswho' ),
				'capability' => RequestsScreen::CAP,
				/* translators: %d: number of data requests closed. */
				'done'       => __( '%d closed', 'whoiswho' ),
				'handler'    => array( $this, 'close_one' ),
			),
			'escalate' => array(
				'label'      => __( 'Escalate to the Administrator', 'whoiswho' ),
				'capability' => RequestsScreen::CAP,
				/* translators: %d: number of items escalated. */
				'done'       => __( '%d escalated', 'whoiswho' ),
				'handler'    => array( $this, 'escalate_one' ),
			),
		);
	}

	/**
	 * Resolve one report, as the single Resolve does.
	 *
	 * @param int $id Item id.
	 * @return bool|\WP_Error
	 */
	public function resolve_one( int $id ) {
		return $this->run( static fn ( RequestsScreen $screen ) => $screen->resolve_report( $id ) );
	}

	/**
	 * Dismiss one report, as the single Dismiss does.
	 *
	 * @param int $id Item id.
	 * @return bool|\WP_Error
	 */
	public function dismiss_one( int $id ) {
		return $this->run( static fn ( RequestsScreen $screen ) => $screen->dismiss_report( $id ) );
	}

	/**
	 * Close one data request, as the single Close does.
	 *
	 * @param int $id Item id.
	 * @return bool|\WP_Error
	 */
	public function close_one( int $id ) {
		return $this->run( static fn ( RequestsScreen $screen ) => $screen->close_data_request( $id ) );
	}

	/**
	 * Escalate one item, as the single Escalate does.
	 *
	 * @param int $id Item id.
	 * @return bool|\WP_Error
	 */
	public function escalate_one( int $id ) {
		return $this->run( static fn ( RequestsScreen $screen ) => $screen->escalate( $id ) );
	}

	/**
	 * Run one single action and turn a refusal into a skipped row (the messages are fixed sentences with no names).
	 *
	 * @param callable $action callable( RequestsScreen ): void.
	 * @return bool|\WP_Error
	 */
	private function run( callable $action ) {
		try {
			$action( $this->host() );
		} catch ( \InvalidArgumentException | \DomainException $e ) {
			return new \WP_Error( 'wiw_request_refused', $e->getMessage() );
		}
		return true;
	}
}
