<?php
/**
 * The Enquiries list as a shared staff list table (REQ-089, REQ-177, REQ-187, REQ-275). Columns: reference, name, organisation,
 * interest, source, age, status and owner (an enquiry has no due date). Row actions: Open, and Assign to me. Bulk actions repeat
 * the single Status and owner change through EnquiriesScreen::update(), which checks wiw_support_inbox and writes one audit row
 * per enquiry (ids and statuses only): Assign to me (the status is kept) and Set status (one status chosen in the confirmation
 * step; the owner is kept).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Admin;

use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Enquiries table.
 */
final class EnquiriesTable extends ListTable {

	public const BULK = 'wiw_enquiries_bulk';

	/**
	 * Filters: status and source.
	 *
	 * @var array<string, string>
	 */
	private array $filters;

	/**
	 * The screen.
	 *
	 * @var EnquiriesScreen|null
	 */
	private ?EnquiriesScreen $host;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $filters Filters (status, source).
	 * @param EnquiriesScreen|null  $screen  Screen; the site's by default.
	 */
	public function __construct( array $filters = array(), ?EnquiriesScreen $screen = null ) {
		parent::__construct( 'enquiry', 'enquiries' );
		$this->filters = array_filter(
			array(
				'status' => (string) ( $filters['status'] ?? '' ),
				'source' => (string) ( $filters['source'] ?? '' ),
			),
			static fn ( string $value ): bool => '' !== $value
		);
		$this->host    = $screen;
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
		return EnquiriesScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return EnquiriesScreen::SCREEN;
	}

	/**
	 * The screen.
	 */
	private function host(): EnquiriesScreen {
		if ( null === $this->host ) {
			$this->host = EnquiriesScreen::for_site();
		}
		return $this->host;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'reference'    => __( 'Reference', 'whoiswho' ),
			'name'         => __( 'Name', 'whoiswho' ),
			'organisation' => __( 'Organisation', 'whoiswho' ),
			'interest'     => __( 'Interested in', 'whoiswho' ),
			'source'       => __( 'Source', 'whoiswho' ),
			'age'          => __( 'Age', 'whoiswho' ),
			'status'       => __( 'Status', 'whoiswho' ),
			'owner'        => __( 'Owner', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'reference';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		$rows = $this->host()->rows( $this->filters['status'] ?? '', $this->filters['source'] ?? '' );
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
	 * The reference: the checkbox label and the confirmation list carry no names.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_name( array $item ): string {
		return (string) $item['reference'];
	}

	/**
	 * The reference, for the confirmation step.
	 *
	 * @param int $id Item id.
	 */
	protected function name_for_id( int $id ): string {
		$item = $this->host()->item( $id );
		return null === $item ? '#' . $id : (string) $item['reference'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No enquiries.', 'whoiswho' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Request $request Request.
	 */
	protected function screen_args( Request $request ): array {
		return array_merge( $this->filters, parent::screen_args( $request ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		switch ( $column ) {
			case 'reference':
				return '<a class="row-title" href="' . esc_url( EnquiriesScreen::item_url( (int) $item['id'] ) ) . '">' . esc_html( (string) $item['reference'] ) . '</a>';
			case 'name':
				return esc_html( (string) $item['requester_name'] );
			case 'organisation':
				return esc_html( (string) $item['organisation'] );
			case 'interest':
				return esc_html( (string) $item['interest'] );
			case 'source':
				return esc_html( (string) $item['source'] );
			case 'age':
				$days = EnquiriesScreen::age_days( $item, time() );
				/* translators: %d: number of days. */
				return esc_html( sprintf( _n( '%d day', '%d days', $days, 'whoiswho' ), $days ) );
			case 'status':
				return self::status_pill( (string) $item['status'] );
			case 'owner':
				$owner = (int) $item['owner_id'] > 0 ? get_userdata( (int) $item['owner_id'] ) : false;
				return esc_html( $owner instanceof \WP_User ? $owner->display_name : '' );
		}//end switch
		return '';
	}

	/**
	 * A support item status as a pill.
	 *
	 * @param string $status Status.
	 */
	public static function status_pill( string $status ): string {
		$tones = array(
			ItemStatus::NEW          => 'info',
			ItemStatus::ACKNOWLEDGED => 'neutral',
			ItemStatus::IN_PROGRESS  => 'warning',
			ItemStatus::RESOLVED     => 'success',
			ItemStatus::DECLINED     => 'neutral',
		);
		return self::pill( $status, $tones[ $status ] ?? 'neutral' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$id = (int) $item['id'];
		return array(
			'open'          => array(
				'label'      => __( 'Open', 'whoiswho' ),
				'url'        => EnquiriesScreen::item_url( $id ),
				'capability' => EnquiriesScreen::CAP,
			),
			'wiw-assign-me' => array(
				'label'      => __( 'Assign to me', 'whoiswho' ),
				'url'        => $this->step_url( 'assign', array( $id ) ),
				'capability' => EnquiriesScreen::CAP,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function bulk_action_definitions(): array {
		return array(
			'assign' => array(
				'label'      => __( 'Assign to me', 'whoiswho' ),
				'capability' => EnquiriesScreen::CAP,
				/* translators: %d: number of enquiries assigned. */
				'done'       => __( '%d assigned to you', 'whoiswho' ),
				'handler'    => array( $this, 'assign_one' ),
			),
			'status' => array(
				'label'      => __( 'Set status', 'whoiswho' ),
				'capability' => EnquiriesScreen::CAP,
				/* translators: %d: number of enquiries changed. */
				'done'       => __( '%d updated', 'whoiswho' ),
				'fields'     => array( self::class, 'status_fields' ),
				'handler'    => array( $this, 'status_one' ),
			),
		);
	}

	/**
	 * Assign one enquiry to the current user, keeping its status, through the single update (audited).
	 *
	 * @param int $id Item id.
	 * @return bool|\WP_Error
	 */
	public function assign_one( int $id ) {
		$item = $this->host()->item( $id );
		if ( null === $item ) {
			return new \WP_Error( 'wiw_enquiry_refused', __( 'not an enquiry', 'whoiswho' ) );
		}
		return $this->update_one( $id, (string) $item['status'], get_current_user_id() );
	}

	/**
	 * Set one enquiry's status to the shared choice, keeping its owner, through the single update (audited).
	 *
	 * @param int     $id      Item id.
	 * @param Request $request Request: status.
	 * @return bool|\WP_Error
	 */
	public function status_one( int $id, Request $request ) {
		$item = $this->host()->item( $id );
		if ( null === $item ) {
			return new \WP_Error( 'wiw_enquiry_refused', __( 'not an enquiry', 'whoiswho' ) );
		}
		return $this->update_one( $id, $request->string( 'status' ), (int) $item['owner_id'] );
	}

	/**
	 * Run the single update and turn a refusal into a skipped row.
	 *
	 * @param int    $id     Item id.
	 * @param string $status Status.
	 * @param int    $owner  Owner.
	 * @return bool|\WP_Error
	 */
	private function update_one( int $id, string $status, int $owner ) {
		try {
			$this->host()->update( $id, $status, $owner );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'wiw_enquiry_refused', $e->getMessage() );
		}
		return true;
	}

	/**
	 * The shared status for Set status.
	 */
	public static function status_fields(): string {
		return Form::select(
			'status',
			__( 'New status', 'whoiswho' ),
			array_combine( ItemStatus::all(), ItemStatus::all() ),
			'',
			array( 'help' => __( 'Every enquiry listed above gets this status; its owner stays the same.', 'whoiswho' ) )
		);
	}
}
