<?php
/**
 * What the public account pages do for a Profile owner (REQ-274): sign in through core wp_signon (so the guard's lockout per
 * account and per address and its one generic error apply, and an email address works as the sign-in name), change the account's
 * name and password, send a password-reset email, and choose a new password from a core reset key. Every sign-in failure and every
 * lost-password answer reads the same whether or not an account exists. Changes to an account are audited with the account id and
 * the names of the changed fields, never their values.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust\Account;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Account service.
 */
final class AccountService {

	public const SIGNIN_FAILED = 'Sign in failed. Check your details and try again.';
	public const LOST_SENT     = 'If an account uses that address, we have sent it an email with a link to choose a new password.';

	/**
	 * Owner accounts (for the reset email).
	 *
	 * @var OwnerAccounts
	 */
	private OwnerAccounts $accounts;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param OwnerAccounts|null $accounts Owner accounts.
	 * @param AuditLogger|null   $audit    Audit logger.
	 */
	public function __construct( ?OwnerAccounts $accounts = null, ?AuditLogger $audit = null ) {
		$this->audit    = $audit ?? AuditLogger::for_site();
		$this->accounts = $accounts ?? new OwnerAccounts( $this->audit );
	}

	/**
	 * Sign in with an email address (or login name) and password.
	 *
	 * @param string $login    Email or login name.
	 * @param string $password Password.
	 * @param bool   $remember Keep me signed in.
	 * @return int The signed-in user id.
	 * @throws \DomainException With the one generic message on any failure.
	 */
	public function sign_in( string $login, string $password, bool $remember ): int {
		$login = trim( $login );
		if ( '' === $login || '' === $password ) {
			throw new \DomainException( esc_html( self::SIGNIN_FAILED ) );
		}
		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => $remember,
			),
			is_ssl()
		);
		if ( ! $user instanceof \WP_User ) {
			throw new \DomainException( esc_html( self::SIGNIN_FAILED ) );
		}
		wp_set_current_user( (int) $user->ID );
		return (int) $user->ID;
	}

	/**
	 * Send the reset email when the address belongs to a Profile owner account. The caller always shows LOST_SENT.
	 *
	 * @param string $email Address typed.
	 * @return bool Whether an email was sent (server side only; never shown).
	 */
	public function lost_password( string $email ): bool {
		$email = sanitize_email( $email );
		$user  = '' !== $email ? get_user_by( 'email', $email ) : false;
		return $user instanceof \WP_User && $this->accounts->send_reset( (int) $user->ID );
	}

	/**
	 * The account a reset link is for, when its key is valid and it is a Profile owner account.
	 *
	 * @param int    $user_id User id from the link.
	 * @param string $key     Key from the link.
	 */
	public function reset_user( int $user_id, string $key ): ?\WP_User {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! $user instanceof \WP_User || '' === $key || ! OwnerRole::is_owner_only( (int) $user->ID ) ) {
			return null;
		}
		$checked = check_password_reset_key( $key, (string) $user->user_login );
		return $checked instanceof \WP_User ? $checked : null;
	}

	/**
	 * Problems with a new password: the two copies must match, and the site's password rules (the guard's, through the core hook
	 * user_profile_update_errors) must pass.
	 *
	 * @param \WP_User $user     Account.
	 * @param string   $password New password.
	 * @param string   $repeat   The second copy.
	 * @return string[] Messages; empty when acceptable.
	 */
	public function password_problems( \WP_User $user, string $password, string $repeat ): array {
		if ( '' === $password ) {
			return array( __( 'Enter a new password.', 'whoiswho' ) );
		}
		if ( $password !== $repeat ) {
			return array( __( 'The two passwords do not match.', 'whoiswho' ) );
		}
		$errors           = new \WP_Error();
		$check            = new \stdClass();
		$check->ID        = (int) $user->ID;
		$check->user_pass = $password;
		do_action_ref_array( 'user_profile_update_errors', array( &$errors, true, &$check ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own hook, so the site's password rules apply.
		return array_map( 'wp_strip_all_tags', $errors->get_error_messages() );
	}

	/**
	 * Choose a new password from a valid reset link.
	 *
	 * @param \WP_User $user     Account the key was checked for.
	 * @param string   $password New password.
	 * @param string   $repeat   Second copy.
	 * @return string[] Problems; empty when the password was changed.
	 */
	public function reset( \WP_User $user, string $password, string $repeat ): array {
		$problems = $this->password_problems( $user, $password, $repeat );
		if ( array() !== $problems ) {
			return $problems;
		}
		reset_password( $user, $password );
		$this->audit->log( 'owner.password_reset', $this->subject( (int) $user->ID ), null, array( 'fields' => array( 'password' ) ), 'Profile owner chose a new password from an email link' );
		return array();
	}

	/**
	 * Change the signed-in owner's name and, when a new password is given, the password (the current one is required).
	 *
	 * @param int    $user_id  Signed-in owner.
	 * @param string $name     Display name.
	 * @param string $current  Current password (for a password change).
	 * @param string $password New password, or empty to keep it.
	 * @param string $repeat   Second copy.
	 * @return array{errors: string[], changed: string[]}
	 */
	public function update_details( int $user_id, string $name, string $current, string $password, string $repeat ): array {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || ! OwnerRole::is_owner_only( $user_id ) ) {
			return array(
				'errors'  => array( __( 'Sign in again to change your details.', 'whoiswho' ) ),
				'changed' => array(),
			);
		}
		$name    = trim( sanitize_text_field( $name ) );
		$errors  = array();
		$changed = array();
		if ( '' === $name || strlen( $name ) > 120 ) {
			$errors[] = __( 'Enter your name (up to 120 characters).', 'whoiswho' );
		}
		if ( '' !== $password ) {
			if ( ! wp_check_password( $current, (string) $user->user_pass, $user_id ) ) {
				$errors[] = __( 'Your current password is not right.', 'whoiswho' );
			} else {
				$errors = array_merge( $errors, $this->password_problems( $user, $password, $repeat ) );
			}
		}
		if ( array() !== $errors ) {
			return array(
				'errors'  => $errors,
				'changed' => array(),
			);
		}
		if ( $name !== (string) $user->display_name ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name,
					'first_name'   => (string) strtok( $name, ' ' ),
				)
			);
			$changed[] = 'name';
		}
		if ( '' !== $password ) {
			wp_set_password( $password, $user_id );
			// Changing the password ends every session; keep this one signed in.
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, false, is_ssl() );
			$changed[] = 'password';
		}
		if ( array() !== $changed ) {
			$this->audit->log( 'owner.details_changed', $this->subject( $user_id ), null, array( 'fields' => $changed ), 'Profile owner changed their account details' );
		}
		return array(
			'errors'  => array(),
			'changed' => $changed,
		);
	}

	/**
	 * Audit subject.
	 *
	 * @param int $user_id User.
	 * @return array{type: string, id: int}
	 */
	private function subject( int $user_id ): array {
		return array(
			'type' => 'user',
			'id'   => $user_id,
		);
	}
}
