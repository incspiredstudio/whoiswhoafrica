<?php
/**
 * Recent bulk unpublish batches as a read-only shared staff list table (REQ-193, REQ-275): when, the reason, the number of
 * profiles and the status, newest first. A history is not edited, so there are no checkboxes and no bulk actions; the one row
 * action is the existing Restore this batch, for a batch that is done or running and a user with wiw_bulk_unpublish.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Admin;

use WhoIsWho\Core\Directory\Admin\ProfilesScreen;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Review\Bulk\BulkRepository;
use WhoIsWho\Core\Review\Bulk\BulkUnpublish;

/**
 * Bulk batches table.
 */
final class BulkBatchesTable extends ListTable {

	public const LIMIT = 10;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'batch', 'bulk-batches' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bulk_action(): string {
		return 'wiw_bulk_batches_bulk';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function capability(): string {
		return BulkUnpublish::CAPABILITY;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return ProfilesScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'created_at'  => __( 'When', 'whoiswho' ),
			'reason'      => __( 'Reason', 'whoiswho' ),
			'match_count' => __( 'Profiles', 'whoiswho' ),
			'status'      => __( 'Status', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'created_at';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		unset( $page, $per_page );
		$rows = ( new BulkRepository() )->recent( self::LIMIT );
		return array(
			'items' => $rows,
			'total' => count( $rows ),
		);
	}

	/**
	 * A batch is never selected: the list is a history.
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
		return (string) $item['created_at'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No bulk actions yet.', 'whoiswho' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		switch ( $column ) {
			case 'status':
				$status = (string) $item['status'];
				return self::pill( $status, BulkRepository::DONE === $status ? 'success' : ( BulkRepository::RUNNING === $status ? 'info' : 'neutral' ) );
			case 'created_at':
				return '<strong>' . esc_html( (string) $item['created_at'] ) . '</strong>';
			case 'reason':
			case 'match_count':
				return esc_html( (string) $item[ $column ] );
		}
		return '';
	}

	/**
	 * Restore this batch, for a batch that is done or still running.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		if ( ! in_array( $item['status'], array( BulkRepository::DONE, BulkRepository::RUNNING ), true ) ) {
			return array();
		}
		return array(
			'wiw-restore' => array(
				'label'      => __( 'Restore this batch', 'whoiswho' ),
				'url'        => add_query_arg(
					array(
						'action'   => BulkUnpublishPanel::RESTORE_ACT,
						'batch'    => (string) $item['batch_ref'],
						'_wpnonce' => ActionGuard::nonce_for( BulkUnpublishPanel::RESTORE_ACT ),
					),
					admin_url( 'admin-post.php' )
				),
				'capability' => BulkUnpublish::CAPABILITY,
			),
		);
	}

	/**
	 * Read-only: no bulk form and no checkboxes (a history has nothing to select); the table sits in a GET form that keeps the page.
	 */
	protected function read_only(): bool {
		return true;
	}

	/**
	 * No pager on a single page: this table shares its screen with other tables, and the pager uses fixed element ids.
	 */
	protected function pager_on_one_page(): bool {
		return false;
	}
}
