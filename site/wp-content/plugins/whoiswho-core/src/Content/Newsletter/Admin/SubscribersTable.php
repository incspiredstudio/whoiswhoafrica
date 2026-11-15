<?php
/**
 * One Subscribers list (confirmed, pending or unsubscribed) as a read-only shared staff list table (REQ-058, REQ-061, REQ-275).
 * Addresses are never edited or removed here, and the screen has no single action on a subscriber, so the table has no
 * checkboxes, no bulk actions and no row actions; it shows the address, the consent, confirmation and withdrawal dates and the
 * source, newest first, at most 500 rows as before, 20 a page.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Newsletter\Admin;

use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Subscribers table.
 */
final class SubscribersTable extends ListTable {

	public const MAX_ROWS = 500;

	/**
	 * Repository.
	 *
	 * @var SubscriberRepository
	 */
	private SubscriberRepository $subscribers;

	/**
	 * The list shown.
	 *
	 * @var string
	 */
	private string $state;

	/**
	 * Rows in the list.
	 *
	 * @var int
	 */
	private int $count;

	/**
	 * Constructor.
	 *
	 * @param SubscriberRepository|null $subscribers Repository.
	 * @param string                    $state       confirmed, pending or unsubscribed.
	 * @param int                       $count       Rows in that list.
	 */
	public function __construct( ?SubscriberRepository $subscribers = null, string $state = 'confirmed', int $count = 0 ) {
		$this->subscribers = $subscribers ?? new SubscriberRepository();
		$this->state       = SubscribersScreen::state( $state );
		$this->count       = min( self::MAX_ROWS, max( 0, $count ) );
		parent::__construct( 'subscriber', 'subscribers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bulk_action(): string {
		return 'wiw_subscribers_bulk';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function capability(): string {
		return SubscribersScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return SubscribersScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'email'        => __( 'Email', 'whoiswho' ),
			'granted_at'   => __( 'Consent given', 'whoiswho' ),
			'confirmed_at' => __( 'Confirmed', 'whoiswho' ),
			'revoked_at'   => __( 'Unsubscribed', 'whoiswho' ),
			'source'       => __( 'Source', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'email';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		$offset = ( $page - 1 ) * $per_page;
		$rows   = $offset >= self::MAX_ROWS ? array() : $this->subscribers->list( $this->state, min( self::MAX_ROWS, $offset + $per_page ) );
		return array(
			'items' => array_slice( $rows, $offset, $per_page ),
			'total' => max( $this->count, count( $rows ) ),
		);
	}

	/**
	 * A subscriber is never selected: the list is read only.
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
		return (string) $item['email'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No subscribers in this list.', 'whoiswho' );
	}

	/**
	 * Keep the list when paging.
	 *
	 * @param Request $request Request.
	 * @return array<string, string|int>
	 */
	protected function screen_args( Request $request ): array {
		return array_merge( array( 'state' => $this->state ), parent::screen_args( $request ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		return esc_html( (string) ( $item[ $column ] ?? '' ) );
	}

	/**
	 * The table alone, without the bulk form: there is nothing to select.
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
