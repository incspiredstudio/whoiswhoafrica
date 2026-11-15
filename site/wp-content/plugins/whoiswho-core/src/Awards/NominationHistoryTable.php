<?php
/**
 * A nomination's change history as a read-only shared staff list table (REQ-202, REQ-275): oldest first, with the staff member
 * for each change. A history is never edited, so the table has no checkboxes, no bulk actions and no row actions.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Nomination history table.
 */
final class NominationHistoryTable extends ListTable {

	/**
	 * The history rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $rows;

	/**
	 * Constructor.
	 *
	 * @param array<int, array<string, mixed>> $rows History rows (NominationService::history).
	 */
	public function __construct( array $rows = array() ) {
		$this->rows = $rows;
		parent::__construct( 'change', 'nomination-changes' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bulk_action(): string {
		return 'wiw_nomination_history_bulk';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function capability(): string {
		return NominationDetailScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return NominationDetailScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'changed_at'  => __( 'When (UTC)', 'whoiswho' ),
			'change_type' => __( 'Change', 'whoiswho' ),
			'old_value'   => __( 'From', 'whoiswho' ),
			'new_value'   => __( 'To', 'whoiswho' ),
			'user'        => __( 'Staff member', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'changed_at';
	}

	/**
	 * Every change on one page.
	 */
	protected function per_page(): int {
		return 500;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		return array(
			'items' => array_slice( $this->rows, ( $page - 1 ) * $per_page, $per_page ),
			'total' => count( $this->rows ),
		);
	}

	/**
	 * A history row is never selected.
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
		return (string) $item['changed_at'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No changes yet.', 'whoiswho' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		switch ( $column ) {
			case 'changed_at':
			case 'change_type':
				return esc_html( (string) $item[ $column ] );
			case 'old_value':
			case 'new_value':
				return esc_html( NominationsScreen::label( (string) $item[ $column ] ) );
			case 'user':
				$user = get_userdata( (int) $item['user_id'] );
				return esc_html( false === $user ? __( '(removed user)', 'whoiswho' ) : (string) $user->display_name );
		}
		return '';
	}

	/**
	 * The table alone, without the bulk form: a history has nothing to select.
	 *
	 * @param Request $request Request.
	 */
	public function html( Request $request ): string {
		$this->load( $request );
		ob_start();
		$this->display();
		return (string) ob_get_clean();
	}

	/**
	 * No pager on a single page: this table shares its screen with other tables, and WordPress's pager uses fixed element ids.
	 *
	 * @param string $which top or bottom.
	 */
	protected function pagination( $which ): void {
		if ( (int) $this->get_pagination_arg( 'total_pages' ) > 1 ) {
			parent::pagination( $which );
		}
	}
}
