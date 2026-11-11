<?php
/**
 * The one way to handle an admin-post or AJAX action. Every action needs a named capability and a valid nonce; a request that
 * fails either check is refused and the handler never runs (deny by default). Handlers receive a Request, not the superglobals.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Admin;

/**
 * Action guard.
 */
final class ActionGuard {

	public const REFUSED_CAPABILITY = 'capability';
	public const REFUSED_NONCE      = 'nonce';

	/**
	 * Register an action handler.
	 *
	 * @param string   $action     Action name (used in admin-post.php?action= and the nonce).
	 * @param string   $capability A named capability. Required.
	 * @param callable $handler    Receives a Request.
	 * @param bool     $ajax       Also register for AJAX.
	 * @throws \InvalidArgumentException When the capability or action is empty.
	 */
	public static function register( string $action, string $capability, callable $handler, bool $ajax = false ): void {
		if ( '' === trim( $action ) || '' === trim( $capability ) ) {
			throw new \InvalidArgumentException( 'An action needs a name and a capability.' );
		}
		$run = static function () use ( $action, $capability, $handler ): void {
			$request = Request::from_globals();
			$refusal = self::check( $action, $capability, $request );
			if ( null !== $refusal ) {
				wp_die( esc_html__( 'You are not allowed to do that.', 'whoiswho' ), '', array( 'response' => 403 ) );
			}
			$handler( $request );
		};
		add_action( 'admin_post_' . $action, $run );
		if ( $ajax ) {
			add_action( 'wp_ajax_' . $action, $run );
		}
	}

	/**
	 * The nonce a form or link for this action must carry.
	 *
	 * @param string $action Action name.
	 */
	public static function nonce_for( string $action ): string {
		return wp_create_nonce( 'wiw_action_' . $action );
	}

	/**
	 * Check a request without running anything.
	 *
	 * @param string  $action     Action name.
	 * @param string  $capability Required capability.
	 * @param Request $request    The request.
	 * @return string|null Null when allowed, otherwise why it was refused.
	 */
	public static function check( string $action, string $capability, Request $request ): ?string {
		if ( ! current_user_can( $capability ) ) {
			return self::REFUSED_CAPABILITY;
		}
		if ( false === wp_verify_nonce( $request->nonce(), 'wiw_action_' . $action ) ) {
			return self::REFUSED_NONCE;
		}
		return null;
	}
}
