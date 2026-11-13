<?php
/**
 * The Corrections screen (REQ-271, REQ-157, REQ-158, REQ-253, REQ-244; prototype A64 and A65). The list shows reference, profile,
 * suggestion, received, due and status, with a status filter and an overdue flag. The detail shows everything the requester sent,
 * for staff only. Apply opens the profile's edit screen with the request reference as the reason and the correction as the item
 * the edit resolves, so the edit creates a version, writes an audit row and closes the item there (M1-DIR-10). Mark resolved
 * and Decline (with a reason that is kept as a private staff note and never shown on the site or sent out) close the item,
 * audited; the requester is emailed when it is resolved. A claimed profile shows the hint that edits by its assigned manager wait
 * for review; a staff edit applies at once (owner decision). Viewing needs wiw_support_inbox; deciding needs wiw_handle_disputes.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Admin\Corrections;

use WhoIsWho\Core\Compliance\Support\EnquiryIntake;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\Admin\ProfileEditScreen;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Notes\NotesService;

/**
 * Corrections screen.
 */
final class CorrectionsScreen {

	public const SCREEN       = 'wiw-corrections';
	public const CAP          = 'wiw_support_inbox';
	public const CAP_DISPUTES = 'wiw_handle_disputes';
	public const ACTION       = 'wiw_correction_action';
	public const LIMIT        = 200;

	/**
	 * Items.
	 *
	 * @var SupportItems
	 */
	private SupportItems $items;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Notifier.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Notes.
	 *
	 * @var NotesService
	 */
	private NotesService $notes;

	/**
	 * Constructor.
	 *
	 * @param SupportItems $items    Support items.
	 * @param AuditLogger  $audit    Audit logger.
	 * @param Notifier     $notifier Notifier.
	 * @param NotesService $notes    Private staff notes.
	 */
	public function __construct( SupportItems $items, AuditLogger $audit, Notifier $notifier, NotesService $notes ) {
		$this->items    = $items;
		$this->audit    = $audit;
		$this->notifier = $notifier;
		$this->notes    = $notes;
	}

	/**
	 * Screen for this site.
	 */
	public static function for_site(): self {
		$audit = AuditLogger::for_site();
		return new self( new SupportItems(), $audit, Notifier::for_site(), new NotesService( $audit ) );
	}

	/**
	 * Register hooks, in the admin only.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register_admin' ), 1 );
	}

	/**
	 * Register the screen and its action.
	 */
	public static function register_admin(): void {
		if ( ! is_admin() ) {
			return;
		}
		$registry = ScreenRegistry::shared();
		$registry->register(
			self::SCREEN,
			__( 'Corrections', 'whoiswho' ),
			self::CAP,
			static function (): void {
				echo self::for_site()->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			}
		);
		$registry->hook();
		ActionGuard::register(
			self::ACTION,
			self::CAP_DISPUTES,
			static function ( Request $request ): void {
				self::for_site()->handle( $request );
			}
		);
	}

	/**
	 * Corrections, soonest due first.
	 *
	 * @param string $status Status to keep, or empty.
	 * @return array<int, array<string, mixed>> Rows with the profile name, the suggestion and the breach flag added.
	 * @throws \DomainException Without the capability.
	 */
	public function rows( string $status = '' ): array {
		$this->require_view();
		$rows = array();
		foreach ( $this->items->list_by_types( array( ItemType::CORRECTION ), $status, self::LIMIT ) as $row ) {
			$rows[] = $this->decorate( $row );
		}
		usort(
			$rows,
			static fn ( array $a, array $b ): int => strcmp( (string) $a['due_at'], (string) $b['due_at'] )
		);
		return $rows;
	}

	/**
	 * One correction.
	 *
	 * @param int $id Item id.
	 * @return array<string, mixed>|null Null when it is not a correction.
	 * @throws \DomainException Without the capability.
	 */
	public function item( int $id ): ?array {
		$this->require_view();
		$row = $this->items->get( $id );
		return null !== $row && ItemType::CORRECTION === $row['item_type'] ? $this->decorate( $row ) : null;
	}

	/**
	 * The edit screen address for Apply: the profile, the request reference as the reason and the item it resolves.
	 *
	 * @param array<string, mixed> $item Correction row.
	 */
	public function apply_url( array $item ): string {
		return add_query_arg(
			array(
				'reason'   => rawurlencode( (string) $item['reference'] ),
				'resolves' => (int) $item['id'],
			),
			ProfileEditScreen::url( (int) $item['profile_id'] )
		);
	}

	/**
	 * Mark resolved: the correction was dealt with. The requester is emailed.
	 *
	 * @param int $id Item id.
	 * @throws \DomainException Without wiw_handle_disputes.
	 * @throws \InvalidArgumentException For an item that is not an open correction.
	 */
	public function mark_resolved( int $id ): void {
		$item = $this->open_correction( $id );
		$this->items->set_status( $id, ItemStatus::RESOLVED );
		$this->log( 'correction.resolved', $id, (string) $item['status'], ItemStatus::RESOLVED );
		$this->tell_requester( $item, 'Your correction request has been dealt with. Thank you for helping us keep the record accurate.' );
	}

	/**
	 * Decline with a reason. The reason is a private staff note, never public and never sent out.
	 *
	 * @param int    $id     Item id.
	 * @param string $reason Why.
	 * @throws \InvalidArgumentException For a missing reason or an item that is not an open correction (a \DomainException without wiw_handle_disputes).
	 */
	public function decline( int $id, string $reason ): void {
		$item   = $this->open_correction( $id );
		$reason = trim( $reason );
		if ( '' === $reason ) {
			throw new \InvalidArgumentException( 'A reason is required.' );
		}
		$this->notes->add( 'enquiry', $id, 'Correction declined: ' . mb_substr( $reason, 0, 1000 ), self::CAP_DISPUTES );
		$this->items->set_status( $id, ItemStatus::DECLINED );
		$this->log( 'correction.declined', $id, (string) $item['status'], ItemStatus::DECLINED );
		$this->tell_requester( $item, 'We could not make the change you asked for. You can reply to this email if you want it looked at again.' );
	}

	/**
	 * Action handler (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public function handle( Request $request ): void {
		$id = $request->int( 'item' );
		try {
			switch ( $request->string( 'op' ) ) {
				case 'resolve':
					$this->mark_resolved( $id );
					break;
				case 'decline':
					$this->decline( $id, $request->text( 'reason' ) );
					break;
				default:
					break;
			}
		} catch ( \InvalidArgumentException | \DomainException $e ) {
			unset( $e );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SCREEN . '&item=' . $id ) );
		exit;
	}

	/**
	 * The screen markup: the list, or one item when ?item= names one.
	 *
	 * @param Request $request Request carrying the status filter and the item.
	 */
	public function html( Request $request ): string {
		$item_id = $request->int( 'item' );
		if ( $item_id > 0 ) {
			$item = $this->item( $item_id );
			if ( null !== $item ) {
				return $this->detail_html( $item );
			}
		}
		return $this->list_html( $request->string( 'status' ) );
	}

	/**
	 * The list.
	 *
	 * @param string $status Status filter.
	 */
	private function list_html( string $status ): string {
		$rows = $this->rows( $status );
		$out  = '<div class="wrap"><h1>' . esc_html__( 'Corrections', 'whoiswho' ) . '</h1>';
		$out .= '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out .= '<label>' . esc_html__( 'Status', 'whoiswho' ) . ' <select name="status"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
		foreach ( ItemStatus::all() as $option ) {
			$out .= '<option value="' . esc_attr( $option ) . '"' . selected( $status, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		$out .= '</select></label> <button class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
		$out .= '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Reference', 'Profile', 'Suggestion', 'Received', 'Due', 'Status', 'Overdue' ) as $heading ) {
			$out .= '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="7">' . esc_html__( 'No corrections.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$out .= '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN . '&item=' . (int) $row['id'] ) ) . '">' . esc_html( (string) $row['reference'] ) . '</a></td>';
			$out .= '<td>' . esc_html( (string) $row['profile_name'] ) . '</td><td>' . esc_html( (string) $row['suggestion'] ) . '</td>';
			$out .= '<td>' . esc_html( (string) $row['created_at'] ) . '</td><td>' . esc_html( (string) $row['due_at'] ) . '</td>';
			$out .= '<td>' . esc_html( (string) $row['status'] ) . '</td><td>' . ( $row['breach'] ? esc_html__( 'Overdue', 'whoiswho' ) : '' ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * One correction with Apply, Mark resolved and Decline.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	private function detail_html( array $item ): string {
		$id   = (int) $item['id'];
		$out  = '<div class="wrap"><h1>' . esc_html( (string) $item['reference'] ) . ' ' . esc_html__( 'Correction', 'whoiswho' ) . '</h1>';
		$out .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN ) ) . '">' . esc_html__( 'Back to the list', 'whoiswho' ) . '</a></p>';
		$out .= '<p class="description">' . esc_html__( 'Everything below is for staff only. The requester\'s details and evidence are never public.', 'whoiswho' ) . '</p>';
		$out .= '<table class="widefat striped"><tbody>';
		foreach ( array(
			'Name'         => 'requester_name',
			'Relationship' => 'requester_relationship',
			'Email'        => 'requester_email',
			'Phone'        => 'requester_phone',
			'Status'       => 'status',
			'Received'     => 'created_at',
			'Due'          => 'due_at',
			'Message'      => 'message',
		) as $label => $key ) {
			$out .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) ( $item[ $key ] ?? '' ) ) ) . '</td></tr>';
		}
		$out .= '<tr><th scope="row">' . esc_html__( 'Profile', 'whoiswho' ) . '</th><td>' . esc_html( (string) $item['profile_name'] ) . '</td></tr>';
		$out .= '</tbody></table>';
		if ( $item['claimed'] ) {
			$out .= '<div class="notice notice-info inline"><p>' . esc_html__( 'This profile is claimed. An edit by its assigned manager waits for review before it shows; an edit by staff applies at once.', 'whoiswho' ) . '</p></div>';
		}
		if ( ItemStatus::is_closed( (string) $item['status'] ) ) {
			return $out . '<p>' . esc_html__( 'This item is closed.', 'whoiswho' ) . '</p></div>';
		}
		$out .= '<h2>' . esc_html__( 'Actions', 'whoiswho' ) . '</h2>';
		if ( null !== $item['profile_id'] ) {
			$out .= '<p><a class="button button-primary" href="' . esc_url( $this->apply_url( $item ) ) . '">' . esc_html__( 'Apply: edit the profile', 'whoiswho' ) . '</a> ';
		} else {
			$out .= '<p>';
		}
		$out .= '</p>';
		$out .= $this->button( $id, 'resolve', __( 'Mark resolved', 'whoiswho' ) );
		$out .= $this->button( $id, 'decline', __( 'Decline', 'whoiswho' ), '<label>' . esc_html__( 'Reason (kept private)', 'whoiswho' ) . ' <input type="text" name="reason" maxlength="1000" required="required" /></label> ' );
		return $out . '</div>';
	}

	/**
	 * One action form.
	 *
	 * @param int    $id    Item id.
	 * @param string $op    Operation.
	 * @param string $label Button label.
	 * @param string $extra Already escaped extra fields.
	 */
	private function button( int $id, string $op, string $label, string $extra = '' ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:1em">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />'
			. '<input type="hidden" name="item" value="' . esc_attr( (string) $id ) . '" /><input type="hidden" name="op" value="' . esc_attr( $op ) . '" />'
			. $extra . '<button class="button">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * An open correction, for a user who may decide.
	 *
	 * @param int $id Item id.
	 * @return array<string, mixed>
	 * @throws \DomainException Without wiw_handle_disputes.
	 * @throws \InvalidArgumentException When missing, not a correction, or closed.
	 */
	private function open_correction( int $id ): array {
		if ( ! current_user_can( self::CAP_DISPUTES ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to decide corrections.' );
		}
		$item = $this->items->get( $id );
		if ( null === $item || ItemType::CORRECTION !== $item['item_type'] ) {
			throw new \InvalidArgumentException( 'That is not a correction.' );
		}
		if ( ItemStatus::is_closed( (string) $item['status'] ) ) {
			throw new \InvalidArgumentException( 'That item is already closed.' );
		}
		return $item;
	}

	/**
	 * Queue the closing email; the address is looked up when the job runs.
	 *
	 * @param array<string, mixed> $item Item.
	 * @param string               $lead Opening line.
	 */
	private function tell_requester( array $item, string $lead ): void {
		if ( '' === (string) $item['requester_email'] ) {
			return;
		}
		$this->notifier->send_to_record(
			Events::REQUEST_RESOLVED,
			EnquiryIntake::RECIPIENT_SOURCE,
			(int) $item['id'],
			array(
				'reference' => (string) $item['reference'],
				'id'        => (int) $item['id'],
				'lead'      => $lead,
				'reason'    => '',
			)
		);
	}

	/**
	 * Audit a change (ids and statuses only).
	 *
	 * @param string $action Audit action.
	 * @param int    $id     Item id.
	 * @param string $from   Old status.
	 * @param string $to     New status.
	 */
	private function log( string $action, int $id, string $from, string $to ): void {
		$this->audit->log(
			$action,
			array(
				'type' => 'support_item',
				'id'   => $id,
			),
			array( 'status' => $from ),
			array( 'status' => $to )
		);
	}

	/**
	 * Add the profile name, the suggestion, the claimed flag and the breach flag.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private function decorate( array $row ): array {
		$profile_id          = null !== $row['profile_id'] ? (int) $row['profile_id'] : 0;
		$state               = $profile_id > 0 ? ( new StateRepository() )->get( $profile_id ) : null;
		$row['profile_name'] = $profile_id > 0 ? (string) get_the_title( $profile_id ) : '';
		$row['claimed']      = null !== $state && (int) ( $state['claimed'] ?? 0 ) > 0;
		$row['suggestion']   = mb_strimwidth( trim( (string) preg_replace( '/\s+/', ' ', (string) $row['message'] ) ), 0, 100, '…' );
		$row['due_at']       = null !== $row['due_at'] ? $row['due_at'] : $row['ack_due_at'];
		$row['breach']       = ! ItemStatus::is_closed( (string) $row['status'] ) && $this->items->is_breach( $row );
		return $row;
	}

	/**
	 * Only holders of the capability may view the screen.
	 *
	 * @throws \DomainException Without it.
	 */
	private function require_view(): void {
		if ( ! current_user_can( self::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to use the corrections screen.' );
		}
	}
}
