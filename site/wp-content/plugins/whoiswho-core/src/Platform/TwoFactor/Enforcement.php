<?php
/**
 * Two-factor enforcement (REQ-047). Every signed-in user, whatever role (including roles created later), must set up a second
 * factor before reaching any wp-admin screen or the REST API. Until then the only screens open are the user's own profile (where
 * the Two Factor plugin offers an authenticator app, email codes and recovery codes) and the sign-out link. The check is by user,
 * never by role, so a new custom role is enforced with no change. When the Two Factor software is missing the answer is no entry.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\TwoFactor;

use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Enforcement.
 */
final class Enforcement {

	public const ALLOW  = 'allow';
	public const ENROL  = 'enrol';
	public const NOTICE = 'wiw_2fa_required';

	/**
	 * Admin pages open to a user who has not enrolled yet.
	 */
	private const OPEN_PAGES = array( 'profile.php', 'user-edit.php' );

	/**
	 * Status source.
	 *
	 * @var SecondFactorStatus
	 */
	private SecondFactorStatus $status;

	/**
	 * Constructor.
	 *
	 * @param SecondFactorStatus $status Status source.
	 */
	public function __construct( SecondFactorStatus $status ) {
		$this->status = $status;
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		return new self( new PluginStatus() );
	}

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'guard_admin' ), 1 );
		add_filter( 'rest_authentication_errors', array( $this, 'guard_rest' ), 100 );
		add_action( 'admin_notices', array( $this, 'show_notice' ) );
	}

	/**
	 * Decide what a signed-in user may reach.
	 *
	 * @param int    $user_id        Signed-in user id (0 when signed out).
	 * @param string $page           Admin page file, for example profile.php; empty for REST.
	 * @param int    $edited_user_id User being edited on that page (0 when none).
	 * @param string $rest_route     REST route, empty outside REST.
	 * @return string allow or enrol.
	 */
	public function decide( int $user_id, string $page = '', int $edited_user_id = 0, string $rest_route = '' ): string {
		if ( $user_id <= 0 || TestMode::exempts( $user_id ) ) {
			return self::ALLOW;
		}
		if ( $this->status->available() && $this->status->is_enrolled( $user_id ) ) {
			return self::ALLOW;
		}
		if ( ! $this->status->available() ) {
			return self::ENROL;
		}
		if ( '' !== $rest_route ) {
			return 0 === strpos( ltrim( $rest_route, '/' ), 'two-factor/' ) ? self::ALLOW : self::ENROL;
		}
		if ( in_array( $page, self::OPEN_PAGES, true ) && ( 'profile.php' === $page || $edited_user_id === $user_id || 0 === $edited_user_id ) ) {
			return self::ALLOW;
		}
		return self::ENROL;
	}

	/**
	 * Send an un-enrolled user to their profile, or stop an ajax or post request.
	 */
	public function guard_admin(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		global $pagenow;
		$page   = is_string( $pagenow ) ? $pagenow : '';
		$edited = Request::from_globals()->int( 'user_id' );
		if ( self::ALLOW === $this->decide( get_current_user_id(), $page, $edited ) ) {
			return;
		}
		if ( wp_doing_ajax() || 'admin-post.php' === $page || 'async-upload.php' === $page ) {
			wp_die( esc_html( $this->message() ), '', array( 'response' => 403 ) );
		}
		wp_safe_redirect( add_query_arg( self::NOTICE, '1', admin_url( 'profile.php' ) ) . '#two-factor-options' );
		exit;
	}

	/**
	 * Refuse REST for an un-enrolled user, except the plugin's own enrolment routes.
	 *
	 * @param mixed $result Result so far.
	 * @return mixed
	 */
	public function guard_rest( $result ) {
		if ( ! is_user_logged_in() ) {
			return $result;
		}
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		if ( '' === $route ) {
			$route = '/';
		}
		if ( self::ALLOW === $this->decide( get_current_user_id(), '', 0, $route ) ) {
			return $result;
		}
		return new \WP_Error( 'wiw_two_factor_required', $this->message(), array( 'status' => 403 ) );
	}

	/**
	 * Explain on the profile screen why the user was sent there.
	 */
	public function show_notice(): void {
		if ( ! Request::from_globals()->has( self::NOTICE ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html( $this->message() ) . '</p></div>';
	}

	/**
	 * The one message.
	 */
	public function message(): string {
		return __( 'Set up two-factor sign-in to continue: choose an authenticator app or email codes below, and save your recovery codes.', 'whoiswho' );
	}
}
