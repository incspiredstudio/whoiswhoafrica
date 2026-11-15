<?php
/**
 * The Email log rows as a shared staff list table (REQ-275, REQ-075, REQ-051). The log is read only: no checkboxes (item_id is
 * 0) and no bulk actions. Its one single action stays: Record bounce and Record complaint are row actions that call the same
 * admin-post action with its nonce (wiw_email_record, capability wiw_view_audit), which adds a log event and an audit row. A
 * failed send carries the wiw-failed row class and a Failed pill. Recipients show as a ten-character reference only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail\Admin;

use WhoIsWho\Core\Platform\Admin\ReadOnlyListForm;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Email log table.
 */
final class EmailLogTable extends ListTable {

	use ReadOnlyListForm;

	public const BULK = 'wiw_email_log_list';

	/**
	 * The screen that reads the rows (and checks the capability).
	 *
	 * @var EmailLogScreen
	 */
	private EmailLogScreen $log_screen;

	/**
	 * Event filter.
	 *
	 * @var string
	 */
	private string $event;

	/**
	 * Result filter.
	 *
	 * @var string
	 */
	private string $result;

	/**
	 * Constructor.
	 *
	 * @param EmailLogScreen $screen Screen.
	 * @param string         $event  Event filter, or empty.
	 * @param string         $result Result filter, or empty.
	 */
	public function __construct( EmailLogScreen $screen, string $event = '', string $result = '' ) {
		$this->log_screen = $screen;
		$this->event      = $event;
		$this->result     = $result;
		parent::__construct( 'message', 'email-events' );
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
		return EmailLogScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return EmailLogScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'event'     => __( 'Event', 'whoiswho' ),
			'at'        => __( 'Time (UTC)', 'whoiswho' ),
			'recipient' => __( 'Recipient', 'whoiswho' ),
			'type'      => __( 'Type', 'whoiswho' ),
			'result'    => __( 'Result', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'event';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function per_page(): int {
		return EmailLogScreen::PER_PAGE;
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
			'items' => $this->log_screen->rows( $this->event, $this->result, $page ),
			'total' => $this->log_screen->count( $this->event, $this->result ),
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
		return (string) ( $item['event'] ?? '' );
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
		return array_merge(
			array_filter(
				array(
					'event'  => $this->event,
					'result' => $this->result,
				)
			),
			parent::screen_args( $request )
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
			case 'event':
				return '<strong>' . esc_html( (string) $item['event'] ) . '</strong>';
			case 'at':
				return esc_html( (string) $item['created_at'] );
			case 'recipient':
				return '<code>' . esc_html( substr( (string) $item['recipient_ref'], 0, 10 ) ) . '</code>';
			case 'type':
				return esc_html( (string) $item['type'] );
			case 'result':
				$result = (string) $item['result'];
				if ( 'failed' === $result ) {
					return self::pill( __( 'Failed', 'whoiswho' ), 'danger' );
				}
				return self::pill( $result, 'sent' === $result ? 'success' : ( 'recorded' === $result ? 'warning' : 'neutral' ) );
		}
		return '';
	}

	/**
	 * Record bounce and Record complaint: the existing single action, one link per kind.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$id = (int) ( $item['id'] ?? 0 );
		if ( $id <= 0 ) {
			return array();
		}
		return array(
			'wiw-record-bounce'    => array(
				'label'      => __( 'Record bounce', 'whoiswho' ),
				'url'        => EmailLogScreen::record_url( $id, 'bounce' ),
				'capability' => EmailLogScreen::CAP,
				'confirm'    => __( 'Record a bounce against this message? This adds a log event and an audit row.', 'whoiswho' ),
			),
			'wiw-record-complaint' => array(
				'label'      => __( 'Record complaint', 'whoiswho' ),
				'url'        => EmailLogScreen::record_url( $id, 'complaint' ),
				'capability' => EmailLogScreen::CAP,
				'confirm'    => __( 'Record a complaint against this message? This adds a log event and an audit row.', 'whoiswho' ),
			),
		);
	}

	/**
	 * A row, with the wiw-failed class on a failed send.
	 *
	 * @param array<string, mixed>|object $item Item.
	 */
	public function single_row( $item ): void {
		$item = (array) $item;
		echo '<tr' . ( 'failed' === ( $item['result'] ?? '' ) ? ' class="wiw-failed"' : '' ) . '>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}
}
