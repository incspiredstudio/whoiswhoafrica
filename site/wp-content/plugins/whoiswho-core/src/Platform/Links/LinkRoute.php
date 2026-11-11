<?php
/**
 * The /link/{token} route. GET shows a confirmation page and consumes nothing. POST consumes the link and runs its purpose
 * handler. Unknown, used, revoked and expired links all show the same "expired or already used" page. Attempts are rate limited
 * per IP and per token. The page markup comes from the theme through the wiw_link_page filter (M0-THM-13); a bare default is used
 * until then.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Links;

use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Forms\TransientStore;

/**
 * Link route.
 */
final class LinkRoute {

	public const QUERY_VAR = 'wiw_link';

	/**
	 * Limits: attempts per IP and per token per hour.
	 */
	public const LIMITS = array(
		'link' => array(
			'ip'      => array( 30, 3600 ),
			'subject' => array( 10, 3600 ),
		),
	);

	/**
	 * Service.
	 *
	 * @var SignedLinks
	 */
	private SignedLinks $links;

	/**
	 * Rate limiter.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $limiter;

	/**
	 * Constructor.
	 *
	 * @param SignedLinks $links   Service.
	 * @param RateLimiter $limiter Rate limiter.
	 */
	public function __construct( SignedLinks $links, RateLimiter $limiter ) {
		$this->links   = $links;
		$this->limiter = $limiter;
	}

	/**
	 * Route for this site.
	 */
	public static function for_site(): self {
		return new self( SignedLinks::for_site(), new RateLimiter( new TransientStore(), wp_salt( 'auth' ), self::LIMITS + RateLimiter::LIMITS ) );
	}

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter(
			'query_vars',
			static function ( array $vars ): array {
				$vars[] = self::QUERY_VAR;
				return $vars;
			}
		);
		add_action( 'template_redirect', array( $this, 'serve' ) );
	}

	/**
	 * Add the rewrite rule.
	 */
	public function add_rewrite(): void {
		add_rewrite_rule( '^link/([A-Za-z0-9_-]{20,64})/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Answer a link request.
	 */
	public function serve(): void {
		$token = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $token ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$state  = $this->respond( $method, $token, $ip );

		status_header( 'limited' === $state['state'] ? 429 : 200 );
		echo (string) apply_filters( 'wiw_link_page', self::default_page( $state['state'], $state['message'], $token ), $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- default markup is escaped above; the theme escapes its own.
		exit;
	}

	/**
	 * Decide the outcome (no output, so it can be tested).
	 *
	 * @param string $method GET or POST.
	 * @param string $token  Token.
	 * @param string $ip     Visitor IP.
	 * @return array{state: string, message: string} state is confirm, done, expired or limited.
	 */
	public function respond( string $method, string $token, string $ip ): array {
		if ( ! $this->limiter->allow( 'link', $ip, SignedLinks::hash( $token ) ) ) {
			return array(
				'state'   => 'limited',
				'message' => 'Too many attempts. Please try again later.',
			);
		}
		if ( 'POST' === $method ) {
			$message = $this->links->use_link( $token );
			return null === $message ? self::expired() : array(
				'state'   => 'done',
				'message' => $message,
			);
		}
		return null === $this->links->peek( $token ) ? self::expired() : array(
			'state'   => 'confirm',
			'message' => '',
		);
	}

	/**
	 * The one answer for every unusable link.
	 *
	 * @return array{state: string, message: string}
	 */
	private static function expired(): array {
		return array(
			'state'   => 'expired',
			'message' => 'This link has expired or has already been used.',
		);
	}

	/**
	 * Bare default markup until the theme supplies the page.
	 *
	 * @param string $state   State.
	 * @param string $message Message.
	 * @param string $token   Token (only used in the form action, never echoed as text).
	 */
	private static function default_page( string $state, string $message, string $token ): string {
		if ( 'confirm' === $state ) {
			return '<main><form method="post" action="' . esc_url( home_url( '/link/' . $token ) ) . '"><p>' . esc_html__( 'Please confirm to continue.', 'whoiswho' ) . '</p><button type="submit">' . esc_html__( 'Confirm', 'whoiswho' ) . '</button></form></main>';
		}
		return '<main><p>' . esc_html( $message ) . '</p></main>';
	}
}
