<?php
/**
 * The Customers screen (REQ-205, REQ-177, REQ-187): the staff list of customers with their external id, name and segment, a search,
 * an add and edit form, internal notes and the enquiry each customer came from. A customer can be created from an Enquiry item,
 * which copies the name, organisation and contact and links the two. Held by the customer service's capability (wiw_manage_register,
 * Administrator only: customers belong to the commercial register); a user without it gets a 403 and no menu entry. Every write goes through CustomerService, which audits field names only.
 * The list is a shared staff list table (CustomersTable, REQ-275) with an Edit row action; the forms use the staff form kit.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce\Admin;

use WhoIsWho\Core\Commerce\CustomerExport;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\CustomerService;
use WhoIsWho\Core\Commerce\Invitations\Invitations;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Customers screen.
 */
final class CustomersScreen {

	public const SCREEN        = 'wiw-customers';
	public const CAP           = CustomerService::CAP;
	public const SAVE_ACTION   = 'wiw_customer_save';
	public const FROM_ENQUIRY  = 'wiw_customer_from_enquiry';
	public const INVITE        = 'wiw_customer_invite';
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
			},
			false,
			'audience'
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
		ActionGuard::register(
			self::INVITE,
			Invitations::CAP,
			static function ( Request $request ): void {
				self::for_site()->handle_invite( $request );
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
	 * Admin-post handler for issuing an invitation link (an Administrator action; the capability and nonce are already checked).
	 *
	 * @param Request $request Request.
	 */
	public function handle_invite( Request $request ): void {
		$id     = $request->int( 'customer' );
		$result = 'invited';
		try {
			Invitations::for_site()->issue( $id, sanitize_email( $request->string( 'invite_email' ) ) );
		} catch ( \DomainException $e ) {
			$result = 'invite_refused';
		}
		wp_safe_redirect( add_query_arg( 'result', $result, admin_url( 'admin.php?page=' . self::SCREEN . '&customer=' . $id ) ) );
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
		return $this->list_html( $request->text( 's' ), max( 1, $request->int( 'paged' ) ) );
	}

	/**
	 * The list, the search and the create-from-enquiry control.
	 *
	 * @param string $search Search text.
	 * @param int    $page   Page of the list.
	 */
	private function list_html( string $search, int $page = 1 ): string {
		$this->require_capability();
		$out  = '<div class="wrap wiw-staff"><h1 class="wp-heading-inline">' . esc_html__( 'Customers', 'whoiswho' ) . '</h1>';
		$out .= ' <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN . '&customer=new' ) ) . '">' . esc_html__( 'Add customer', 'whoiswho' ) . '</a><hr class="wp-header-end" />';
		$out .= self::export_html();
		$out .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="wiw-filters" role="search"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out .= '<label>' . esc_html__( 'Search', 'whoiswho' ) . ' <input type="search" name="s" value="' . esc_attr( $search ) . '" /></label> <button class="button">' . esc_html__( 'Search', 'whoiswho' ) . '</button></form>';
		$out .= ( new CustomersTable( $this->customers, $search ) )->html( new Request( array( 'paged' => (string) $page ) ) );

		$enquiries = $this->open_enquiries();
		if ( array() === $enquiries ) {
			return $out . Form::section( __( 'Create from an enquiry', 'whoiswho' ), '<p>' . esc_html__( 'Every enquiry already has a customer.', 'whoiswho' ) . '</p>', array( 'id' => 'wiw-from-enquiry' ) ) . '</div>';
		}
		$options = array();
		foreach ( $enquiries as $item ) {
			$options[ (string) $item['id'] ] = (string) $item['reference'] . ' ' . ( '' !== (string) $item['organisation'] ? (string) $item['organisation'] : (string) $item['requester_name'] );
		}
		$body  = Form::open( '<input type="hidden" name="action" value="' . esc_attr( self::FROM_ENQUIRY ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::FROM_ENQUIRY ) ) . '" />', array( 'class' => 'wiw-inline-fields' ) );
		$body .= Form::select( 'enquiry', __( 'Enquiry', 'whoiswho' ), $options );
		$body .= '<div class="wiw-inline-fields__action"><button class="button">' . esc_html__( 'Create customer', 'whoiswho' ) . '</button></div></form>';
		return $out . Form::section(
			__( 'Create from an enquiry', 'whoiswho' ),
			$body,
			array(
				'id'          => 'wiw-from-enquiry',
				'description' => __( 'Copies the name, organisation and contact from the enquiry and links the two.', 'whoiswho' ),
			)
		) . '</div>';
	}

	/**
	 * The invitation form: an Administrator sends a single-use link so the customer can fill in their own profile (REQ-013).
	 *
	 * @param int $id Customer.
	 */
	private static function invite_html( int $id ): string {
		if ( ! current_user_can( Invitations::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return '';
		}
		$body  = Form::open( '<input type="hidden" name="action" value="' . esc_attr( self::INVITE ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::INVITE ) ) . '" /><input type="hidden" name="customer" value="' . esc_attr( (string) $id ) . '" />', array( 'class' => 'wiw-inline-fields' ) );
		$body .= Form::email( 'invite_email', __( 'Send the link to', 'whoiswho' ), '', array( 'required' => true ) );
		$body .= '<div class="wiw-inline-fields__action"><button class="button">' . esc_html__( 'Send invitation', 'whoiswho' ) . '</button></div></form>';
		return Form::section(
			__( 'Invite the customer to complete their profile', 'whoiswho' ),
			$body,
			array(
				'id'          => 'wiw-invite',
				'description' => __( 'Sends a single-use link so the customer can fill in their own profile.', 'whoiswho' ),
			)
		);
	}

	/**
	 * The two CSV export buttons, shown only to someone who holds wiw_export_data (REQ-077).
	 */
	private static function export_html(): string {
		if ( ! current_user_can( CustomerExport::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return '';
		}
		$out = '<div class="wiw-actions wiw-exports">';
		foreach ( array(
			CustomerExport::CUSTOMERS_ACTION => __( 'Export customers as CSV', 'whoiswho' ),
			CustomerExport::ENQUIRIES_ACTION => __( 'Export enquiries as CSV', 'whoiswho' ),
		) as $action => $label ) {
			$out .= '<form method="post" class="wiw-inline-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( $action ) ) . '" /><button class="button">' . esc_html( $label ) . '</button></form> ';
		}
		return $out . '</div>';
	}

	/**
	 * The add or edit form, with the linked enquiry when there is one.
	 *
	 * @param array<string, mixed>|null $row Customer, or null for a new one.
	 */
	private function form_html( ?array $row ): string {
		$id   = null === $row ? 0 : (int) $row['id'];
		$out  = '<div class="wrap wiw-staff"><h1>' . esc_html( null === $row ? __( 'Add customer', 'whoiswho' ) : (string) $row['name'] ) . '</h1>';
		$out .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SCREEN ) ) . '">' . esc_html__( 'Back to the list', 'whoiswho' ) . '</a></p>';
		if ( null !== $row ) {
			$out .= '<p class="wiw-lead">' . esc_html__( 'External ID', 'whoiswho' ) . ': <code>' . esc_html( (string) $row['external_id'] ) . '</code></p>';
			$out .= $this->enquiry_html( $row );
		}
		$value  = static fn ( string $key ): string => null === $row ? '' : (string) $row[ $key ];
		$hidden = '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::SAVE_ACTION ) ) . '" /><input type="hidden" name="customer" value="' . esc_attr( (string) $id ) . '" />';
		$fields = '<div class="wiw-field-grid">'
			. Form::text( 'name', __( 'Name', 'whoiswho' ), $value( 'name' ), array( 'id' => 'wiw-c-name' ) )
			. Form::text( 'segment', __( 'Segment', 'whoiswho' ), $value( 'segment' ), array( 'id' => 'wiw-c-segment' ) )
			. Form::email( 'contact_email', __( 'Contact email', 'whoiswho' ), $value( 'contact_email' ), array( 'id' => 'wiw-c-contact_email' ) )
			. Form::text( 'contact_phone', __( 'Contact phone', 'whoiswho' ), $value( 'contact_phone' ), array( 'id' => 'wiw-c-contact_phone' ) )
			. '</div>';
		$notes  = Form::textarea(
			'notes',
			__( 'Internal notes (never public)', 'whoiswho' ),
			$value( 'notes' ),
			array(
				'id'        => 'wiw-c-notes',
				'rows'      => 5,
				'maxlength' => 5000,
				'counter'   => true,
			)
		);
		$out   .= Form::open( $hidden, array( 'unsaved' => true ) );
		$out   .= Form::section( __( 'Customer', 'whoiswho' ), $fields, array( 'id' => 'wiw-customer-details' ) );
		$out   .= Form::section( __( 'Notes', 'whoiswho' ), $notes, array( 'id' => 'wiw-customer-notes' ) );
		$out   .= Form::actions( '<button class="button button-primary">' . esc_html__( 'Save', 'whoiswho' ) . '</button>', array( 'sticky' => true ) ) . '</form>';
		if ( null !== $row ) {
			$out .= self::invite_html( $id );
		}
		return $out . '</div>';
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
