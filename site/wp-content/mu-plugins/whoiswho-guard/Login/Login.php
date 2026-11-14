<?php
/**
 * Sign-in protection for staff: account lockout, strong passwords, an idle timeout in wp-admin, one generic error for every
 * failure, and session revocation when a user's role or capabilities change. Uses only generic WordPress hooks.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Login;

/**
 * Login protection.
 */
final class Login {

	public const MAX_FAILURES = 5;
	// Failures from one client address, across any names, before that address is refused for the window (issue 810).
	public const MAX_ADDRESS_FAILURES = 20;
	public const WINDOW       = 900;
	// Seconds in which failures are counted, and how long a lock lasts (15 minutes).
	public const IDLE_LIMIT = 3600;
	// Idle timeout in wp-admin (60 minutes).
	public const MIN_LENGTH  = 12;
	public const META_ACTIVE = 'wiw_last_active';

	/**
	 * Attach hooks.
	 */
	public static function init(): void {
		// After core's own checks (priority 20), so a locked account is refused even with the right password.
		add_filter( 'authenticate', array( self::class, 'block_locked' ), 100, 2 );
		add_filter( 'authenticate', array( self::class, 'make_errors_generic' ), 101 );
		add_action( 'wp_login_failed', array( self::class, 'record_failure' ) );
		add_action( 'wp_login', array( self::class, 'clear_failures' ), 10, 2 );

		add_action( 'user_profile_update_errors', array( self::class, 'check_profile_password' ), 10, 3 );
		add_action( 'validate_password_reset', array( self::class, 'check_reset_password' ), 10, 2 );

		add_action( 'admin_init', array( self::class, 'enforce_idle_timeout' ) );

		add_action( 'set_user_role', array( self::class, 'revoke_sessions' ) );
		add_action( 'add_user_role', array( self::class, 'revoke_sessions' ) );
		add_action( 'remove_user_role', array( self::class, 'revoke_sessions' ) );
		add_action( 'updated_user_meta', array( self::class, 'revoke_on_capability_change' ), 10, 3 );
		add_action( 'added_user_meta', array( self::class, 'revoke_on_capability_change' ), 10, 3 );
	}

	/**
	 * Transient key for a username. Hashed, so it holds no name and works for unknown names too. A name that belongs to an
	 * account (its username or its email) is counted under the account id, so both spellings share one count (issue 810).
	 *
	 * @param string $username Username or email as typed.
	 */
	private static function key( string $username ): string {
		$name    = strtolower( trim( $username ) );
		$account = get_user_by( 'login', $name );
		$account = $account ? $account : get_user_by( 'email', $name );
		$subject = $account ? 'user:' . (int) $account->ID : 'name:' . $name;
		return 'wiw_lock_' . substr( hash_hmac( 'sha256', $subject, wp_salt( 'auth' ) ), 0, 40 );
	}

	/**
	 * Transient key for a client address (hashed, so no address is stored).
	 *
	 * @param string $address IP address.
	 */
	private static function address_key( string $address ): string {
		return 'wiw_lockip_' . substr( hash_hmac( 'sha256', $address, wp_salt( 'auth' ) ), 0, 40 );
	}

	/**
	 * The client address of this request: REMOTE_ADDR only, never a forwarded header a client can forge.
	 */
	private static function client_address(): string {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return false === filter_var( $address, FILTER_VALIDATE_IP ) ? '' : $address;
	}

	/**
	 * Failure timestamps in the window under a key.
	 *
	 * @param string $key Transient key.
	 * @return int[]
	 */
	private static function times( string $key ): array {
		$times = get_transient( $key );
		$times = is_array( $times ) ? array_map( 'intval', $times ) : array();
		$now   = time();
		return array_values( array_filter( $times, static fn ( int $t ): bool => $t > $now - self::WINDOW ) );
	}

	/**
	 * Failure timestamps in the window.
	 *
	 * @param string $username Username.
	 * @return int[]
	 */
	private static function failures( string $username ): array {
		return self::times( self::key( $username ) );
	}

	/**
	 * Whether a client address has failed too often in the window, across any names (password spraying, issue 810).
	 *
	 * @param string $address IP address.
	 */
	public static function address_locked( string $address ): bool {
		return '' !== $address && count( self::times( self::address_key( $address ) ) ) >= self::MAX_ADDRESS_FAILURES;
	}

	/**
	 * Whether the account is locked.
	 *
	 * @param string $username Username.
	 */
	public static function is_locked( string $username ): bool {
		return count( self::failures( $username ) ) >= self::MAX_FAILURES;
	}

	/**
	 * Refuse the attempt while locked, even with the right password, with the same message as any other failure.
	 *
	 * @param mixed  $user     Result so far.
	 * @param string $username Username typed.
	 * @return mixed
	 */
	public static function block_locked( $user, $username ) {
		if ( '' !== (string) $username && ( self::is_locked( (string) $username ) || self::address_locked( self::client_address() ) ) ) {
			return new \WP_Error( 'wiw_login_failed', self::generic_message() );
		}
		return $user;
	}

	/**
	 * Count a failed attempt, and log a lock with the account id only (never the name or email).
	 *
	 * @param string $username Username typed.
	 */
	public static function record_failure( $username ): void {
		$username = (string) $username;
		if ( '' === $username ) {
			return;
		}
		$times   = self::failures( $username );
		$times[] = time();
		set_transient( self::key( $username ), $times, self::WINDOW );

		$address = self::client_address();
		if ( '' !== $address ) {
			$by_address   = self::times( self::address_key( $address ) );
			$by_address[] = time();
			set_transient( self::address_key( $address ), array_slice( $by_address, -self::MAX_ADDRESS_FAILURES ), self::WINDOW );
		}

		if ( count( $times ) === self::MAX_FAILURES ) {
			$account = get_user_by( 'login', $username );
			$account = $account ? $account : get_user_by( 'email', $username );
			do_action( 'wiw_login_locked', $account ? (int) $account->ID : 0 );
		}
	}

	/**
	 * A successful sign-in clears the count.
	 *
	 * @param string   $user_login Login.
	 * @param \WP_User $user       User.
	 */
	public static function clear_failures( $user_login, $user = null ): void {
		delete_transient( self::key( (string) $user_login ) );
		// A new sign-in starts a fresh idle clock, so a stale timestamp from an old session cannot end it at once.
		if ( $user instanceof \WP_User ) {
			update_user_meta( $user->ID, self::META_ACTIVE, time() );
		}
	}

	/**
	 * Unlock by hand (the Administrator).
	 *
	 * @param string $username Username.
	 */
	public static function unlock( string $username ): void {
		delete_transient( self::key( $username ) );
	}

	/**
	 * Every sign-in failure carries the same message and code, so nothing reveals whether an account exists.
	 *
	 * @param mixed $user Result.
	 * @return mixed
	 */
	public static function make_errors_generic( $user ) {
		if ( $user instanceof \WP_Error && ! in_array( 'wiw_login_failed', $user->get_error_codes(), true ) ) {
			$codes = $user->get_error_codes();
			$known = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed' );
			if ( array() !== array_intersect( $codes, $known ) ) {
				return new \WP_Error( 'wiw_login_failed', self::generic_message() );
			}
		}
		return $user;
	}

	/**
	 * The one message.
	 */
	public static function generic_message(): string {
		return __( 'Sign in failed. Check your details and try again.', 'whoiswho' );
	}

	/**
	 * Problems with a password, in plain words. Empty when the password is acceptable.
	 *
	 * @param string $password Password.
	 * @return string[]
	 */
	public static function password_problems( string $password ): array {
		$problems = array();
		if ( strlen( $password ) < self::MIN_LENGTH ) {
			$problems[] = sprintf( 'Use at least %d characters.', self::MIN_LENGTH );
		}
		$classes = (int) (bool) preg_match( '/[a-z]/', $password )
			+ (int) (bool) preg_match( '/[A-Z]/', $password )
			+ (int) (bool) preg_match( '/[0-9]/', $password )
			+ (int) (bool) preg_match( '/[^a-zA-Z0-9]/', $password );
		if ( $classes < 3 ) {
			$problems[] = 'Mix at least three of: lower case, upper case, digits and symbols.';
		}
		if ( preg_match( '/^(.)\1+$/', $password ) || preg_match( '/(password|qwerty|123456|letmein|whoiswho)/i', $password ) ) {
			$problems[] = 'Avoid common words and patterns.';
		}
		return $problems;
	}

	/**
	 * Profile screen: refuse a weak new password.
	 *
	 * @param \WP_Error $errors Errors.
	 * @param bool      $update Whether this is an update.
	 * @param \stdClass $user   User being saved.
	 */
	public static function check_profile_password( $errors, $update, $user ): void {
		$password = isset( $user->user_pass ) ? (string) $user->user_pass : '';
		if ( '' === $password ) {
			return;
		}
		foreach ( self::password_problems( $password ) as $problem ) {
			$errors->add( 'wiw_weak_password', esc_html( $problem ) );
		}
	}

	/**
	 * Password reset screen: refuse a weak new password.
	 *
	 * @param \WP_Error $errors Errors.
	 * @param mixed     $user   User.
	 */
	public static function check_reset_password( $errors, $user ): void {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- hook signature.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress verifies the reset key before this hook; this only validates the new password.
		$password = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		if ( '' === $password ) {
			return;
		}
		foreach ( self::password_problems( $password ) as $problem ) {
			$errors->add( 'wiw_weak_password', esc_html( $problem ) );
		}
	}

	/**
	 * Whether a session has been idle too long.
	 *
	 * @param int $last  Last activity time.
	 * @param int $now   Now.
	 * @param int $limit Idle limit in seconds.
	 */
	public static function is_idle( int $last, int $now, int $limit = self::IDLE_LIMIT ): bool {
		return $last > 0 && ( $now - $last ) > $limit;
	}

	/**
	 * In wp-admin, end a session that has been idle for an hour. Activity is noted at most once a minute.
	 */
	public static function enforce_idle_timeout(): void {
		if ( ! is_user_logged_in() || wp_doing_ajax() ) {
			return;
		}
		$user = get_current_user_id();
		$last = (int) get_user_meta( $user, self::META_ACTIVE, true );
		$now  = time();
		if ( self::is_idle( $last, $now ) ) {
			delete_user_meta( $user, self::META_ACTIVE );
			wp_logout();
			wp_safe_redirect( add_query_arg( 'wiw_idle', '1', wp_login_url() ) );
			exit;
		}
		if ( $now - $last > 60 ) {
			update_user_meta( $user, self::META_ACTIVE, $now );
		}
	}

	/**
	 * Sign the user out everywhere (used when their role or capabilities change).
	 *
	 * @param int $user_id User id.
	 */
	public static function revoke_sessions( $user_id ): void {
		$user_id = (int) $user_id;
		if ( $user_id > 0 ) {
			\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		}
	}

	/**
	 * Revoke sessions when the capabilities meta changes.
	 *
	 * @param int    $meta_id  Meta id.
	 * @param int    $user_id  User id.
	 * @param string $meta_key Meta key.
	 */
	public static function revoke_on_capability_change( $meta_id, $user_id, $meta_key ): void {
		global $wpdb;
		if ( $wpdb->get_blog_prefix() . 'capabilities' === $meta_key ) {
			self::revoke_sessions( (int) $user_id );
		}
	}
}
