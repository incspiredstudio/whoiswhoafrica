<?php
/**
 * The Customers list as a shared staff list table (REQ-205, REQ-275): external id, name and segment, newest first, searched as
 * before (at most 200 rows, 20 a page). The row action is Edit (the customer's form). The screen's single actions on a customer
 * are the edit form and the invitation, which each need values of their own (fields, an address), so there is no bulk action and
 * no checkbox.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce\Admin;

use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Customers table.
 */
final class CustomersTable extends ListTable {

	/**
	 * Repository.
	 *
	 * @var CustomerRepository
	 */
	private CustomerRepository $customers;

	/**
	 * Search text.
	 *
	 * @var string
	 */
	private string $search;

	/**
	 * Constructor.
	 *
	 * @param CustomerRepository|null $customers Repository.
	 * @param string                  $search    Search text.
	 */
	public function __construct( ?CustomerRepository $customers = null, string $search = '' ) {
		$this->customers = $customers ?? new CustomerRepository();
		$this->search    = $search;
		parent::__construct( 'customer', 'customers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bulk_action(): string {
		return 'wiw_customers_bulk';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function capability(): string {
		return CustomersScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return CustomersScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'name'        => __( 'Name', 'whoiswho' ),
			'external_id' => __( 'External ID', 'whoiswho' ),
			'segment'     => __( 'Segment', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'name';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		$rows = $this->customers->search( $this->search, CustomersScreen::LIMIT );
		return array(
			'items' => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
			'total' => count( $rows ),
		);
	}

	/**
	 * No checkbox: there is no bulk action on customers.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_id( array $item ): int {
		unset( $item );
		return 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_name( array $item ): string {
		return (string) $item['name'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No customers.', 'whoiswho' );
	}

	/**
	 * Keep the search and the page.
	 *
	 * @param Request $request Request.
	 * @return array<string, string|int>
	 */
	protected function screen_args( Request $request ): array {
		return array_merge( '' === $this->search ? array() : array( 's' => $this->search ), parent::screen_args( $request ) );
	}

	/**
	 * The customer's form.
	 *
	 * @param int $id Customer.
	 */
	public static function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'page'     => CustomersScreen::SCREEN,
				'customer' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		switch ( $column ) {
			case 'name':
				return '<a class="row-title" href="' . esc_url( self::edit_url( (int) $item['id'] ) ) . '">' . esc_html( (string) $item['name'] ) . '</a>';
			case 'external_id':
				return '<code>' . esc_html( (string) $item['external_id'] ) . '</code>';
			case 'segment':
				return esc_html( (string) $item['segment'] );
		}
		return '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		return array(
			'edit' => array(
				'label'      => __( 'Edit', 'whoiswho' ),
				'url'        => self::edit_url( (int) $item['id'] ),
				'capability' => CustomersScreen::CAP,
			),
		);
	}

	/**
	 * The table alone, without the bulk form: there is no bulk action.
	 *
	 * @param Request $request Request.
	 */
	public function html( Request $request ): string {
		$this->load( $request );
		ob_start();
		$this->display();
		return (string) ob_get_clean();
	}
}
