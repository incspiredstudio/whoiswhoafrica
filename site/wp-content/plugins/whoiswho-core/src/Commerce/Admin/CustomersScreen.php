<?php
/**
 * The Customers screen (REQ-205, REQ-177, REQ-187): the staff list of customers with their external id, name and segment, a search,
 * an add and edit form, internal notes and the enquiry each customer came from. A customer can be created from an Enquiry item,
 * which copies the name, organisation and contact and links the two. Held by the customer service's capability (wiw_support_inbox);
 * a user without it gets a 403 and no menu entry. Every write goes through CustomerService, which audits field names only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce\Admin;

use WhoIsWho\Core\Commerce\CustomerExport;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\CustomerService;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Customers screen.
 */
final class CustomersScreen {

	public const SCREEN        = 'wiw-customers';
	public const CAP           = CustomerService::CAP;
	public const SAVE_ACTION   = 'wiw_customer_save';
	public const FROM_ENQUIRY  = 'wiw_customer_from_enquiry';
	public const LIMIT         = 200;
	public const ENQUIRY_LIMIT = 100;

	/**
	 * Repository.
	 *
	 * @var CustomerRepository
	 */
	private CustomerRepository $customers;

	/**
	 * Service.
	 *
	 * @var CustomerService
	 */
	private CustomerService $service;

	/**
	 * Support items.
	 *
	 * @var SupportItems
	 */
	private SupportItems $items;

	/**
	 * Constructor.
	 *
	 * @param CustomerRepository $customers Repository.
	 * @param CustomerService    $service   Service.
	 * @param SupportItems       $items     Support items.
	 */
	public function __construct( CustomerRepository $customers, CustomerService $service, SupportItems $items ) {
		$this->customers = $customers;
		$this->service   = $service;
		$this->items     = $items;
	}

	/**
	 * Screen for this site.
	 */
	public static function for_site(): self {
		$repository = new CustomerRepository();
		$items      = new SupportItems();
		return new self( $repository, new CustomerService( $repository, AuditLogger::for_site(), $items ), $items );
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
			__( 'Customers', 'whoiswho' ),
			self::CAP,
			static function (): void {
				echo self::for_site()->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			}
		);
		$registry->hook();
		ActionGuard::register(
			self::SAVE_ACTION,
			self::CAP,
			static function ( Request $request ): void {
				self::for_site()->handle_save( $request );
			}
		);
		ActionGuard::register(
			self::FROM_ENQUIRY,
			self::CAP,
			static function ( Request $request ): void {
				self::for_site()->handle_from_enquiry( $request );
			}
		);
	}

	/**
	 * Customers, newest first, optionally searched.
	 *
	 * @param string $search Search text.
	 * @return array<int, array<string, mixed>>
	 * @throws \DomainException Without the capability.
	 */
	public function rows( string $search = '' ): array {
		$this->require_capability();
		return $this->customers->search( $search, self::LIMIT );
	}

	/**
	 * Enquiries that have no customer yet, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 * @throws \DomainException Without the capability.
	 */
	public function open_enquiries(): array {
		$this->require_capability();
		$out = array();
		foreach ( $this->items->list_by_types( array( ItemType::ENQUIRY ), '', self::ENQUIRY_LIMIT ) as $item ) {
			if ( null === $this->customers->find_by_enquiry( (int) $item['id'] ) ) {
				$out[] = $item;
			}
		}
		usort( $out, static fn ( array $a, array $b ): int => (int) $b['id'] <=> (int) $a['id'] );
		return $out;
	}

	/**
	 * Create a customer from an enquiry, linked back to it.
	 *
	 * @param int $enquiry_id Support item id.
	 * @return int Customer id.
	 * @throws \DomainException Without the capability or for a non-enquiry.
	 */
	public function create_from_enquiry( int $enquiry_id ): int {
		return $this->service->create_from_enquiry( $enquiry_id );
	}

	/**
	 * Admin-post handler for add and edit.
	 *
	 * @param Request $request Request.
	 */
	public function handle_save( Request $request ): void {
		$id     = $request->int( 'customer' );
		$fields = array(
			'name'          => $request->text( 'name' ),
			'segment'       => $request->text( 'segment' ),
			'contact_email' => sanitize_email( $request->string( 'contact_email' ) ),
			'contact_phone' => $request->text( 'contact_phone' ),
			'notes'         => $request->text( 'notes' ),
		);
		try {
			if ( $id > 0 ) {
				$this->service->update( $id, $fields );
			} else {
				$id = $this->service->create( $fields );
			}
		} catch ( \LogicException $e ) {
			unset( $e );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SCREEN . ( $id > 0 ? '&customer=' . $id : '' ) ) );
		exit;
	}

	/**
	 * Admin-post handler for create-from-enquiry.
	 *
	 * @param Request $request Request.
	 */
	public function handle_from_enquiry( Request $request ): void {
		$id = 0;
		try {
			$id = $this->create_from_enquiry( $request->int( 'enquiry' ) );
		} catch ( \DomainException $e ) {
			unset( $e );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SCREEN . ( $id > 0 ? '&customer=' . $id : '' ) ) );
		exit;
	}

	/**
	 * The screen markup: the list, or one customer when ?customer= names one, or the add form for ?customer=new.
	 *
	 * @param Request $request Request.
	 */
	public function html( Request $request ): string {
		$this->require_capability();
		if ( 'new' === $request->string( 'customer' ) ) {
			return $this->form_html( null );
		}
		$id = $request->int( 'customer' );
		if ( $id > 0 ) {
			$row = $this->customers->get( $id );
			if ( null !== $row ) {
				return $this->form_html( $row );
			}
		}
		return $this->list_html( $request->text( 's' ) );
	}

	/**
	 * The list, the search and the create-from-enquiry control.
	 *
	 * @param string $search Search text.
	 */
	private function list_html( string $search ): string {
		$rows = $this->rows( $search );
		$out  = '<div class="wrap"><h1>' . esc_html__( 'Customers', 'whoiswho' ) . '</h1>';
		$out .= '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN . '&customer=new' ) ) . '">' . esc_html__( 'Add customer', 'whoiswho' ) . '</a></p>';
		$out .= self::export_html();
		$out .= '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out .= '<label>' . esc_html__( 'Search', 'whoiswho' ) . ' <input type="search" name="s" value="' . esc_attr( $search ) . '" /></label> <button class="button">' . esc_html__( 'Search', 'whoiswho' ) . '</button></form>';
		$out .= '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'External ID', 'Name', 'Segment' ) as $heading ) {
			$out .= '<th>' . esc_html( $heading ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="3">' . esc_html__( 'No customers.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$out .= '<tr><td>' . esc_html( (string) $row['external_id'] ) . '</td>';
			$out .= '<td><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN . '&customer=' . (int) $row['id'] ) ) . '">' . esc_html( (string) $row['name'] ) . '</a></td>';
			$out .= '<td>' . esc_html( (string) $row['segment'] ) . '</td></tr>';
		}
		$out .= '</tbody></table>';

		$enquiries = $this->open_enquiries();
		$out      .= '<h2>' . esc_html__( 'Create from an enquiry', 'whoiswho' ) . '</h2>';
		if ( array() === $enquiries ) {
			return $out . '<p>' . esc_html__( 'Every enquiry already has a customer.', 'whoiswho' ) . '</p></div>';
		}
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::FROM_ENQUIRY ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::FROM_ENQUIRY ) ) . '" />';
		$out .= '<label>' . esc_html__( 'Enquiry', 'whoiswho' ) . ' <select name="enquiry">';
		foreach ( $enquiries as $item ) {
			$label = (string) $item['reference'] . ' ' . ( '' !== (string) $item['organisation'] ? (string) $item['organisation'] : (string) $item['requester_name'] );
			$out  .= '<option value="' . esc_attr( (string) $item['id'] ) . '">' . esc_html( $label ) . '</option>';
		}
		return $out . '</select></label> <button class="button">' . esc_html__( 'Create customer', 'whoiswho' ) . '</button></form></div>';
	}

	/**
	 * The two CSV export buttons, shown only to someone who holds wiw_export_data (REQ-077).
	 */
	private static function export_html(): string {
		if ( ! current_user_can( CustomerExport::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return '';
		}
		$out = '<p>';
		foreach ( array(
			CustomerExport::CUSTOMERS_ACTION => __( 'Export customers as CSV', 'whoiswho' ),
			CustomerExport::ENQUIRIES_ACTION => __( 'Export enquiries as CSV', 'whoiswho' ),
		) as $action => $label ) {
			$out .= '<form method="post" style="display:inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( $action ) ) . '" /><button class="button">' . esc_html( $label ) . '</button></form> ';
		}
		return $out . '</p>';
	}

	/**
	 * The add or edit form, with the linked enquiry when there is one.
	 *
	 * @param array<string, mixed>|null $row Customer, or null for a new one.
	 */
	private function form_html( ?array $row ): string {
		$id   = null === $row ? 0 : (int) $row['id'];
		$out  = '<div class="wrap"><h1>' . esc_html( null === $row ? __( 'Add customer', 'whoiswho' ) : (string) $row['name'] ) . '</h1>';
		$out .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN ) ) . '">' . esc_html__( 'Back to the list', 'whoiswho' ) . '</a></p>';
		if ( null !== $row ) {
			$out .= '<p>' . esc_html__( 'External ID', 'whoiswho' ) . ': <code>' . esc_html( (string) $row['external_id'] ) . '</code></p>';
			$out .= $this->enquiry_html( $row );
		}
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::SAVE_ACTION ) ) . '" /><input type="hidden" name="customer" value="' . esc_attr( (string) $id ) . '" />';
		$out .= '<table class="form-table"><tbody>';
		foreach ( array(
			'name'          => array( 'Name', 'text' ),
			'segment'       => array( 'Segment', 'text' ),
			'contact_email' => array( 'Contact email', 'email' ),
			'contact_phone' => array( 'Contact phone', 'text' ),
		) as $key => $def ) {
			$out .= '<tr><th><label for="wiw-c-' . esc_attr( $key ) . '">' . esc_html( $def[0] ) . '</label></th><td><input class="regular-text" id="wiw-c-' . esc_attr( $key ) . '" type="' . esc_attr( $def[1] ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( null === $row ? '' : (string) $row[ $key ] ) . '" /></td></tr>';
		}
		$out .= '<tr><th><label for="wiw-c-notes">' . esc_html__( 'Internal notes (never public)', 'whoiswho' ) . '</label></th><td><textarea id="wiw-c-notes" name="notes" rows="5" cols="60" maxlength="5000">' . esc_textarea( null === $row ? '' : (string) $row['notes'] ) . '</textarea></td></tr>';
		return $out . '</tbody></table><button class="button button-primary">' . esc_html__( 'Save', 'whoiswho' ) . '</button></form></div>';
	}

	/**
	 * The enquiry a customer came from, as a link back.
	 *
	 * @param array<string, mixed> $row Customer.
	 */
	private function enquiry_html( array $row ): string {
		$enquiry_id = (int) ( $row['enquiry_id'] ?? 0 );
		$item       = $enquiry_id > 0 ? $this->items->get( $enquiry_id ) : null;
		if ( null === $item ) {
			return '';
		}
		$url = admin_url( 'admin.php?page=wiw-enquiries&item=' . $enquiry_id );
		return '<p>' . esc_html__( 'Linked enquiry', 'whoiswho' ) . ': <a href="' . esc_url( $url ) . '">' . esc_html( (string) $item['reference'] ) . '</a></p>';
	}

	/**
	 * Only holders of the capability may read or change.
	 *
	 * @throws \DomainException Without it.
	 */
	private function require_capability(): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to use the customers screen.' );
		}
	}
}
