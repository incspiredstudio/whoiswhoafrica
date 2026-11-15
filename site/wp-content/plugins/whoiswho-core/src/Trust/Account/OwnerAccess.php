<?php
/**
 * Keeps a Profile owner out of wp-admin (REQ-274): any wp-admin request from a user whose only role is the Profile owner role is
 * sent to /account/, the admin bar is never shown to them, and after a sign-in (including the two-factor step on wp-login.php) they
 * land on /account/. admin-ajax.php is left to its own handlers, which check their own capabilities; the account pages post to
 * themselves and use neither admin-ajax.php nor admin-post.php. The decisions are pure; the hooks are thin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust\Account;

/**
 * Owner access rules.
 */
final class OwnerAccess {

	/**
	 * Attach the hooks.
	 */
	public static function hooks(): void {
		// auth_redirect runs first on every wp-admin screen, before the menu's own "not allowed" stop (which comes before admin_init);
		// admin_init and admin_page_access_denied are kept as further nets.
		add_action( 'auth_redirect', array( self::class, 'keep_user_out_of_admin' ), 0 );
		add_action( 'admin_page_access_denied', array( self::class, 'keep_out_of_admin' ), 0 );
		add_action( 'admin_init', array( self::class, 'keep_out_of_admin' ), 0 );
		add_filter( 'show_admin_bar', array( self::class, 'admin_bar' ), 99 );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 99, 3 );
	}

	/**
	 * Where a wp-admin request should go instead, or an empty string to let it through.
	 *
	 * @param int  $user_id User.
	 * @param bool $ajax    Whether this is an admin-ajax.php request.
	 */
	public static function admin_redirect( int $user_id, bool $ajax ): string {
		return ! $ajax && OwnerRole::is_owner_only( $user_id ) ? OwnerRole::url() : '';
	}

	/**
	 * Send an owner from wp-admin to the account pages.
	 */
	public static function keep_out_of_admin(): void {
		$to = self::admin_redirect( get_current_user_id(), wp_doing_ajax() );
		if ( '' !== $to ) {
			wp_safe_redirect( $to );
			exit;
		}
	}

	/**
	 * The wp-admin cookie check (auth_redirect) hands over the signed-in user's id before the current user is set.
	 *
	 * @param mixed $user_id User whose admin cookie was accepted.
	 */
	public static function keep_user_out_of_admin( $user_id ): void {
		$to = self::admin_redirect( (int) $user_id, wp_doing_ajax() );
		if ( '' !== $to ) {
			wp_safe_redirect( $to );
			exit;
		}
	}

	/**
	 * No admin bar for an owner.
	 *
	 * @param mixed $show Whether to show it.
	 */
	public static function admin_bar( $show ): bool {
		return OwnerRole::is_owner_only( get_current_user_id() ) ? false : (bool) $show;
	}

	/**
	 * After a sign-in an owner always lands on /account/.
	 *
	 * @param mixed $redirect_to Requested address.
	 * @param mixed $requested   Address asked for in the request.
	 * @param mixed $user        User or error.
	 */
	public static function login_redirect( $redirect_to, $requested, $user ): string {
		unset( $requested );
		if ( $user instanceof \WP_User && OwnerRole::is_owner_only( (int) $user->ID ) ) {
			return OwnerRole::url();
		}
		return (string) $redirect_to;
	}
}
