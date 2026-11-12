<?php
/**
 * Same-day staff deactivation (REQ-235, REQ-051). A leaver is marked with the user meta wiw_deactivated_at (the key the staff
 * notification audience reads), cannot sign in, and every session they hold ends at once. Deactivating and reactivating are
 * audited with actor, subject and reason. The one capability needed is the core edit_users, which only the Administrator has.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Users;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Mail\StaffAudience;

/**
 * Deactivation service and its admin hooks.
 */
final class Deactivation {

	public const CAPABILITY = 'edit_users';
	public const META       = StaffAudience::DEACTIVATED_META;
	public const ACTION     = 'wiw_user_deactivation';

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger $audit Audit logger.
	 */
	public function __construct( AuditLogger $audit ) {
		$this->audit = $audit;
	}

	/**
	 * The service for this site.
	 */
	public static function for_site(): self {
		return new self( AuditLogger::for_site() );
	}

	/**
	 * Attach hooks: the sign-in block, the Users list link and the action handler.
	 */
	public function register(): void {
		add_filter( 'authenticate', array( self::class, 'block_sign_in' ), 100 );
		add_filter( 'user_row_actions', array( $this, 'row_actions' ), 10, 2 );
		ActionGuard::register( self::ACTION, self::CAPABILITY, array( $this, 'handle' ) );
	}

	/**
	 * Whether a user is deactivated.
	 *
	 * @param int $user_id User id.
	 */
	public static function is_deactivated( int $user_id ): bool {
		return '' !== (string) get_user_meta( $user_id, self::META, true );
	}

	/**
	 * Refuse sign-in for a deactivated user, with the same generic error every failure gets.
	 *
	 * @param mixed $user A WP_User after the password check, a WP_Error or null.
	 * @return mixed
	 */
	public static function block_sign_in( $user ) {
		if ( $user instanceof \WP_User && self::is_deactivated( (int) $user->ID ) ) {
			return new \WP_Error( 'wiw_deactivated', __( 'Sign in failed. Check your details and try again.', 'whoiswho' ) );
		}
		return $user;
	}

	/**
	 * Deactivate a user: mark, end every session, audit.
	 *
	 * @param int    $user_id User to deactivate.
	 * @param string $reason  Why.
	 * @throws \DomainException When the actor may not, or tries to deactivate themselves.
	 */
	public function deactivate( int $user_id, string $reason ): void {
		$this->require_permission( $user_id );
		if ( self::is_deactivated( $user_id ) ) {
			return;
		}
		update_user_meta( $user_id, self::META, gmdate( 'Y-m-d H:i:s' ) );
		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		$this->audit->log(
			'user.deactivate',
			array(
				'type' => 'user',
				'id'   => $user_id,
			),
			false,
			true,
			$reason
		);
	}

	/**
	 * Reactivate a user.
	 *
	 * @param int    $user_id User to reactivate.
	 * @param string $reason  Why.
	 * @throws \DomainException When the actor may not.
	 */
	public function reactivate( int $user_id, string $reason ): void {
		$this->require_permission( $user_id );
		if ( ! self::is_deactivated( $user_id ) ) {
			return;
		}
		delete_user_meta( $user_id, self::META );
		$this->audit->log(
			'user.reactivate',
			array(
				'type' => 'user',
				'id'   => $user_id,
			),
			true,
			false,
			$reason
		);
	}

	/**
	 * Add Deactivate or Reactivate to a user's row on the Users list.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param mixed                 $user    The row's user (a WP_User).
	 * @return array<string, string>
	 */
	public function row_actions( array $actions, $user ): array {
		if ( ! current_user_can( self::CAPABILITY ) || ! $user instanceof \WP_User || get_current_user_id() === (int) $user->ID ) {
			return $actions;
		}
		$on   = self::is_deactivated( (int) $user->ID );
		$link = add_query_arg(
			array(
				'action'   => self::ACTION,
				'user'     => (int) $user->ID,
				'activate' => $on ? '1' : '0',
				'reason'   => rawurlencode( $on ? 'Reactivated from the Users list' : 'Deactivated from the Users list' ),
				'_wpnonce' => ActionGuard::nonce_for( self::ACTION ),
			),
			admin_url( 'admin-post.php' )
		);

		$actions['wiw_deactivation'] = '<a href="' . esc_url( $link ) . '">' . esc_html( $on ? __( 'Reactivate', 'whoiswho' ) : __( 'Deactivate', 'whoiswho' ) ) . '</a>';
		return $actions;
	}

	/**
	 * The admin-post handler (capability and nonce are already checked by the guard).
	 *
	 * @param Request $request Request.
	 */
	public function handle( Request $request ): void {
		$user   = $request->int( 'user' );
		$reason = trim( rawurldecode( $request->string( 'reason' ) ) );
		try {
			if ( '1' === $request->string( 'activate' ) ) {
				$this->reactivate( $user, $reason );
			} else {
				$this->deactivate( $user, $reason );
			}
		} catch ( \DomainException $e ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'whoiswho' ), '', array( 'response' => 403 ) );
		}
		wp_safe_redirect( admin_url( 'users.php' ) );
		exit;
	}

	/**
	 * Only edit_users, and never on yourself.
	 *
	 * @param int $user_id Subject.
	 * @throws \DomainException When not allowed.
	 */
	private function require_permission( int $user_id ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			throw new \DomainException( 'Only a user who can manage users may deactivate or reactivate accounts.' );
		}
		if ( get_current_user_id() === $user_id ) {
			throw new \DomainException( 'You cannot deactivate your own account.' );
		}
	}
}
