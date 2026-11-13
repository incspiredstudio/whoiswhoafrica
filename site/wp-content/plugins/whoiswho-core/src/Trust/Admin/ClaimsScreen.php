<?php
/**
 * The Claims admin screen (REQ-252, REQ-256): one card per claim with everything the claimant entered, the decide-by date, the
 * overdue marker and the reminder state, and Approve and Reject (a reason is required). Needs wiw_review_claims; every post is
 * nonce-checked by ActionGuard and decided through ClaimDecision.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust\Admin;

use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
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
	 * One card.
	 *
	 * @param Claim $claim Claim.
	 */
	private function card( Claim $claim ): string {
		$overdue = $this->is_overdue( $claim );
		$rows    = array(
			'Reference'       => $claim->reference,
			'Profile'         => '#' . $claim->profile_id,
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
		$out     = '<div class="card" id="claim-' . (int) $claim->id . '"><h2>' . esc_html( $claim->reference ) . ( $overdue ? ' <span class="wiw-overdue">' . esc_html__( 'Overdue', 'whoiswho' ) . '</span>' : '' ) . '</h2><dl>';
		foreach ( $rows as $label => $value ) {
			$out .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
		}
		$out .= '</dl>';
		if ( $claim->is_open() ) {
			$out .= $this->form( $claim, 'approve', '' );
			$out .= $this->form( $claim, 'reject', '<label>' . esc_html__( 'Reason (required)', 'whoiswho' ) . ' <input type="text" name="reason" class="regular-text" /></label> ' );
		}
		return $out . '</div>';
	}

	/**
	 * A decision form.
	 *
	 * @param Claim  $claim    Claim.
	 * @param string $decision approve or reject.
	 * @param string $extra    Extra markup (escaped).
	 */
	private function form( Claim $claim, string $decision, string $extra ): string {
		$out  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out .= '<input type="hidden" name="claim_id" value="' . (int) $claim->id . '" />';
		$out .= '<input type="hidden" name="decision" value="' . esc_attr( $decision ) . '" />';
		return $out . $extra . '<button type="submit" class="button">' . esc_html( 'approve' === $decision ? __( 'Approve', 'whoiswho' ) : __( 'Reject', 'whoiswho' ) ) . '</button></form> ';
	}

	/**
	 * The screen markup.
	 *
	 * @param string $filter Status filter.
	 * @param string $notice Message after an action.
	 * @throws \DomainException When the user may not open the screen.
	 */
	public function html( string $filter = 'new', string $notice = '' ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to review claims.' );
		}
		$filter = in_array( $filter, self::FILTERS, true ) ? $filter : 'new';
		$out    = '<div class="wrap"><h1>' . esc_html__( 'Claims', 'whoiswho' ) . '</h1>';
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		$links = array();
		foreach ( self::FILTERS as $key ) {
			$links[] = '<a href="' . esc_url( add_query_arg( 'status', $key, admin_url( 'admin.php?page=' . self::SCREEN ) ) ) . '"' . ( $key === $filter ? ' class="current"' : '' ) . '>' . esc_html( ucfirst( $key ) ) . '</a>';
		}
		$out   .= '<p>' . implode( ' | ', $links ) . '</p>';
		$claims = $this->claims->list_by_status( 'all' === $filter ? '' : $filter );
		if ( array() === $claims ) {
			$out .= '<p>' . esc_html__( 'No claims in this list.', 'whoiswho' ) . '</p>';
		}
		foreach ( $claims as $claim ) {
			$out .= $this->card( $claim );
		}
		return $out . '</div>';
	}

	/**
	 * Print the screen.
	 *
	 * @param Request $request Request.
	 */
	public function render( Request $request ): void {
		echo wp_kses(
			$this->html( $request->string( 'status' ), $request->string( 'wiw_notice' ) ),
			array(
				'div'    => array(
					'class' => true,
					'id'    => true,
				),
				'h1'     => array(),
				'h2'     => array(),
				'p'      => array(),
				'span'   => array( 'class' => true ),
				'dl'     => array(),
				'dt'     => array(),
				'dd'     => array(),
				'a'      => array(
					'href'  => true,
					'class' => true,
				),
				'label'  => array(),
				'form'   => array(
					'method' => true,
					'action' => true,
				),
				'input'  => array(
					'type'  => true,
					'name'  => true,
					'value' => true,
					'class' => true,
				),
				'button' => array(
					'type'  => true,
					'class' => true,
				),
			)
		);
	}
}
