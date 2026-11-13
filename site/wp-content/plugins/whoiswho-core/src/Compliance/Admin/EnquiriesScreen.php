<?php
/**
 * The Enquiries screen (REQ-089, REQ-177, REQ-187, REQ-244): the staff list where enquiries from Get listed, Contact and profile
 * pages are worked. It lists reference, name, organisation, interest, source, age, status and owner (an enquiry has no due date),
 * filters by status and source, and opens one item with its full detail. Staff set the owner and status and add internal notes
 * that are never public. Every change is audited with ids and statuses only, never names, addresses or note text. Held by
 * wiw_support_inbox; a user without it gets a 403 and no menu entry.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Admin;

use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Notes\NotesService;

/**
 * Enquiries screen.
 */
final class EnquiriesScreen {

	public const SCREEN      = 'wiw-enquiries';
	public const CAP         = 'wiw_support_inbox';
	public const ACTION      = 'wiw_enquiry_update';
	public const NOTE_ACTION = 'wiw_enquiry_note';
	public const NOTE_TYPE   = 'enquiry';
	public const SOURCES     = array( 'get-listed', 'contact', 'profile' );
	public const LIMIT       = 200;

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
	 * Notes.
	 *
	 * @var NotesService
	 */
	private NotesService $notes;

	/**
	 * Constructor.
	 *
	 * @param SupportItems $items Support items.
	 * @param AuditLogger  $audit Audit logger.
	 * @param NotesService $notes Notes service.
	 */
	public function __construct( SupportItems $items, AuditLogger $audit, NotesService $notes ) {
		$this->items = $items;
		$this->audit = $audit;
		$this->notes = $notes;
	}

	/**
	 * Screen for this site.
	 */
	public static function for_site(): self {
		$audit = AuditLogger::for_site();
		return new self( new SupportItems(), $audit, new NotesService( $audit ) );
	}

	/**
	 * Register the screen and its actions, in the admin only.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register_admin' ), 1 );
	}

	/**
	 * Register in the admin menu.
	 */
	public static function register_admin(): void {
		if ( ! is_admin() ) {
			return;
		}
		$registry = ScreenRegistry::shared();
		$registry->register(
			self::SCREEN,
			__( 'Enquiries', 'whoiswho' ),
			self::CAP,
			static function (): void {
				echo self::for_site()->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			},
			false,
			'audience'
		);
		$registry->hook();
		ActionGuard::register(
			self::ACTION,
			self::CAP,
			static function ( Request $request ): void {
				self::for_site()->handle_update( $request );
			}
		);
		ActionGuard::register(
			self::NOTE_ACTION,
			self::CAP,
			static function ( Request $request ): void {
				self::for_site()->handle_note( $request );
			}
		);
	}

	/**
	 * Enquiries, newest first, optionally filtered.
	 *
	 * @param string $status Status filter, or empty.
	 * @param string $source Source filter, or empty.
	 * @return array<int, array<string, mixed>>
	 * @throws \DomainException Without the capability.
	 */
	public function rows( string $status = '', string $source = '' ): array {
		$this->require_capability();
		$rows = $this->items->list_by_types( array( ItemType::ENQUIRY ), in_array( $status, ItemStatus::all(), true ) ? $status : '', self::LIMIT );
		if ( in_array( $source, self::SOURCES, true ) ) {
			$rows = array_values( array_filter( $rows, static fn ( array $row ): bool => $source === $row['source'] ) );
		}
		usort( $rows, static fn ( array $a, array $b ): int => (int) $b['id'] <=> (int) $a['id'] );
		return $rows;
	}

	/**
	 * One enquiry, or null when the id is not an enquiry.
	 *
	 * @param int $id Item id.
	 * @return array<string, mixed>|null
	 * @throws \DomainException Without the capability.
	 */
	public function item( int $id ): ?array {
		$this->require_capability();
		$item = $this->items->get( $id );
		return null !== $item && ItemType::ENQUIRY === $item['item_type'] ? $item : null;
	}

	/**
	 * Age of an item in whole days.
	 *
	 * @param array<string, mixed> $item Row.
	 * @param int                  $now  Unix time.
	 */
	public static function age_days( array $item, int $now ): int {
		$created = strtotime( (string) $item['created_at'] . ' UTC' );
		return false === $created ? 0 : max( 0, (int) floor( ( $now - $created ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Set an enquiry's status and owner. Only changes are written and audited (ids and statuses, no personal data).
	 *
	 * @param int    $id     Item id.
	 * @param string $status New status.
	 * @param int    $owner  Owner user id, or 0 for none.
	 * @throws \InvalidArgumentException For an unknown enquiry, status or owner, or without the capability.
	 */
	public function update( int $id, string $status, int $owner ): void {
		$item = $this->item( $id );
		if ( null === $item ) {
			throw new \InvalidArgumentException( 'That enquiry does not exist.' );
		}
		if ( ! in_array( $status, ItemStatus::all(), true ) ) {
			throw new \InvalidArgumentException( 'Unknown status.' );
		}
		if ( $owner > 0 && ( ! get_userdata( $owner ) instanceof \WP_User || ! user_can( $owner, self::CAP ) ) ) {
			throw new \InvalidArgumentException( 'The owner must be someone who can work enquiries.' );
		}
		$before = array(
			'status' => (string) $item['status'],
			'owner'  => (int) $item['owner_id'],
		);
		$after  = array(
			'status' => $status,
			'owner'  => $owner,
		);
		if ( $before === $after ) {
			return;
		}
		if ( $before['status'] !== $status ) {
			$this->items->set_status( $id, $status );
		}
		if ( $before['owner'] !== $owner ) {
			$this->items->assign( $id, $owner );
		}
		$this->audit->log(
			'enquiry.update',
			array(
				'type' => 'support_item',
				'id'   => $id,
			),
			$before,
			$after
		);
	}

	/**
	 * Add an internal note. Notes never reach a public page.
	 *
	 * @param int    $id   Item id.
	 * @param string $body Note text.
	 * @throws \InvalidArgumentException For an unknown enquiry or an invalid note, or without the capability.
	 */
	public function add_note( int $id, string $body ): void {
		if ( null === $this->item( $id ) ) {
			throw new \InvalidArgumentException( 'That enquiry does not exist.' );
		}
		$this->notes->add( self::NOTE_TYPE, $id, $body, self::CAP );
	}

	/**
	 * Admin-post handler for status and owner (capability and nonce already checked by ActionGuard).
	 *
	 * @param Request $request Request.
	 */
	public function handle_update( Request $request ): void {
		$id = $request->int( 'item' );
		try {
			$this->update( $id, $request->string( 'status' ), $request->int( 'owner' ) );
		} catch ( \InvalidArgumentException $e ) {
			unset( $e );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SCREEN . '&item=' . $id ) );
		exit;
	}

	/**
	 * Admin-post handler for a note.
	 *
	 * @param Request $request Request.
	 */
	public function handle_note( Request $request ): void {
		$id = $request->int( 'item' );
		try {
			$this->add_note( $id, $request->string( 'note' ) );
		} catch ( \InvalidArgumentException $e ) {
			unset( $e );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SCREEN . '&item=' . $id ) );
		exit;
	}

	/**
	 * The screen markup: the list, or one item when ?item= names an enquiry.
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
		return $this->list_html( $request->string( 'status' ), $request->string( 'source' ) );
	}

	/**
	 * The list.
	 *
	 * @param string $status Status filter.
	 * @param string $source Source filter.
	 */
	private function list_html( string $status, string $source ): string {
		$rows = $this->rows( $status, $source );
		$now  = time();
		$out  = '<div class="wrap"><h1>' . esc_html__( 'Enquiries', 'whoiswho' ) . '</h1>';
		$out .= '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out .= '<label>' . esc_html__( 'Status', 'whoiswho' ) . ' <select name="status"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
		foreach ( ItemStatus::all() as $option ) {
			$out .= '<option value="' . esc_attr( $option ) . '"' . selected( $status, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		$out .= '</select></label> <label>' . esc_html__( 'Source', 'whoiswho' ) . ' <select name="source"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
		foreach ( self::SOURCES as $option ) {
			$out .= '<option value="' . esc_attr( $option ) . '"' . selected( $source, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		$out .= '</select></label> <button class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
		$out .= '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Reference', 'Name', 'Organisation', 'Interested in', 'Source', 'Age', 'Status', 'Owner' ) as $heading ) {
			$out .= '<th>' . esc_html( $heading ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="8">' . esc_html__( 'No enquiries.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$owner = (int) $row['owner_id'] > 0 ? get_userdata( (int) $row['owner_id'] ) : false;
			$out  .= '<tr>';
			$out  .= '<td><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN . '&item=' . (int) $row['id'] ) ) . '">' . esc_html( (string) $row['reference'] ) . '</a></td>';
			$out  .= '<td>' . esc_html( (string) $row['requester_name'] ) . '</td>';
			$out  .= '<td>' . esc_html( (string) $row['organisation'] ) . '</td>';
			$out  .= '<td>' . esc_html( (string) $row['interest'] ) . '</td>';
			$out  .= '<td>' . esc_html( (string) $row['source'] ) . '</td>';
			/* translators: %d: number of days. */
			$out .= '<td>' . esc_html( sprintf( _n( '%d day', '%d days', self::age_days( $row, $now ), 'whoiswho' ), self::age_days( $row, $now ) ) ) . '</td>';
			$out .= '<td>' . esc_html( (string) $row['status'] ) . '</td>';
			$out .= '<td>' . esc_html( $owner instanceof \WP_User ? $owner->display_name : '' ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * One enquiry with its controls and internal notes.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	private function detail_html( array $item ): string {
		$id   = (int) $item['id'];
		$out  = '<div class="wrap"><h1>' . esc_html( (string) $item['reference'] ) . '</h1>';
		$out .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN ) ) . '">' . esc_html__( 'Back to the list', 'whoiswho' ) . '</a></p>';
		$out .= '<table class="widefat striped"><tbody>';
		foreach ( array(
			'Name'          => 'requester_name',
			'Organisation'  => 'organisation',
			'Interested in' => 'interest',
			'Email'         => 'requester_email',
			'Phone'         => 'requester_phone',
			'Source'        => 'source',
			'Received'      => 'created_at',
			'Message'       => 'message',
		) as $label => $key ) {
			$out .= '<tr><th>' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) $item[ $key ] ) ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
		$out .= '<h2>' . esc_html__( 'Status and owner', 'whoiswho' ) . '</h2>';
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" /><input type="hidden" name="item" value="' . esc_attr( (string) $id ) . '" />';
		$out .= '<label>' . esc_html__( 'Status', 'whoiswho' ) . ' <select name="status">';
		foreach ( ItemStatus::all() as $option ) {
			$out .= '<option value="' . esc_attr( $option ) . '"' . selected( (string) $item['status'], $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		$out .= '</select></label> <label>' . esc_html__( 'Owner', 'whoiswho' ) . ' <select name="owner"><option value="0">' . esc_html__( 'Nobody', 'whoiswho' ) . '</option>';
		foreach ( get_users( array( 'capability' => self::CAP ) ) as $user ) {
			$out .= '<option value="' . esc_attr( (string) $user->ID ) . '"' . selected( (int) $item['owner_id'], (int) $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
		}
		$out .= '</select></label> <button class="button button-primary">' . esc_html__( 'Save', 'whoiswho' ) . '</button></form>';
		$out .= '<h2>' . esc_html__( 'Internal notes (never public)', 'whoiswho' ) . '</h2><ul>';
		foreach ( $this->notes->list( self::NOTE_TYPE, $id, self::CAP ) as $note ) {
			$out .= '<li>' . esc_html( $note['created_at'] ) . ': ' . nl2br( esc_html( $note['body'] ) ) . '</li>';
		}
		$out .= '</ul><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::NOTE_ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::NOTE_ACTION ) ) . '" /><input type="hidden" name="item" value="' . esc_attr( (string) $id ) . '" />';
		$out .= '<p><textarea name="note" rows="3" cols="60" maxlength="5000"></textarea></p><button class="button">' . esc_html__( 'Add note', 'whoiswho' ) . '</button></form></div>';
		return $out;
	}

	/**
	 * Only holders of the capability may read or change.
	 *
	 * @throws \DomainException Without it.
	 */
	private function require_capability(): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to use the enquiries screen.' );
		}
	}
}
