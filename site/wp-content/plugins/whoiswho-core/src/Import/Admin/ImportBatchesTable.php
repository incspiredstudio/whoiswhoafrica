<?php
/**
 * The Import history as a shared staff list table (REQ-275, REQ-237). One row per batch, newest first, with its status as a pill,
 * its record count and when it was uploaded. Row actions: Open (the batch on the Import screen: dry run, progress, counts) and,
 * for an applied batch, Undo, which opens the confirmation step of the bulk action for that one batch. Bulk Undo selected repeats
 * the single Undo for each selected batch through the same service (ImportService::undo, which checks wiw_run_imports per batch
 * and writes the import.undo audit row); it is destructive, so it is confirmed first, and a batch that is not applied is skipped
 * with the service's reason. A large batch is undone in steps: the bulk action starts it, and its batch page carries on with
 * Continue, as after the single Undo. Each request stops starting new undos after BUDGET seconds; the rest are skipped and can be
 * selected again.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Admin;

use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\Undo\BatchUndo;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Import history table.
 */
final class ImportBatchesTable extends ListTable {

	public const BULK = 'wiw_import_bulk';

	/**
	 * Seconds one bulk request may spend starting undos (each undo step has its own budget of ImportService::STEP_BUDGET).
	 */
	public const BUDGET = 20;

	/**
	 * Batches.
	 *
	 * @var BatchRepository
	 */
	private BatchRepository $batches;

	/**
	 * When this table was made (the bulk budget starts here).
	 *
	 * @var float
	 */
	private float $started;

	/**
	 * Constructor.
	 *
	 * @param BatchRepository|null $batches Batches.
	 */
	public function __construct( ?BatchRepository $batches = null ) {
		$this->batches = $batches ?? new BatchRepository();
		$this->started = microtime( true );
		parent::__construct( 'batch', 'import-batches' );
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
		return ImportScreen::CAPABILITY;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return ImportScreen::SCREEN;
	}

	/**
	 * The Import screen showing one batch.
	 *
	 * @param string $batch_id Batch id.
	 */
	public static function batch_url( string $batch_id ): string {
		return add_query_arg(
			array(
				'page'  => ImportScreen::SCREEN,
				'batch' => $batch_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'batch'   => __( 'Batch', 'whoiswho' ),
			'status'  => __( 'Status', 'whoiswho' ),
			'records' => __( 'Records', 'whoiswho' ),
			'created' => __( 'Uploaded', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'batch';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		return array(
			'items' => $this->batches->page( $per_page, ( $page - 1 ) * $per_page ),
			'total' => $this->batches->count(),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_id( array $item ): int {
		return (int) ( $item['id'] ?? 0 );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_name( array $item ): string {
		return (string) ( $item['batch_id'] ?? '' );
	}

	/**
	 * A row's name for the confirmation step: its batch id.
	 *
	 * @param int $id Row id.
	 */
	public function name_for( int $id ): string {
		$batch_id = $this->batches->batch_id_of( $id );
		return '' === $batch_id ? '#' . $id : $batch_id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Row id.
	 */
	protected function name_for_id( int $id ): string {
		return $this->name_for( $id );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No imports yet.', 'whoiswho' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		$batch_id = (string) $item['batch_id'];
		switch ( $column ) {
			case 'batch':
				return '<a class="row-title" href="' . esc_url( self::batch_url( $batch_id ) ) . '">' . esc_html( $batch_id ) . '</a>';
			case 'status':
				return self::status_pill( (string) $item['status'], $batch_id );
			case 'records':
				return esc_html( (string) $item['record_count'] );
			case 'created':
				return esc_html( (string) $item['created_at'] );
		}
		return '';
	}

	/**
	 * A batch status as a pill (an applied batch whose undo has started shows as undoing).
	 *
	 * @param string $status   Status.
	 * @param string $batch_id Batch id.
	 */
	private static function status_pill( string $status, string $batch_id ): string {
		if ( 'applied' === $status && BatchUndo::in_progress( $batch_id ) ) {
			return self::pill( __( 'undoing', 'whoiswho' ), 'warning' );
		}
		$tones = array(
			'applied'  => 'success',
			'undone'   => 'neutral',
			'dry_run'  => 'info',
			'checked'  => 'info',
			'applying' => 'warning',
			'failed'   => 'danger',
		);
		return self::pill( $status, $tones[ $status ] ?? 'neutral' );
	}

	/**
	 * Open, and Undo for an applied batch (it opens the confirmation step for that batch).
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$batch_id = (string) ( $item['batch_id'] ?? '' );
		$actions  = array(
			'wiw-open' => array(
				'label'      => __( 'Open', 'whoiswho' ),
				'url'        => self::batch_url( $batch_id ),
				'capability' => ImportScreen::CAPABILITY,
			),
		);
		if ( 'applied' === ( $item['status'] ?? '' ) && ! BatchUndo::in_progress( $batch_id ) ) {
			$actions['wiw-undo'] = array(
				'label'      => __( 'Undo', 'whoiswho' ),
				'url'        => $this->step_url( 'undo', array( (int) $item['id'] ) ),
				'capability' => ImportScreen::CAPABILITY,
			);
		}
		return $actions;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function bulk_action_definitions(): array {
		return array(
			'undo' => array(
				'label'       => __( 'Undo selected', 'whoiswho' ),
				'capability'  => ImportScreen::CAPABILITY,
				'destructive' => true,
				/* translators: %d: number of batches undone or being undone. */
				'done'        => __( '%d undone', 'whoiswho' ),
				'handler'     => fn ( int $id ) => $this->undo( $id ),
			),
		);
	}

	/**
	 * Undo one selected batch through the same service as the single Undo.
	 *
	 * @param int $id Row id.
	 * @return true|\WP_Error
	 */
	private function undo( int $id ) {
		$batch_id = $this->batches->batch_id_of( $id );
		if ( '' === $batch_id ) {
			return new \WP_Error( 'wiw_import_unknown', __( 'no such batch', 'whoiswho' ) );
		}
		if ( microtime( true ) - $this->started > self::BUDGET ) {
			return new \WP_Error( 'wiw_import_budget', __( 'time ran out; select them again', 'whoiswho' ) );
		}
		( new ImportService() )->undo( $batch_id, get_current_user_id() );
		return true;
	}
}
