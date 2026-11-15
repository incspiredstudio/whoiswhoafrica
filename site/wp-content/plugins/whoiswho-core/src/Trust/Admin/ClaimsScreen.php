<?php
/**
 * The Claims admin screen (REQ-252, REQ-256, REQ-275): a shared staff list table (ClaimsTable) with everything the claimant
 * entered, the decide-by date, the overdue marker and the reminder state, bulk Approve and Reject and row quick actions; and one
 * claim's page (?claim=) with Approve and create account (REQ-274), Approve and Reject (a reason is required) in the staff form
 * kit. Needs wiw_review_claims; every post is nonce-checked by ActionGuard and decided through ClaimDecision.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust\Admin;

use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Trust\Claim;
use WhoIsWho\Core\Trust\ClaimDecision;
use WhoIsWho\Core\Trust\ClaimRepository;

/**
 * Claims screen.
 */
final class ClaimsScreen {

	public const SCREEN  = 'wiw-claims';
	public const ACTION  = 'wiw_decide_claim';
	public const CAP     = ClaimDecision::CAPABILITY;
	public const FILTERS = array( 'new', 'approved', 'rejected', 'all' );

	/**
	 * Claims.
	 *
	 * @var ClaimRepository
	 */
	private ClaimRepository $claims;

	/**
	 * Decisions.
	 *
	 * @var ClaimDecision
	 */
	private ClaimDecision $decision;

	/**
	 * Clock returning a UTC "Y-m-d H:i:s" string.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param ClaimRepository $claims   Claims.
	 * @param ClaimDecision   $decision Decisions.
	 * @param callable|null   $clock    Clock.
	 */
	public function __construct( ClaimRepository $claims, ClaimDecision $decision, ?callable $clock = null ) {
		$this->claims   = $claims;
		$this->decision = $decision;
		$this->clock    = $clock ?? static fn (): string => gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Screen for this site.
	 */
	public static function for_site(): self {
		$claims = new ClaimRepository();
		return new self( $claims, new ClaimDecision( $claims, new StateRepository(), Notifier::for_site() ) );
	}

	/**
	 * Register the screen and the decision action, in the admin only.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				$registry = ScreenRegistry::shared();
				$registry->register(
					self::SCREEN,
					__( 'Claims', 'whoiswho' ),
					self::CAP,
					static function (): void {
						self::for_site()->render( Request::from_globals() );
					},
					false,
					'review'
				);
				$registry->hook();
				ClaimsTable::register_bulk_handler();
				ActionGuard::register(
					self::ACTION,
					self::CAP,
					static function ( Request $request ): void {
						$notice = self::for_site()->handle( $request, get_current_user_id() );
						wp_safe_redirect( add_query_arg( 'wiw_notice', rawurlencode( $notice ), admin_url( 'admin.php?page=' . self::SCREEN ) ) );
						exit;
					}
				);
			},
			1
		);
	}

	/**
	 * Decide a claim. A refusal changes nothing and comes back as the message.
	 *
	 * @param Request $request Request (claim_id, decision, reason).
	 * @param int     $user_id Acting user.
	 * @return string Message for the person.
	 */
	public function handle( Request $request, int $user_id ): string {
		$id = $request->int( 'claim_id' );
		try {
			if ( 'approve_account' === $request->string( 'decision' ) ) {
				$result = $this->decision->approve_with_account( $id, $user_id );
				if ( ! $result['emailed'] ) {
					return 'Claim approved and the profile owner account is ready, but the "Set your password" email could not be sent. Check the mail settings, then ask the owner to use "Forgotten your password?" on the sign-in page.';
				}
				return $result['created'] ? 'Claim approved. A profile owner account was created and the "Set your password" email was sent.' : 'Claim approved. The existing profile owner account now owns this profile too, and a "Set your password" email was sent.';
			}
			if ( 'approve' === $request->string( 'decision' ) ) {
				$this->decision->approve( $id, $user_id );
				return 'Claim approved.';
			}
			if ( 'reject' === $request->string( 'decision' ) ) {
				$this->decision->reject( $id, $user_id, $request->string( 'reason' ) );
				return 'Claim rejected.';
			}
		} catch ( \DomainException $refusal ) {
			return $refusal->getMessage();
		}
		return 'Nothing was changed.';
	}

	/**
	 * Whether a claim is open and past its decide-by time.
	 *
	 * @param Claim $claim Claim.
	 */
	public function is_overdue( Claim $claim ): bool {
		return $claim->is_open() && $claim->decide_by_at < ( $this->clock )();
	}


	/**
	 * The decisions service (the bulk actions use the same one).
	 */
	public function decision(): ClaimDecision {
		return $this->decision;
	}

	/**
	 * The address of one claim's page.
	 *
	 * @param int $id Claim id.
	 */
	public static function claim_url( int $id ): string {
		return add_query_arg(
			array(
				'page'  => self::SCREEN,
				'claim' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * One claim: everything the claimant entered, then the decision forms while it is open.
	 *
	 * @param Claim $claim Claim.
	 */
	private function detail( Claim $claim ): string {
		$rows  = array(
			'Reference'       => $claim->reference,
			'Profile'         => trim( get_the_title( $claim->profile_id ) . ' (#' . $claim->profile_id . ')' ),
			'Relationship'    => $claim->relationship,
			'Name'            => $claim->name,
			'Role'            => $claim->role,
			'Work email'      => $claim->work_email,
			'Phone'           => $claim->phone,
			'Evidence'        => $claim->evidence,
			'Consent given'   => (string) $claim->consent_at,
			'Status'          => $claim->status,
			'Received'        => $claim->received_at,
			'Decide by'       => $claim->decide_by_at,
			'Last reminder'   => null === $claim->last_reminder_at ? 'None sent' : $claim->last_reminder_at,
			'Decision reason' => $claim->reason,
		);
		$table = '<table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) {
			$table .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( $value ) ) . '</td></tr>';
		}
		$table  .= '</tbody></table>';
		$overdue = $this->is_overdue( $claim ) ? ' ' . ListTable::pill( __( 'Overdue', 'whoiswho' ), 'danger' ) : '';
		$out     = '<h1>' . esc_html( $claim->reference ) . $overdue . '</h1>';
		$out    .= '<p><a href="' . esc_url( add_query_arg( 'page', self::SCREEN, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Back to the list', 'whoiswho' ) . '</a></p>';
		$out    .= Form::section( __( 'What the claimant sent', 'whoiswho' ), $table, array( 'id' => 'wiw-claim-details' ) );
		if ( ! $claim->is_open() ) {
			return $out . '<p>' . esc_html__( 'This claim has been decided.', 'whoiswho' ) . '</p>';
		}
		$out   .= Form::section(
			__( 'Approve', 'whoiswho' ),
			'<div class="wiw-actions">' . $this->form( $claim, 'approve_account', '', 'button button-primary' ) . ' ' . $this->form( $claim, 'approve', '', 'button' ) . '</div>',
			array(
				'id'          => 'wiw-claim-approve',
				'description' => __( 'Approve and create account gives the claimant a profile owner account and sends the "Set your password" email. Approve only marks the profile claimed.', 'whoiswho' ),
			)
		);
		$reason = Form::text(
			'reason',
			__( 'Reason', 'whoiswho' ),
			'',
			array(
				'required' => true,
				'id'       => 'wiw-f-claim-reason',
				'help'     => __( 'Required. The reason is sent to the claimant.', 'whoiswho' ),
			)
		);
		return $out . Form::section(
			__( 'Reject', 'whoiswho' ),
			$this->form( $claim, 'reject', $reason, 'button wiw-button--danger' ),
			array( 'id' => 'wiw-claim-reject' )
		);
	}

	/**
	 * A decision form.
	 *
	 * @param Claim  $claim    Claim.
	 * @param string $decision approve_account, approve or reject.
	 * @param string $extra    Extra fields (escaped); with fields, the button sits in an actions bar.
	 * @param string $button   Button classes.
	 */
	private function form( Claim $claim, string $decision, string $extra, string $button ): string {
		$hidden = '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />'
			. '<input type="hidden" name="claim_id" value="' . (int) $claim->id . '" />'
			. '<input type="hidden" name="decision" value="' . esc_attr( $decision ) . '" />';
		$labels = array(
			'approve_account' => __( 'Approve and create account', 'whoiswho' ),
			'approve'         => __( 'Approve', 'whoiswho' ),
			'reject'          => __( 'Reject', 'whoiswho' ),
		);
		$submit = '<button type="submit" class="' . esc_attr( $button ) . '">' . esc_html( $labels[ $decision ] ?? $decision ) . '</button>';
		return Form::open( $hidden, array( 'class' => 'wiw-claim-decision' ) ) . ( '' === $extra ? $submit : $extra . Form::actions( $submit ) ) . '</form>';
	}

	/**
	 * The screen markup: the list, or one claim when the request names one (?claim=).
	 *
	 * @param string       $filter  Status filter.
	 * @param string       $notice  Message after an action.
	 * @param Request|null $request Request (paging, the bulk step, the claim); none for the plain list.
	 * @throws \DomainException When the user may not open the screen.
	 */
	public function html( string $filter = 'new', string $notice = '', ?Request $request = null ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to review claims.' );
		}
		$request = $request ?? new Request( array() );
		$filter  = in_array( $filter, self::FILTERS, true ) ? $filter : 'new';
		$note    = '' !== $notice ? '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>' : '';
		$claim   = $request->int( 'claim' ) > 0 ? $this->claims->find( $request->int( 'claim' ) ) : null;
		if ( null !== $claim ) {
			return '<div class="wrap wiw-staff">' . $note . $this->detail( $claim ) . '</div>';
		}
		$out   = '<div class="wrap wiw-staff"><h1>' . esc_html__( 'Claims', 'whoiswho' ) . '</h1>' . $note;
		$links = array();
		foreach ( self::FILTERS as $key ) {
			$current = $key === $filter ? ' class="current" aria-current="page"' : '';
			$links[] = '<a href="' . esc_url( add_query_arg( 'status', $key, admin_url( 'admin.php?page=' . self::SCREEN ) ) ) . '"' . $current . '>' . esc_html( ucfirst( $key ) ) . '</a>';
		}
		$out .= '<ul class="subsubsub"><li>' . implode( ' |</li> <li>', $links ) . '</li></ul><br class="clear" />';
		return $out . ( new ClaimsTable( $filter, $this ) )->html( $request ) . '</div>';
	}

	/**
	 * Print the screen.
	 *
	 * @param Request $request Request.
	 */
	public function render( Request $request ): void {
		echo $this->html( $request->string( 'status' ), $request->string( 'wiw_notice' ), $request ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
	}
}
