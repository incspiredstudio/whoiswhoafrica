<?php
/**
 * GET /confirm/{token} (REQ-249, REQ-061): confirms a pending newsletter sign-up from the link in the confirmation email. The token
 * works once and for CONFIRM_DAYS days; an unknown, used, withdrawn or expired token shows the same "expired or already used"
 * state. The page markup comes from the theme through the wiw_newsletter_confirm_page filter; a bare default is used until then.
 * Never indexed or cached.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Newsletter;

/**
 * Newsletter confirmation route.
 */
final class ConfirmRoute {

	public const QUERY_VAR = 'wiw_nl_confirm';

	public const CONFIRMED = 'confirmed';
	public const EXPIRED   = 'expired';

	/**
	 * Subscribers.
	 *
	 * @var SubscriberRepository
	 */
	private SubscriberRepository $subscribers;

	/**
	 * Constructor.
	 *
	 * @param SubscriberRepository|null $subscribers Subscriber storage.
	 */
	public function __construct( ?SubscriberRepository $subscribers = null ) {
		$this->subscribers = $subscribers ?? new SubscriberRepository();
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
		add_rewrite_rule( '^confirm/([A-Za-z0-9_-]{20,64})/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Answer a confirmation request.
	 */
	public function serve(): void {
		$token = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $token ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );
		$state = $this->respond( $token );
		status_header( self::CONFIRMED === $state['state'] ? 200 : 410 );
		echo (string) apply_filters( 'wiw_newsletter_confirm_page', self::default_page( $state ), $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- default markup is escaped below; the theme escapes its own.
		exit;
	}

	/**
	 * Decide the outcome (no output, so it can be tested).
	 *
	 * @param string $token Token from the link.
	 * @return array{state: string, message: string}
	 */
	public function respond( string $token ): array {
		if ( $this->subscribers->confirm( $token ) ) {
			return array(
				'state'   => self::CONFIRMED,
				'message' => 'You are subscribed. Every email has an unsubscribe link.',
			);
		}
		return array(
			'state'   => self::EXPIRED,
			'message' => 'Link expired or already used.',
		);
	}

	/**
	 * Bare default markup.
	 *
	 * @param array{state: string, message: string} $state Outcome.
	 */
	private static function default_page( array $state ): string {
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>' . esc_html( $state['message'] ) . '</title></head><body><main><p>' . esc_html( $state['message'] ) . '</p></main></body></html>';
	}
}
