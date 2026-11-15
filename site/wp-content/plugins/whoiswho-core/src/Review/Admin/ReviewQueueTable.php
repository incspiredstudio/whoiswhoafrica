<?php
/**
 * The Review queue as a shared staff list table (REQ-084, REQ-222, REQ-275). Rows are the queue items in the default order. Row
 * actions: Review (open the item), Assign to me, and for a profile item Approve and Reject, which open the confirmation step for
 * that one row. Bulk actions repeat the same single actions through the same code: Approve (the five-point checklist is ticked
 * once in the confirmation step for all the selected rows; each row still passes the fact gate, the importer rule and the scan
 * block, and the state machine writes the audit row), Reject (one shared reason and reason code, through ReviewService), and
 * Assign to me. Pending revisions and flagged changes are decided on the item screen (they need the revision), so their rows
 * have no checkbox. There is no Return action in the review flow; Reject with a reason is the way back.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Admin;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Review\Metrics\DecisionLog;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateException;

/**
 * Review queue table.
 */
final class ReviewQueueTable extends ListTable {

	public const BULK = 'wiw_review_bulk';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'item', 'review-items' );
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
		return ReviewQueueScreen::CAPABILITY;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return ReviewQueueScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'name'     => __( 'Item', 'whoiswho' ),
			'kind'     => __( 'Kind', 'whoiswho' ),
			'type'     => __( 'Type', 'whoiswho' ),
			'state'    => __( 'State', 'whoiswho' ),
			'since'    => __( 'Waiting since', 'whoiswho' ),
			'marks'    => __( 'Marks', 'whoiswho' ),
			'assignee' => __( 'Assigned', 'whoiswho' ),
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
		$result = ( new ReviewQueue() )->items( $page, $per_page );
		return array(
			'items' => $result['items'],
			'total' => $result['total'],
		);
	}

	/**
	 * A profile item carries its profile id; a revision or flagged change has no checkbox.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_id( array $item ): int {
		return 0 === (int) $item['version_id'] ? (int) $item['profile_id'] : 0;
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
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'Nothing to review.', 'whoiswho' );
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
				return '<a class="row-title" href="' . esc_url( ReviewItemScreen::url( (int) $item['profile_id'] ) ) . '">' . esc_html( (string) $item['name'] ) . '</a>';
			case 'kind':
				return esc_html( self::kinds()[ (string) $item['kind'] ] ?? (string) $item['kind'] );
			case 'type':
				return esc_html( Labels::entity_type( (string) $item['type'] ) );
			case 'state':
				return self::pill( Labels::state( (string) $item['state'], (string) ( $item['origin'] ?? '' ) ), PubState::PUBLISHED_PENDING === (string) $item['state'] ? 'warning' : 'neutral' );
			case 'since':
				return esc_html( (string) $item['since'] );
			case 'marks':
				$marks = array_filter(
					array(
						! empty( $item['pilot'] ) ? self::pill( __( 'Pilot', 'whoiswho' ), 'info' ) : '',
						! empty( $item['ai_drafted'] ) ? self::pill( __( 'AI-drafted', 'whoiswho' ), 'warning' ) : '',
						! empty( $item['edited'] ) ? self::pill( __( 'Edited', 'whoiswho' ), 'neutral' ) : '',
					)
				);
				return implode( ' ', $marks );
			case 'assignee':
				$who = (int) $item['assignee'] > 0 ? get_userdata( (int) $item['assignee'] ) : false;
				return esc_html( $who ? $who->display_name : '' );
		}//end switch
		return '';
	}

	/**
	 * The kinds in words.
	 *
	 * @return array<string, string>
	 */
	public static function kinds(): array {
		return array(
			ReviewQueue::KIND_NEW        => __( 'New profile', 'whoiswho' ),
			ReviewQueue::KIND_CLAIM      => __( 'Claim', 'whoiswho' ),
			ReviewQueue::KIND_SUBMISSION => __( 'Customer submission', 'whoiswho' ),
			ReviewQueue::KIND_REVISION   => __( 'Pending revision', 'whoiswho' ),
			ReviewQueue::KIND_FLAGGED    => __( 'Flagged change', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$id      = (int) $item['profile_id'];
		$actions = array(
			'review' => array(
				'label'      => __( 'Review', 'whoiswho' ),
				'url'        => ReviewItemScreen::url( $id ),
				'capability' => ReviewItemScreen::CAPABILITY,
			),
			'assign' => array(
				'label'      => __( 'Assign to me', 'whoiswho' ),
				'url'        => add_query_arg(
					array(
						'action'     => ReviewQueueScreen::ASSIGN_ACT,
						'profile_id' => $id,
						'_wpnonce'   => ActionGuard::nonce_for( ReviewQueueScreen::ASSIGN_ACT ),
					),
					admin_url( 'admin-post.php' )
				),
				'capability' => ReviewQueueScreen::CAPABILITY,
			),
		);
		if ( $this->item_id( $item ) > 0 ) {
			$actions['wiw-approve'] = array(
				'label'      => __( 'Approve', 'whoiswho' ),
				'url'        => $this->step_url( 'approve', array( $id ) ),
				'capability' => ReviewItemScreen::CAPABILITY,
			);
			$actions['wiw-reject']  = array(
				'label'      => __( 'Reject', 'whoiswho' ),
				'url'        => $this->step_url( 'reject', array( $id ) ),
				'capability' => ReviewItemScreen::CAPABILITY,
			);
		}
		return $actions;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function bulk_action_definitions(): array {
		return array(
			'approve' => array(
				'label'      => __( 'Approve', 'whoiswho' ),
				'capability' => ReviewItemScreen::CAPABILITY,
				/* translators: %d: number of items approved. */
				'done'       => __( '%d approved', 'whoiswho' ),
				'fields'     => array( self::class, 'checklist_fields' ),
				'handler'    => array( self::class, 'approve_one' ),
			),
			'reject'  => array(
				'label'       => __( 'Reject', 'whoiswho' ),
				'capability'  => ReviewItemScreen::CAPABILITY,
				'destructive' => true,
				/* translators: %d: number of items rejected. */
				'done'        => __( '%d rejected', 'whoiswho' ),
				'fields'      => array( self::class, 'reject_fields' ),
				'handler'     => array( self::class, 'reject_one' ),
			),
			'assign'  => array(
				'label'      => __( 'Assign to me', 'whoiswho' ),
				'capability' => ReviewQueueScreen::CAPABILITY,
				/* translators: %d: number of items assigned. */
				'done'       => __( '%d assigned to you', 'whoiswho' ),
				'handler'    => array( self::class, 'assign_one' ),
			),
		);
	}

	/**
	 * Approve one row: the same path as the item screen (checklist, fact gate, review service, state machine and audit).
	 *
	 * @param int     $id      Profile.
	 * @param Request $request Request: c1 to c5.
	 * @return bool|\WP_Error
	 */
	public static function approve_one( int $id, Request $request ) {
		$ticked = 0;
		foreach ( array_keys( ReviewItemScreen::checklist() ) as $key ) {
			$ticked += '' !== $request->string( $key ) ? 1 : 0;
		}
		try {
			( new ReviewItemScreen() )->approve( $id, get_current_user_id(), $ticked );
		} catch ( StateException $e ) {
			return new \WP_Error( 'wiw_review_refused', $e->getMessage() );
		}
		return true;
	}

	/**
	 * Reject one row with the shared reason, through the review service.
	 *
	 * @param int     $id      Profile.
	 * @param Request $request Request: reason, reason_code.
	 * @return bool|\WP_Error
	 */
	public static function reject_one( int $id, Request $request ) {
		$reason = trim( $request->string( 'reason' ) );
		if ( '' === $reason ) {
			return new \WP_Error( 'wiw_review_refused', 'A reason is required to reject.' );
		}
		try {
			( new ReviewService() )->reject( $id, $reason, get_current_user_id(), DecisionLog::normalise_code( $request->string( 'reason_code' ) ) );
		} catch ( StateException $e ) {
			return new \WP_Error( 'wiw_review_refused', $e->getMessage() );
		}
		return true;
	}

	/**
	 * Assign one row to the current user (audited), as the single Assign to me does.
	 *
	 * @param int $id Profile.
	 */
	public static function assign_one( int $id ): bool {
		return ReviewQueueScreen::assign_to_current_user( $id );
	}

	/**
	 * The checklist for a bulk approve: ticked once, it applies to every selected row.
	 */
	public static function checklist_fields(): string {
		$out = '';
		foreach ( ReviewItemScreen::checklist() as $key => $label ) {
			$out .= Form::checkbox( $key, $label, false, array( 'required' => true ) );
		}
		return Form::section(
			__( 'Checklist', 'whoiswho' ),
			$out,
			array(
				'description' => __( 'Tick all five points. They apply to every row listed above; each row still has to pass its own checks.', 'whoiswho' ),
				'level'       => 3,
				'class'       => 'wiw-card--inset',
			)
		);
	}

	/**
	 * The shared reason and reason code for a bulk reject.
	 */
	public static function reject_fields(): string {
		$codes = array();
		foreach ( DecisionLog::REASON_CODES as $code ) {
			$codes[ $code ] = Labels::reason( $code );
		}
		return Form::text(
			'reason',
			__( 'Reason', 'whoiswho' ),
			'',
			array(
				'required' => true,
				'help'     => __( 'The same reason is recorded for every row listed above.', 'whoiswho' ),
			)
		) . Form::select( 'reason_code', __( 'Reason code', 'whoiswho' ), $codes );
	}
}
