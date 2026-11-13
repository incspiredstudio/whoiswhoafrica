<?php
/**
 * The Requests and reports screen (REQ-270, REQ-059, REQ-090, REQ-158, REQ-244): one list of data requests, removals and
 * reports with the Kind column, status and kind filters, due date and breach flag, and a detail page with the staff actions for
 * each kind. Viewing needs wiw_handle_requests; deciding a removal, or dismissing or resolving a report, needs
 * wiw_handle_disputes (checked again in RemovalDecisions); hiding a reported profile needs wiw_bulk_unpublish or
 * wiw_unpublish_profiles. Every change is audited with ids and statuses only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Admin\Requests;

use WhoIsWho\Core\Compliance\Info\InformationSender;
use WhoIsWho\Core\Compliance\Removals\RemovalDecisions;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Requests and reports screen.
 */
final class RequestsScreen {

	public const SCREEN         = 'wiw-requests';
	public const CAP            = 'wiw_handle_requests';
	public const CAP_DISPUTES   = 'wiw_handle_disputes';
	public const ACTION         = 'wiw_request_action';
	public const ACTION_HIDE    = 'wiw_request_hide';
	public const ACTION_HIDE_ED = 'wiw_request_hide_editor';
	public const LIMIT          = 200;
	public const KINDS          = array(
		ItemType::DATA_REQUEST => 'Data request',
		ItemType::REMOVAL      => 'Removal',
		ItemType::REPORT       => 'Report',
	);

	/**
	 * Items.
	 *
	 * @var SupportItems
	 */
	private SupportItems $items;

	/**
	 * Removal decisions.
	 *
	 * @var RemovalDecisions
	 */
	private RemovalDecisions $removals;

	/**
	 * State machine.
	 *
	 * @var StateMachine
	 */
	private StateMachine $machine;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param SupportItems     $items    Support items.
	 * @param RemovalDecisions $removals Removal decisions.
	 * @param StateMachine     $machine  State machine.
	 * @param AuditLogger      $audit    Audit logger.
	 */
	public function __construct( SupportItems $items, RemovalDecisions $removals, StateMachine $machine, AuditLogger $audit ) {
		$this->items    = $items;
		$this->removals = $removals;
		$this->machine  = $machine;
		$this->audit    = $audit;
	}

	/**
	 * Screen for this site.
	 */
	public static function for_site(): self {
		$audit = AuditLogger::for_site();
		return new self( new SupportItems(), RemovalDecisions::for_site(), new StateMachine( null, $audit ), $audit );
	}

	/**
	 * Register hooks, in the admin only.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register_admin' ), 1 );
	}

	/**
	 * Register the screen and its actions.
	 */
	public static function register_admin(): void {
		if ( ! is_admin() ) {
			return;
		}
		$registry = ScreenRegistry::shared();
		$registry->register(
			self::SCREEN,
			__( 'Requests and reports', 'whoiswho' ),
			self::CAP,
			static function (): void {
				echo self::for_site()->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			}
		);
		$registry->hook();
		$handler = static function ( Request $request ): void {
			self::for_site()->handle( $request );
		};
		ActionGuard::register( self::ACTION, self::CAP, $handler );
		ActionGuard::register( self::ACTION_HIDE, 'wiw_bulk_unpublish', $handler );
		ActionGuard::register( self::ACTION_HIDE_ED, 'wiw_unpublish_profiles', $handler );
	}

	/**
	 * Items of the three kinds, soonest due first.
	 *
	 * @param string $kind   Item type to keep, or empty.
	 * @param string $status Status to keep, or empty.
	 * @return array<int, array<string, mixed>> Rows with kind (label) and breach added.
	 * @throws \DomainException Without the capability.
	 */
	public function rows( string $kind = '', string $status = '' ): array {
		$this->require_view();
		$types = isset( self::KINDS[ $kind ] ) ? array( $kind ) : array_keys( self::KINDS );
		$rows  = $this->items->list_by_types( $types, in_array( $status, ItemStatus::all(), true ) ? $status : '', self::LIMIT );
		return array_map( array( $this, 'decorate' ), $rows );
	}

	/**
	 * One item of these kinds, or null.
	 *
	 * @param int $id Item id.
	 * @return array<string, mixed>|null
	 * @throws \DomainException Without the capability.
	 */
	public function item( int $id ): ?array {
		$this->require_view();
		$item = $this->items->get( $id );
		return null !== $item && isset( self::KINDS[ (string) $item['item_type'] ] ) ? $this->decorate( $item ) : null;
	}

	/**
	 * Hide the reported profile now (unpublish) and mark the report in progress.
	 *
	 * @param int $id Report item id.
	 * @throws \DomainException Without wiw_bulk_unpublish or wiw_unpublish_profiles.
	 * @throws \InvalidArgumentException For an item that is not an open report about a profile, or a profile that cannot be hidden.
	 */
	public function hide_reported_profile( int $id ): void {
		if ( ! current_user_can( 'wiw_bulk_unpublish' ) && ! current_user_can( 'wiw_unpublish_profiles' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to hide profiles.' );
		}
		$item = $this->open_item( $id, ItemType::REPORT );
		if ( null === $item['profile_id'] ) {
			throw new \InvalidArgumentException( 'The report is not about a profile.' );
		}
		$profile = (int) $item['profile_id'];
		$state   = ( new StateRepository() )->pub_state( $profile );
		if ( PubState::UNPUBLISHED !== $state ) {
			if ( null === $state || ! StateMachine::can_move( $state, PubState::UNPUBLISHED ) ) {
				throw new \InvalidArgumentException( 'That profile cannot be hidden from its current state.' );
			}
			$this->machine->transition( $profile, PubState::UNPUBLISHED, 'Report ' . (string) $item['reference'], (int) get_current_user_id() );
		}
		$this->items->set_status( $id, ItemStatus::IN_PROGRESS );
		$this->log( 'report.hidden', $id, (string) $item['status'], ItemStatus::IN_PROGRESS, array( 'profile' => $profile ) );
	}

	/**
	 * Dismiss a report: no action is needed.
	 *
	 * @param int $id Report item id.
	 * @throws \DomainException Without wiw_handle_disputes.
	 */
	public function dismiss_report( int $id ): void {
		$this->close( $id, ItemType::REPORT, ItemStatus::DECLINED, 'report.dismissed', true );
	}

	/**
	 * Resolve a report: the issue is dealt with.
	 *
	 * @param int $id Report item id.
	 * @throws \DomainException Without wiw_handle_disputes.
	 */
	public function resolve_report( int $id ): void {
		$this->close( $id, ItemType::REPORT, ItemStatus::RESOLVED, 'report.resolved', true );
	}

	/**
	 * Close a data request once it has been fulfilled.
	 *
	 * @param int $id Data request item id.
	 * @throws \DomainException Without wiw_handle_requests.
	 */
	public function close_data_request( int $id ): void {
		$this->close( $id, ItemType::DATA_REQUEST, ItemStatus::RESOLVED, 'data_request.closed', false );
	}

	/**
	 * Removal valid: hide and suppress.
	 *
	 * @param int $id Removal item id.
	 * @throws \DomainException Without wiw_handle_disputes.
	 * @throws \InvalidArgumentException For an item that is not an open removal.
	 */
	public function uphold_removal( int $id ): void {
		$this->require_view();
		$this->removals->uphold( $id );
	}

	/**
	 * Removal not valid: decline with a reason.
	 *
	 * @param int    $id     Removal item id.
	 * @param string $reason Reason sent to the requester.
	 * @throws \DomainException Without wiw_handle_disputes.
	 * @throws \InvalidArgumentException For a missing reason or an item that is not an open removal.
	 */
	public function decline_removal( int $id, string $reason ): void {
		$this->require_view();
		$this->removals->decline( $id, $reason );
	}

	/**
	 * Escalate a dispute to an Administrator: set it in progress and assign it to one.
	 *
	 * @param int $id Item id.
	 * @throws \InvalidArgumentException For an item that is not open, or when there is no Administrator.
	 */
	public function escalate( int $id ): void {
		$this->require_view();
		$item = $this->items->get( $id );
		if ( null === $item || ! isset( self::KINDS[ (string) $item['item_type'] ] ) || ItemStatus::is_closed( (string) $item['status'] ) ) {
			throw new \InvalidArgumentException( 'That request cannot be escalated.' );
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'fields'  => 'ID',
			)
		);
		if ( array() === $admins ) {
			throw new \InvalidArgumentException( 'There is no Administrator to escalate to.' );
		}
		$this->items->set_status( $id, ItemStatus::IN_PROGRESS );
		$this->items->assign( $id, (int) $admins[0] );
		$this->log( 'request.escalated', $id, (string) $item['status'], ItemStatus::IN_PROGRESS, array( 'owner' => (int) $admins[0] ) );
	}

	/**
	 * Admin-post handler for every staff action (capability and nonce already checked by ActionGuard).
	 *
	 * @param Request $request Request.
	 */
	public function handle( Request $request ): void {
		$id = $request->int( 'item' );
		try {
			switch ( $request->string( 'op' ) ) {
				case 'hide':
					$this->hide_reported_profile( $id );
					break;
				case 'dismiss':
					$this->dismiss_report( $id );
					break;
				case 'resolve':
					$this->resolve_report( $id );
					break;
				case 'close':
					$this->close_data_request( $id );
					break;
				case 'uphold':
					$this->uphold_removal( $id );
					break;
				case 'decline':
					$this->decline_removal( $id, $request->text( 'reason' ) );
					break;
				case 'escalate':
					$this->escalate( $id );
					break;
				case 'send_info':
					$kinds = array();
					foreach ( array_keys( InformationSender::KINDS ) as $kind ) {
						if ( $request->has( 'kind_' . $kind ) ) {
							$kinds[] = $kind;
						}
					}
					InformationSender::for_site()->send( $id, $kinds );
					break;
				default:
					break;
			}//end switch
		} catch ( \InvalidArgumentException | \DomainException $e ) {
			unset( $e );
		}//end try
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SCREEN . '&item=' . $id ) );
		exit;
	}

	/**
	 * The screen markup: the list, or one item when ?item= names one.
	 *
	 * @param Request $request Request carrying the filters and the item.
	 */
	public function html( Request $request ): string {
		$item_id = $request->int( 'item' );
		if ( $item_id > 0 ) {
			$item = $this->item( $item_id );
			if ( null !== $item ) {
				return $this->detail_html( $item );
			}
		}
		return $this->list_html( $request->string( 'kind' ), $request->string( 'status' ) );
	}

	/**
	 * The list.
	 *
	 * @param string $kind   Kind filter.
	 * @param string $status Status filter.
	 */
	private function list_html( string $kind, string $status ): string {
		$rows = $this->rows( $kind, $status );
		$out  = '<div class="wrap"><h1>' . esc_html__( 'Requests and reports', 'whoiswho' ) . '</h1>';
		$out .= '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out .= '<label>' . esc_html__( 'Kind', 'whoiswho' ) . ' <select name="kind"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
		foreach ( self::KINDS as $type => $label ) {
			$out .= '<option value="' . esc_attr( $type ) . '"' . selected( $kind, $type, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$out .= '</select></label> <label>' . esc_html__( 'Status', 'whoiswho' ) . ' <select name="status"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
		foreach ( ItemStatus::all() as $option ) {
			$out .= '<option value="' . esc_attr( $option ) . '"' . selected( $status, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		$out .= '</select></label> <button class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
		$out .= '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Reference', 'Kind', 'Status', 'Due', 'Breach' ) as $heading ) {
			$out .= '<th>' . esc_html( $heading ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="5">' . esc_html__( 'No requests or reports.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$out .= '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN . '&item=' . (int) $row['id'] ) ) . '">' . esc_html( (string) $row['reference'] ) . '</a></td>';
			$out .= '<td>' . esc_html( (string) $row['kind'] ) . '</td><td>' . esc_html( (string) $row['status'] ) . '</td>';
			$out .= '<td>' . esc_html( (string) $row['due_at'] ) . '</td><td>' . ( $row['breach'] ? esc_html__( 'Overdue', 'whoiswho' ) : '' ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * One item with the actions for its kind.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	private function detail_html( array $item ): string {
		$id   = (int) $item['id'];
		$out  = '<div class="wrap"><h1>' . esc_html( (string) $item['reference'] ) . ' ' . esc_html( (string) $item['kind'] ) . '</h1>';
		$out .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN ) ) . '">' . esc_html__( 'Back to the list', 'whoiswho' ) . '</a></p>';
		$out .= '<table class="widefat striped"><tbody>';
		foreach ( array(
			'Name'     => 'requester_name',
			'Email'    => 'requester_email',
			'Status'   => 'status',
			'Due'      => 'due_at',
			'Received' => 'created_at',
			'Message'  => 'message',
		) as $label => $key ) {
			$out .= '<tr><th>' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) $item[ $key ] ) ) . '</td></tr>';
		}
		if ( null !== $item['profile_id'] ) {
			$out .= '<tr><th>' . esc_html__( 'Profile', 'whoiswho' ) . '</th><td>' . esc_html( (string) get_the_title( (int) $item['profile_id'] ) ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
		if ( ItemStatus::is_closed( (string) $item['status'] ) ) {
			return $out . '<p>' . esc_html__( 'This item is closed.', 'whoiswho' ) . '</p></div>';
		}
		$out .= '<h2>' . esc_html__( 'Actions', 'whoiswho' ) . '</h2>';
		switch ( (string) $item['item_type'] ) {
			case ItemType::REMOVAL:
				$out .= $this->button( $id, 'uphold', __( 'Valid: hide and suppress', 'whoiswho' ), self::ACTION );
				$out .= $this->button( $id, 'decline', __( 'Not valid', 'whoiswho' ), self::ACTION, '<label>' . esc_html__( 'Reason', 'whoiswho' ) . ' <input type="text" name="reason" maxlength="500" required /></label> ' );
				break;
			case ItemType::REPORT:
				$out .= $this->button( $id, 'hide', __( 'Hide the profile now', 'whoiswho' ), current_user_can( 'wiw_bulk_unpublish' ) ? self::ACTION_HIDE : self::ACTION_HIDE_ED ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
				$out .= $this->button( $id, 'dismiss', __( 'Dismiss', 'whoiswho' ), self::ACTION );
				$out .= $this->button( $id, 'resolve', __( 'Resolve', 'whoiswho' ), self::ACTION );
				break;
			default:
				$out .= $this->button( $id, 'close', __( 'Close: fulfilled', 'whoiswho' ), self::ACTION );
		}
		$out .= $this->button( $id, 'escalate', __( 'Escalate to the Administrator', 'whoiswho' ), self::ACTION );
		if ( '' !== (string) $item['requester_email'] ) {
			$boxes = '';
			foreach ( InformationSender::KINDS as $kind => $label ) {
				$boxes .= '<label style="margin-right:1em"><input type="checkbox" name="kind_' . esc_attr( $kind ) . '" value="1" /> ' . esc_html( $label ) . '</label>';
			}
			$out .= '<h2>' . esc_html__( 'Send information to the requester', 'whoiswho' ) . '</h2>'
				. $this->button( $id, 'send_info', __( 'Send', 'whoiswho' ), self::ACTION, $boxes );
		}
		return $out . '</div>';
	}

	/**
	 * One action form.
	 *
	 * @param int    $id     Item id.
	 * @param string $op     Operation.
	 * @param string $label  Button label.
	 * @param string $action Admin-post action.
	 * @param string $extra  Already escaped extra fields.
	 */
	private function button( int $id, string $op, string $label, string $action, string $extra = '' ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:1em">'
			. '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( $action ) ) . '" />'
			. '<input type="hidden" name="item" value="' . esc_attr( (string) $id ) . '" /><input type="hidden" name="op" value="' . esc_attr( $op ) . '" />'
			. $extra . '<button class="button">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Close an open item of one type.
	 *
	 * @param int    $id       Item id.
	 * @param string $type     Required type.
	 * @param string $status   Closing status.
	 * @param string $action   Audit action.
	 * @param bool   $disputes Needs wiw_handle_disputes rather than wiw_handle_requests.
	 * @throws \DomainException Without the capability.
	 */
	private function close( int $id, string $type, string $status, string $action, bool $disputes ): void {
		if ( ! current_user_can( $disputes ? self::CAP_DISPUTES : self::CAP ) ) {
			throw new \DomainException( 'Not allowed to decide this item.' );
		}
		$item = $this->open_item( $id, $type );
		$this->items->set_status( $id, $status );
		$this->log( $action, $id, (string) $item['status'], $status, array() );
	}

	/**
	 * An open item of the given type.
	 *
	 * @param int    $id   Item id.
	 * @param string $type Required type.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException When missing, of another type, or closed.
	 */
	private function open_item( int $id, string $type ): array {
		$item = $this->items->get( $id );
		if ( null === $item || $type !== $item['item_type'] ) {
			throw new \InvalidArgumentException( 'That is not the right kind of item.' );
		}
		if ( ItemStatus::is_closed( (string) $item['status'] ) ) {
			throw new \InvalidArgumentException( 'That item is already closed.' );
		}
		return $item;
	}

	/**
	 * Audit a change (ids and statuses only).
	 *
	 * @param string               $action Audit action.
	 * @param int                  $id     Item id.
	 * @param string               $from   Old status.
	 * @param string               $to     New status.
	 * @param array<string, mixed> $extra  More ids.
	 */
	private function log( string $action, int $id, string $from, string $to, array $extra ): void {
		$this->audit->log(
			$action,
			array(
				'type' => 'support_item',
				'id'   => $id,
			),
			array( 'status' => $from ),
			array_merge( array( 'status' => $to ), $extra )
		);
	}

	/**
	 * Add the kind label and the breach flag.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private function decorate( array $row ): array {
		$row['kind']   = self::KINDS[ (string) $row['item_type'] ] ?? (string) $row['item_type'];
		$row['due_at'] = null !== $row['due_at'] ? $row['due_at'] : $row['ack_due_at'];
		$row['breach'] = ! ItemStatus::is_closed( (string) $row['status'] ) && $this->items->is_breach( $row );
		return $row;
	}

	/**
	 * Only holders of the capability may use the screen.
	 *
	 * @throws \DomainException Without it.
	 */
	private function require_view(): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to use the requests screen.' );
		}
	}
}
