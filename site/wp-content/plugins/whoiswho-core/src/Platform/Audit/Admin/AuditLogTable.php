<?php
/**
 * The Audit log rows as a shared staff list table (REQ-275, REQ-051, REQ-188). The log is read only: no checkboxes (item_id is
 * 0), no bulk actions and no row actions; nothing here edits or deletes a row. It pages 50 rows at a time with the kit's pager and
 * keeps the screen's filters (actor, action, from, to) in the address.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Audit\Admin;

use WhoIsWho\Core\Platform\Admin\ReadOnlyListForm;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Audit log table.
 */
final class AuditLogTable extends ListTable {

	use ReadOnlyListForm;

	public const BULK = 'wiw_audit_log_list';

	/**
	 * The screen that reads the rows (and checks the capability).
	 *
	 * @var AuditScreen
	 */
	private AuditScreen $log_screen;

	/**
	 * The sanitised filters.
	 *
	 * @var array{actor: int, action: string, from: string, to: string}
	 */
	private array $filters;

	/**
	 * Constructor.
	 *
	 * @param AuditScreen                                                     $screen  Screen.
	 * @param array{actor?: int, action?: string, from?: string, to?: string} $filters Filters.
	 */
	public function __construct( AuditScreen $screen, array $filters = array() ) {
		$this->log_screen = $screen;
		$this->filters    = array(
			'actor'  => (int) ( $filters['actor'] ?? 0 ),
			'action' => (string) ( $filters['action'] ?? '' ),
			'from'   => (string) ( $filters['from'] ?? '' ),
			'to'     => (string) ( $filters['to'] ?? '' ),
		);
		parent::__construct( 'entry', 'audit-entries' );
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
		return AuditScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return AuditScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'action'  => __( 'Action', 'whoiswho' ),
			'at'      => __( 'Time (UTC)', 'whoiswho' ),
			'actor'   => __( 'Actor', 'whoiswho' ),
			'subject' => __( 'Subject', 'whoiswho' ),
			'reason'  => __( 'Reason', 'whoiswho' ),
			'batch'   => __( 'Batch', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'action';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function per_page(): int {
		return AuditScreen::PER_PAGE;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page (the screen's 50).
	 */
	protected function fetch( int $page, int $per_page ): array {
		unset( $per_page );
		return array(
			'items' => $this->log_screen->rows( $this->filters, $page ),
			'total' => $this->log_screen->count( $this->filters ),
		);
	}

	/**
	 * A log row has no checkbox.
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
		return (string) ( $item['action'] ?? '' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'Nothing logged yet.', 'whoiswho' );
	}

	/**
	 * Keep the filters and the page.
	 *
	 * @param Request $request Request.
	 * @return array<string, string|int>
	 */
	protected function screen_args( Request $request ): array {
		return array_merge( array_filter( $this->filters ), parent::screen_args( $request ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		switch ( $column ) {
			case 'at':
				return esc_html( (string) $item['at'] );
			case 'action':
				return '<code class="wiw-code">' . esc_html( (string) $item['action'] ) . '</code>';
			case 'actor':
				return esc_html( (string) $item['actor_id'] );
			case 'subject':
				return esc_html( $item['subject_type'] . ' ' . $item['subject_id'] );
			case 'reason':
				return esc_html( (string) $item['reason'] );
			case 'batch':
				return esc_html( (string) $item['batch_ref'] );
		}
		return '';
	}
}
