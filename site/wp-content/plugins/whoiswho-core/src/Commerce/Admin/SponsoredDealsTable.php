<?php
/**
 * The Sponsored stories deals as a shared staff list table (REQ-179, REQ-269, REQ-275): sponsor, story, the amount (only for a
 * user who may see money), the four progress dots, the status and the next step, filtered by status as before. The row action
 * opens the deal's story in the editor, for a user who may edit it. The screen has no single action on a listed deal (recording
 * a payment needs its own values), so there is no bulk action and no checkbox.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce\Admin;

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\DealService;
use WhoIsWho\Core\Commerce\DealStatus;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Commerce\SponsoredStoriesScreen;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Sponsored deals table.
 */
final class SponsoredDealsTable extends ListTable {

	/**
	 * A status to show, or empty for all.
	 *
	 * @var string
	 */
	private string $filter;

	/**
	 * Whether amounts are shown.
	 *
	 * @var bool
	 */
	private bool $money;

	/**
	 * Constructor.
	 *
	 * @param string $filter A status to show, or empty for all.
	 */
	public function __construct( string $filter = '' ) {
		$this->filter = $filter;
		$this->money  = current_user_can( SponsoredStoriesScreen::MONEY );
		parent::__construct( 'deal', 'sponsored-deals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bulk_action(): string {
		return 'wiw_sponsored_deals_bulk';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function capability(): string {
		return SponsoredStoriesScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return SponsoredStoriesScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		$columns = array(
			'sponsor' => __( 'Sponsor', 'whoiswho' ),
			'story'   => __( 'Story', 'whoiswho' ),
		);
		if ( $this->money ) {
			$columns['amount'] = __( 'Amount', 'whoiswho' );
		}
		return $columns + array(
			'progress' => __( 'Progress', 'whoiswho' ),
			'status'   => __( 'Status', 'whoiswho' ),
			'next'     => __( 'Next step', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'sponsor';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		$deals     = new DealService( new ArrangementRepository(), AuditLogger::for_site() );
		$places    = new PlacementRepository();
		$customers = new CustomerRepository();
		$today     = gmdate( 'Y-m-d' );
		$rows      = array();
		foreach ( ( new ArrangementRepository() )->all() as $row ) {
			$placements = $places->for_arrangement( (int) $row['id'] );
			$status     = DealStatus::of( $row, $placements, $today );
			if ( '' !== $this->filter && $status !== $this->filter ) {
				continue;
			}
			$customer = $customers->get( (int) $row['customer_id'] );
			$progress = DealStatus::progress( $row, $placements );
			$amount   = $this->money ? ArrangementRepository::money( $deals->view( $row ) ) : null;
			$rows[]   = array(
				'id'       => (int) $row['id'],
				'sponsor'  => null === $customer ? '' : (string) $customer['name'],
				'story_id' => (int) ( $row['story_id'] ?? 0 ),
				'story'    => empty( $row['story_id'] ) ? '' : get_the_title( (int) $row['story_id'] ),
				'amount'   => null === $amount ? '' : $amount->currency() . ' ' . number_format_i18n( $amount->minor() / 100, 2 ),
				'steps'    => $progress['steps'],
				'status'   => $status,
				'next'     => null === $progress['next'] ? '' : SponsoredStoriesScreen::next_label( $progress['next'] ),
			);
		}//end foreach
		return array(
			'items' => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
			'total' => count( $rows ),
		);
	}

	/**
	 * No checkbox: there is no bulk action on deals.
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
		return (string) $item['sponsor'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No sponsored stories yet.', 'whoiswho' );
	}

	/**
	 * Keep the status filter and the page.
	 *
	 * @param Request $request Request.
	 * @return array<string, string|int>
	 */
	protected function screen_args( Request $request ): array {
		return array_merge( '' === $this->filter ? array() : array( 'status' => $this->filter ), parent::screen_args( $request ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		switch ( $column ) {
			case 'sponsor':
				return '<strong class="row-title">' . esc_html( (string) $item['sponsor'] ) . '</strong>';
			case 'progress':
				$dots = '';
				foreach ( (array) $item['steps'] as $done ) {
					$dots .= $done ? '●' : '○';
				}
				/* translators: 1: steps done, 2: steps in all. */
				$words = sprintf( __( '%1$d of %2$d steps done', 'whoiswho' ), count( array_filter( (array) $item['steps'] ) ), count( (array) $item['steps'] ) );
				return '<span class="wiw-dots" aria-hidden="true">' . esc_html( $dots ) . '</span><span class="screen-reader-text">' . esc_html( $words ) . '</span>';
			case 'status':
				$status = (string) $item['status'];
				$tone   = in_array( $status, array( DealStatus::LIVE, DealStatus::SCHEDULED ), true ) ? 'success' : ( DealStatus::CANCELLED === $status ? 'danger' : ( DealStatus::ENDED === $status ? 'neutral' : 'warning' ) );
				return self::pill( SponsoredStoriesScreen::status_label( $status ), $tone );
			case 'story':
			case 'amount':
			case 'next':
				return esc_html( (string) $item[ $column ] );
		}
		return '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$story = (int) $item['story_id'];
		if ( $story <= 0 || ! current_user_can( 'edit_post', $story ) ) {
			return array();
		}
		return array(
			'edit' => array(
				'label'      => __( 'Open story', 'whoiswho' ),
				'url'        => (string) get_edit_post_link( $story, 'raw' ),
				'capability' => SponsoredStoriesScreen::CAP,
			),
		);
	}

	/**
	 * Read-only: no bulk form and no checkboxes (there is no bulk action); the table sits in a GET form that keeps the page.
	 */
	protected function read_only(): bool {
		return true;
	}
}
