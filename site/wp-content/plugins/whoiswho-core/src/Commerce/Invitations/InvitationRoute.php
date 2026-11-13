<?php
/**
 * The /invite/{token} route (REQ-013, REQ-233). GET shows the profile form with the draft saved against the link; POST with a
 * save button keeps the draft, and POST with a submit button validates and creates the profile once. An unknown, used, revoked or
 * expired link shows one "expired" page with a way to ask for a new link. Attempts are rate limited per address and per link. The
 * page is a bare form until the theme supplies its own through the wiw_invitation_page filter. The page is never indexed and no
 * personal data is put in the address.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce\Invitations;

use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Forms\TransientStore;
use WhoIsWho\Core\Platform\Links\SignedLinks;

/**
 * Invitation route.
 */
final class InvitationRoute {

	public const QUERY_VAR = 'wiw_invite';

	/**
	 * Limits: attempts per IP and per link per hour.
	 */
	public const LIMITS = array(
		'invite' => array(
			'ip'      => array( 30, 3600 ),
			'subject' => array( 20, 3600 ),
		),
	);

	/**
	 * Invitations.
	 *
	 * @var Invitations
	 */
	private Invitations $invitations;

	/**
	 * Rate limiter.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $limiter;

	/**
	 * Constructor.
	 *
	 * @param Invitations $invitations Invitations.
	 * @param RateLimiter $limiter     Rate limiter.
	 */
	public function __construct( Invitations $invitations, RateLimiter $limiter ) {
		$this->invitations = $invitations;
		$this->limiter     = $limiter;
	}

	/**
	 * Attach hooks.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'add_rewrite' ) );
		add_filter(
			'query_vars',
			static function ( array $vars ): array {
				$vars[] = self::QUERY_VAR;
				return $vars;
			}
		);
		add_action( 'template_redirect', array( self::class, 'serve' ) );
	}

	/**
	 * Add the rewrite rule.
	 */
	public static function add_rewrite(): void {
		add_rewrite_rule( '^invite/([A-Za-z0-9_-]{20,64})/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Answer an invitation request.
	 */
	public static function serve(): void {
		$token = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $token ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );
		$route  = new self( Invitations::for_site(), new RateLimiter( new TransientStore(), wp_salt( 'auth' ), self::LIMITS + RateLimiter::LIMITS ) );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$state  = $route->respond( $method, $token, Request::from_globals(), $ip );
		status_header( 'limited' === $state['state'] ? 429 : 200 );
		echo (string) apply_filters( 'wiw_invitation_page', self::default_page( $state, $token ), $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- default markup is escaped below; the theme escapes its own.
		exit;
	}

	/**
	 * Decide the outcome (no output, so it can be tested).
	 *
	 * @param string  $method  GET or POST.
	 * @param string  $token   Token.
	 * @param Request $request Request, for the values and the picture.
	 * @param string  $ip      Visitor address.
	 * @return array{state: string, errors: array<string, string>, values: array<string, string>} state is form, saved, invalid, done, expired or limited.
	 */
	public function respond( string $method, string $token, Request $request, string $ip ): array {
		$none = array();
		if ( ! $this->limiter->allow( 'invite', $ip, SignedLinks::hash( $token ) ) ) {
			return array(
				'state'  => 'limited',
				'errors' => $none,
				'values' => $none,
			);
		}
		if ( ! $this->invitations->usable( $token ) ) {
			return array(
				'state'  => 'expired',
				'errors' => $none,
				'values' => $none,
			);
		}
		if ( 'POST' !== $method ) {
			return array(
				'state'  => 'form',
				'errors' => $none,
				'values' => $this->invitations->draft( $token ),
			);
		}
		$values = array();
		foreach ( array( 'name', 'type', 'summary', 'website' ) as $field ) {
			$values[ $field ] = 'summary' === $field ? $request->text( $field ) : $request->string( $field );
		}
		if ( 'draft' === $request->string( 'submit' ) ) {
			$this->invitations->save_draft( $token, $values );
			return array(
				'state'  => 'saved',
				'errors' => $none,
				'values' => $values,
			);
		}
		$result = $this->invitations->submit( $token, $values, $request->file( 'image' ) );
		return array(
			'state'  => $result['state'],
			'errors' => $result['errors'],
			'values' => 'invalid' === $result['state'] ? $values : $none,
		);
	}

	/**
	 * Bare default markup until the theme supplies the page.
	 *
	 * @param array{state: string, errors: array<string, string>, values: array<string, string>} $state State.
	 * @param string                                                                             $token Token (only in the form action).
	 */
	private static function default_page( array $state, string $token ): string {
		switch ( $state['state'] ) {
			case 'expired':
				return '<main><h1>' . esc_html__( 'Link expired or already used', 'whoiswho' ) . '</h1><p>' . esc_html__( 'This invitation link has expired or has already been used.', 'whoiswho' ) . '</p><p><a href="' . esc_url( home_url( '/contact/' ) ) . '">' . esc_html__( 'Request a new link', 'whoiswho' ) . '</a></p></main>';
			case 'limited':
				return '<main><p>' . esc_html__( 'Too many attempts. Please try again later.', 'whoiswho' ) . '</p></main>';
			case 'done':
				return '<main><h1>' . esc_html__( 'Thank you', 'whoiswho' ) . '</h1><p>' . esc_html__( 'Your profile has been sent for review. Nothing is published until a reviewer approves it.', 'whoiswho' ) . '</p></main>';
		}
		$values = $state['values'];
		$value  = static fn ( string $key ): string => $values[ $key ] ?? '';
		$out    = '<main><h1>' . esc_html__( 'Your profile', 'whoiswho' ) . '</h1>';
		if ( 'saved' === $state['state'] ) {
			$out .= '<p>' . esc_html__( 'Your draft is saved. You can come back to this link until it expires.', 'whoiswho' ) . '</p>';
		}
		if ( array() !== $state['errors'] ) {
			$out .= '<p role="alert">' . esc_html__( 'Please check the highlighted fields: ', 'whoiswho' ) . esc_html( implode( ', ', array_keys( $state['errors'] ) ) ) . '</p>';
		}
		$out .= '<form method="post" enctype="multipart/form-data" action="' . esc_url( home_url( '/invite/' . $token ) ) . '">';
		$out .= '<p><label>' . esc_html__( 'Name', 'whoiswho' ) . ' <input name="name" required value="' . esc_attr( $value( 'name' ) ) . '" /></label></p>';
		$out .= '<p><label>' . esc_html__( 'Type', 'whoiswho' ) . ' <select name="type">';
		foreach ( InvitationValidator::TYPES as $type ) {
			$out .= '<option value="' . esc_attr( $type ) . '"' . selected( $value( 'type' ), $type, false ) . '>' . esc_html( $type ) . '</option>';
		}
		$out .= '</select></label></p>';
		$out .= '<p><label>' . esc_html__( 'Short description', 'whoiswho' ) . ' <textarea name="summary" rows="5" maxlength="1500">' . esc_textarea( $value( 'summary' ) ) . '</textarea></label></p>';
		$out .= '<p><label>' . esc_html__( 'Website', 'whoiswho' ) . ' <input name="website" type="url" value="' . esc_attr( $value( 'website' ) ) . '" /></label></p>';
		$out .= '<p><label>' . esc_html__( 'Picture (JPEG, PNG or WebP, up to 2 MB)', 'whoiswho' ) . ' <input name="image" type="file" accept="image/jpeg,image/png,image/webp" /></label></p>';
		return $out . '<p><button name="submit" value="draft">' . esc_html__( 'Save draft', 'whoiswho' ) . '</button> <button name="submit" value="send">' . esc_html__( 'Send for review', 'whoiswho' ) . '</button></p></form></main>';
	}
}
