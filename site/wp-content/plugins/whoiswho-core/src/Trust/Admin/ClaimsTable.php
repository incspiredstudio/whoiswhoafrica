<?php
/**
 * The Claims list as a shared staff list table (REQ-252, REQ-256, REQ-275). Each row shows everything the claimant entered, the
 * decide-by date, the overdue marker and the reminder state. Row actions: View (the claim with its decision forms), and for an
 * open claim Approve and Reject, which open the confirmation step for that one row. Bulk actions repeat the same single actions
 * through ClaimDecision, which checks wiw_review_claims and writes the audit row for each claim: Approve, and Reject with one
 * shared reason (sent to every claimant listed). Approve and create account stays a single action on the claim's own page: its
 * outcome (account created or reused, email sent or not) needs its own message. Decided claims have no checkbox.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust\Admin;

use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Trust\Claim;
use WhoIsWho\Core\Trust\ClaimRepository;

/**
 * Claims table.
 */
final class ClaimsTable extends ListTable {

	public const BULK = 'wiw_claims_bulk';

	/**
	 * The status filter (new, approved, rejected or all).
	 *
	 * @var string
	 */
	private string $filter;

	/**
	 * The screen, for the overdue rule and the decisions.
	 *
	 * @var ClaimsScreen|null
	 */
	private ?ClaimsScreen $host;

	/**
	 * Constructor.
	 *
	 * @param string            $filter Status filter.
	 * @param ClaimsScreen|null $screen Screen; the site's by default.
	 */
	public function __construct( string $filter = 'new', ?ClaimsScreen $screen = null ) {
		parent::__construct( 'claim', 'claims' );
		$this->filter = in_array( $filter, ClaimsScreen::FILTERS, true ) ? $filter : 'new';
		$this->host   = $screen;
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
		return ClaimsScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return ClaimsScreen::SCREEN;
	}

	/**
	 * The screen.
	 */
	private function host(): ClaimsScreen {
		if ( null === $this->host ) {
			$this->host = ClaimsScreen::for_site();
		}
		return $this->host;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'claim'    => __( 'Claim', 'whoiswho' ),
			'claimant' => __( 'Claimant', 'whoiswho' ),
			'contact'  => __( 'Contact', 'whoiswho' ),
			'evidence' => __( 'Evidence', 'whoiswho' ),
			'status'   => __( 'Status', 'whoiswho' ),
			'dates'    => __( 'Dates', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'claim';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		$claims = ( new ClaimRepository() )->list_by_status( 'all' === $this->filter ? '' : $this->filter );
		$items  = array();
		foreach ( array_slice( $claims, ( $page - 1 ) * $per_page, $per_page ) as $claim ) {
			$items[] = array( 'claim' => $claim );
		}
		return array(
			'items' => $items,
			'total' => count( $claims ),
		);
	}

	/**
	 * The claim of a row.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	private static function claim_of( array $item ): Claim {
		return $item['claim'];
	}

	/**
	 * Only an open claim can be decided, so only an open claim has a checkbox.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_id( array $item ): int {
		$claim = self::claim_of( $item );
		return $claim->is_open() ? $claim->id : 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_name( array $item ): string {
		return self::claim_of( $item )->reference;
	}

	/**
	 * The claim reference, for the confirmation step.
	 *
	 * @param int $id Claim id.
	 */
	protected function name_for_id( int $id ): string {
		$claim = ( new ClaimRepository() )->find( $id );
		return null === $claim ? '#' . $id : $claim->reference;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No claims in this list.', 'whoiswho' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Request $request Request.
	 */
	protected function screen_args( Request $request ): array {
		return array_merge( array( 'status' => $this->filter ), parent::screen_args( $request ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		$claim = self::claim_of( $item );
		switch ( $column ) {
			case 'claim':
				$title   = '' !== (string) get_the_title( $claim->profile_id ) ? (string) get_the_title( $claim->profile_id ) : '#' . $claim->profile_id;
				$overdue = $this->host()->is_overdue( $claim ) ? ' ' . self::pill( __( 'Overdue', 'whoiswho' ), 'danger' ) : '';
				return '<a class="row-title" href="' . esc_url( ClaimsScreen::claim_url( $claim->id ) ) . '">' . esc_html( $claim->reference ) . '</a>' . $overdue
					. '<br /><span class="description">' . esc_html__( 'Profile', 'whoiswho' ) . ': ' . esc_html( $title ) . ' (#' . esc_html( (string) $claim->profile_id ) . ')</span>';
			case 'claimant':
				return '<strong>' . esc_html( $claim->name ) . '</strong><br />' . esc_html( $claim->role ) . '<br /><span class="description">' . esc_html( $claim->relationship ) . '</span>';
			case 'contact':
				return esc_html( $claim->work_email ) . '<br />' . esc_html( $claim->phone );
			case 'evidence':
				return nl2br( esc_html( $claim->evidence ) );
			case 'status':
				$tones = array(
					Claim::NEW      => 'info',
					Claim::APPROVED => 'success',
					Claim::REJECTED => 'neutral',
				);
				$out   = self::pill( ucfirst( $claim->status ), $tones[ $claim->status ] ?? 'neutral' );
				return '' !== $claim->reason ? $out . '<br /><span class="description">' . esc_html( $claim->reason ) . '</span>' : $out;
			case 'dates':
				$lines = array(
					__( 'Received', 'whoiswho' )      => $claim->received_at,
					__( 'Decide by', 'whoiswho' )     => $claim->decide_by_at,
					__( 'Last reminder', 'whoiswho' ) => null === $claim->last_reminder_at ? __( 'None sent', 'whoiswho' ) : $claim->last_reminder_at,
					__( 'Consent given', 'whoiswho' ) => (string) $claim->consent_at,
				);
				$out   = array();
				foreach ( $lines as $label => $value ) {
					$out[] = '<span class="description">' . esc_html( $label ) . ':</span> ' . esc_html( (string) $value );
				}
				return implode( '<br />', $out );
		}//end switch
		return '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$claim   = self::claim_of( $item );
		$actions = array(
			'view' => array(
				'label'      => __( 'View', 'whoiswho' ),
				'url'        => ClaimsScreen::claim_url( $claim->id ),
				'capability' => ClaimsScreen::CAP,
			),
		);
		if ( $claim->is_open() ) {
			$actions['wiw-approve'] = array(
				'label'      => __( 'Approve', 'whoiswho' ),
				'url'        => $this->step_url( 'approve', array( $claim->id ) ),
				'capability' => ClaimsScreen::CAP,
			);
			$actions['wiw-reject']  = array(
				'label'      => __( 'Reject', 'whoiswho' ),
				'url'        => $this->step_url( 'reject', array( $claim->id ) ),
				'capability' => ClaimsScreen::CAP,
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
				'capability' => ClaimsScreen::CAP,
				/* translators: %d: number of claims approved. */
				'done'       => __( '%d approved', 'whoiswho' ),
				'handler'    => array( $this, 'approve_one' ),
			),
			'reject'  => array(
				'label'       => __( 'Reject', 'whoiswho' ),
				'capability'  => ClaimsScreen::CAP,
				'destructive' => true,
				/* translators: %d: number of claims rejected. */
				'done'        => __( '%d rejected', 'whoiswho' ),
				'fields'      => array( self::class, 'reject_fields' ),
				'handler'     => array( $this, 'reject_one' ),
			),
		);
	}

	/**
	 * Approve one claim through ClaimDecision, as the single Approve does (capability, state and audit per claim).
	 *
	 * @param int $id Claim id.
	 * @return bool|\WP_Error
	 */
	public function approve_one( int $id ) {
		try {
			$this->host()->decision()->approve( $id, get_current_user_id() );
		} catch ( \DomainException $e ) {
			return new \WP_Error( 'wiw_claim_refused', $e->getMessage() );
		}
		return true;
	}

	/**
	 * Reject one claim with the shared reason through ClaimDecision, as the single Reject does.
	 *
	 * @param int     $id      Claim id.
	 * @param Request $request Request: reason.
	 * @return bool|\WP_Error
	 */
	public function reject_one( int $id, Request $request ) {
		try {
			$this->host()->decision()->reject( $id, get_current_user_id(), $request->string( 'reason' ) );
		} catch ( \DomainException $e ) {
			return new \WP_Error( 'wiw_claim_refused', $e->getMessage() );
		}
		return true;
	}

	/**
	 * The shared reason for a bulk reject.
	 */
	public static function reject_fields(): string {
		return Form::text(
			'reason',
			__( 'Reason', 'whoiswho' ),
			'',
			array(
				'required' => true,
				'help'     => __( 'The same reason is recorded and sent to every claimant listed above.', 'whoiswho' ),
			)
		);
	}
}
