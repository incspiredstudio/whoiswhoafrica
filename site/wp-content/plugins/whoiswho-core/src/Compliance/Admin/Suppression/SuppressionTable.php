<?php
/**
 * The Suppression list as a shared staff list table (REQ-207, REQ-147, REQ-275). Columns: name, type, how added, added by, added
 * on and the reason (staff only). The single Remove (wiw_edit_suppression, a reason required) is the row action and the only bulk
 * action: one shared reason is taken in the confirmation step and each entry is removed through the Suppression service, which
 * checks the capability and writes the audit row per entry. A user who may only view (wiw_view_suppression) gets the table with
 * no checkboxes, no row actions and no form at all.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Admin\Suppression;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Suppression table.
 */
final class SuppressionTable extends ListTable {

	public const BULK = 'wiw_suppression_bulk';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'entry', 'suppression' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bulk_action(): string {
		return self::BULK;
	}

	/**
	 * Posting the bulk form needs the edit capability; viewing the list does not use this.
	 */
	public static function capability(): string {
		return SuppressionScreen::EDIT_CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return SuppressionScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'name'   => __( 'Name', 'whoiswho' ),
			'type'   => __( 'Type', 'whoiswho' ),
			'source' => __( 'How added', 'whoiswho' ),
			'by'     => __( 'Added by', 'whoiswho' ),
			'on'     => __( 'Added on', 'whoiswho' ),
			'reason' => __( 'Reason', 'whoiswho' ),
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
		$rows = ( new SuppressionScreen() )->entries();
		return array(
			'items' => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
			'total' => count( $rows ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_id( array $item ): int {
		return (int) $item['id'];
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
	 * The entry's name, for the confirmation step (staff only).
	 *
	 * @param int $id Entry id.
	 */
	protected function name_for_id( int $id ): string {
		$entry = ( new Suppression( AuditLogger::for_site() ) )->get( $id );
		return null === $entry ? '#' . $id : (string) $entry['name'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No names are suppressed.', 'whoiswho' );
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
				return '<strong>' . esc_html( (string) $item['name'] ) . '</strong>';
			case 'type':
				return esc_html( (string) $item['entity_type'] );
			case 'source':
				return self::pill( (string) $item['source'], 'neutral' );
			case 'by':
				return esc_html( (int) $item['added_by'] > 0 ? (string) get_the_author_meta( 'display_name', (int) $item['added_by'] ) : __( 'System', 'whoiswho' ) );
			case 'on':
				return esc_html( (string) $item['created_at'] );
			case 'reason':
				return esc_html( (string) $item['reason'] );
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
			'wiw-remove' => array(
				'label'       => __( 'Remove', 'whoiswho' ),
				'url'         => $this->step_url( 'remove', array( (int) $item['id'] ) ),
				'capability'  => SuppressionScreen::EDIT_CAP,
				'destructive' => true,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function bulk_action_definitions(): array {
		return array(
			'remove' => array(
				'label'       => __( 'Remove from the list', 'whoiswho' ),
				'capability'  => SuppressionScreen::EDIT_CAP,
				'destructive' => true,
				/* translators: %d: number of entries removed. */
				'done'        => __( '%d removed', 'whoiswho' ),
				'fields'      => array( self::class, 'remove_fields' ),
				'handler'     => array( self::class, 'remove_one' ),
			),
		);
	}

	/**
	 * Remove one entry with the shared reason through the Suppression service, as the single Remove does (capability and audit
	 * per entry).
	 *
	 * @param int     $id      Entry id.
	 * @param Request $request Request: reason.
	 * @return bool|\WP_Error
	 */
	public static function remove_one( int $id, Request $request ) {
		$reason = trim( $request->string( 'reason' ) );
		if ( '' === $reason ) {
			return new \WP_Error( 'wiw_suppression_refused', __( 'a reason is required to remove an entry', 'whoiswho' ) );
		}
		$service = new Suppression( AuditLogger::for_site() );
		if ( null === $service->get( $id ) ) {
			return new \WP_Error( 'wiw_suppression_refused', __( 'no longer on the list', 'whoiswho' ) );
		}
		$service->remove_manual( $id, $reason );
		return true;
	}

	/**
	 * The shared reason for Remove.
	 */
	public static function remove_fields(): string {
		return Form::text(
			'reason',
			__( 'Reason', 'whoiswho' ),
			'',
			array(
				'required' => true,
				'help'     => __( 'Required. The same reason is recorded in the audit log for every entry listed above.', 'whoiswho' ),
			)
		);
	}

	/**
	 * The table. A user who may not edit gets the rows only: no checkboxes, no row actions and no form.
	 *
	 * @param Request $request Request.
	 */
	public function html( Request $request ): string {
		if ( array() !== $this->allowed_bulk_actions() ) {
			return parent::html( $request );
		}
		$this->load( $request );
		ob_start();
		$this->display();
		return (string) ob_get_clean();
	}
}
