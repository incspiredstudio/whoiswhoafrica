<?php
/**
 * The Purge log rows as a shared staff list table (REQ-275, REQ-209). The log is read only: no checkboxes (item_id is 0), no bulk
 * actions and no row actions. It pages 50 rows at a time and keeps the screen's filters (status, from, to); the status shows as a
 * pill.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache\Admin;

use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Cache\PurgeLog;
use WhoIsWho\Core\Platform\Cache\WpdbPurgeLog;

/**
 * Purge log table.
 */
final class PurgeLogTable extends ListTable {


	public const BULK = 'wiw_purge_log_list';

	/**
	 * Log.
	 *
	 * @var PurgeLog
	 */
	private PurgeLog $log;

	/**
	 * The sanitised filters.
	 *
	 * @var array{status: string, from: string, to: string}
	 */
	private array $filters;

	/**
	 * Constructor.
	 *
	 * @param PurgeLog                                           $log     Log.
	 * @param array{status?: string, from?: string, to?: string} $filters Filters.
	 */
	public function __construct( PurgeLog $log, array $filters = array() ) {
		$this->log     = $log;
		$this->filters = array(
			'status' => (string) ( $filters['status'] ?? '' ),
			'from'   => (string) ( $filters['from'] ?? '' ),
			'to'     => (string) ( $filters['to'] ?? '' ),
		);
		parent::__construct( 'purge', 'purges' );
	}

	/**
	 * Read-only: a log has nothing to select, so the table sits in a GET form that keeps the page and the filters.
	 */
	protected function read_only(): bool {
		return true;
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
		return PurgeLogScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return PurgeLogScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'subject' => __( 'Subject', 'whoiswho' ),
			'at'      => __( 'Time (UTC)', 'whoiswho' ),
			'reason'  => __( 'Reason', 'whoiswho' ),
			'urls'    => __( 'URLs', 'whoiswho' ),
			'status'  => __( 'Status', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'subject';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function per_page(): int {
		return PurgeLogScreen::PER_PAGE;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		return array(
			'items' => $this->log->rows( $per_page, ( $page - 1 ) * $per_page, $this->filters ),
			'total' => $this->log->count( $this->filters ),
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
		return $item['subject_type'] . ' #' . $item['subject_id'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'Nothing purged yet.', 'whoiswho' );
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
			case 'subject':
				return '<strong>' . esc_html( $this->item_name( $item ) ) . '</strong>';
			case 'at':
				return esc_html( (string) $item['created_at'] );
			case 'reason':
				return esc_html( (string) $item['reason'] );
			case 'urls':
				return esc_html( (string) $item['url_count'] );
			case 'status':
				$status = (string) $item['result'];
				return self::pill( $status, 'ok' === $status ? 'success' : ( 'failed' === $status ? 'danger' : 'neutral' ) );
		}
		return '';
	}
}
