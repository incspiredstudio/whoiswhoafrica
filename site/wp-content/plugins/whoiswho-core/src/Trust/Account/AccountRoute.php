<?php
/**
 * The public account pages of a Profile owner (REQ-274, REQ-261, REQ-253), like a shop's "My account":
 *   /account/                      sign-in form when signed out, the dashboard (My profiles) when signed in
 *   /account/profiles/{id}/edit/   propose a change to one owned profile (it waits for review)
 *   /account/details/              name and password
 *   /account/lost-password/        ask for a reset email; with ?key=&id= choose a new password (core reset keys)
 *   /account/sign-out/             sign out (nonce in the link)
 * Every POST carries a nonce for its page; the sign-in and lost-password forms also pass the bot challenge the other public forms
 * use (Turnstile when the site has keys) and a rate limit per address and per name typed. A profile id the owner does not own
 * answers 404, the same as one that does not exist. redirect_to is honoured only for an address under /account/ on this site. The
 * pages are never indexed or cached. The plugin decides the state; the theme draws it through the wiw_account_page filter, and a
 * bare default page is printed when no theme supplies one.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust\Account;

use WhoIsWho\Core\Compliance\Forms\TurnstileSettings;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\SideFields;
use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Forms\BotChallenge;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Forms\TransientStore;
use WhoIsWho\Core\Trust\MyProfiles;
use WhoIsWho\Core\Trust\OwnerEdits;

/**
 * Account route.
 */
final class AccountRoute {

	public const QUERY_VAR   = 'wiw_account';
	public const ID_VAR      = 'wiw_account_id';
	public const NONCE_FIELD = 'wiw_account_nonce';
	public const VIEWS       = array( 'home', 'edit', 'details', 'lost', 'signout' );
	public const RULES_OPT   = 'wiw_account_rules_1';

	/**
	 * Limits: sign-in and lost-password attempts per address and per name typed.
	 */
	public const LIMITS = array(
		'account_signin' => array(
			'ip'      => array( 30, 900 ),
			'subject' => array( 10, 900 ),
		),
		'account_lost'   => array(
			'ip'      => array( 10, 3600 ),
			'subject' => array( 3, 3600 ),
		),
	);

	/**
	 * Account service.
	 *
	 * @var AccountService
	 */
	private AccountService $service;

	/**
	 * Owner edits.
	 *
	 * @var OwnerEdits
	 */
	private OwnerEdits $edits;

	/**
	 * Rate limiter.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $limiter;

	/**
	 * Bot challenge.
	 *
	 * @var BotChallenge
	 */
	private BotChallenge $challenge;

	/**
	 * Constructor.
	 *
	 * @param AccountService $service   Account service.
	 * @param OwnerEdits     $edits     Owner edits.
	 * @param RateLimiter    $limiter   Rate limiter.
	 * @param BotChallenge   $challenge Bot challenge.
	 */
	public function __construct( AccountService $service, OwnerEdits $edits, RateLimiter $limiter, BotChallenge $challenge ) {
		$this->service   = $service;
		$this->edits     = $edits;
		$this->limiter   = $limiter;
		$this->challenge = $challenge;
	}

	/**
	 * The route for this site.
	 */
	public static function for_site(): self {
		return new self(
			new AccountService(),
			new OwnerEdits(),
			new RateLimiter( new TransientStore(), wp_salt( 'auth' ), self::LIMITS + RateLimiter::LIMITS ),
			TurnstileSettings::challenge( FormToken::for_site() )
		);
	}

	/**
	 * Attach hooks.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'add_rewrite' ) );
		add_filter(
			'query_vars',
			static function ( array $vars ): array {
				return array_merge( $vars, array( self::QUERY_VAR, self::ID_VAR ) );
			}
		);
		add_action( 'template_redirect', array( self::class, 'serve' ), 1 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
	}

	/**
	 * The rewrite rules.
	 */
	public static function add_rewrite(): void {
		$base = 'index.php?' . self::QUERY_VAR . '=';
		add_rewrite_rule( '^account/?$', $base . 'home', 'top' );
		add_rewrite_rule( '^account/profiles/([0-9]+)/edit/?$', $base . 'edit&' . self::ID_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( '^account/details/?$', $base . 'details', 'top' );
		add_rewrite_rule( '^account/lost-password/?$', $base . 'lost', 'top' );
		add_rewrite_rule( '^account/sign-out/?$', $base . 'signout', 'top' );
		if ( '1' !== get_option( self::RULES_OPT ) ) {
			flush_rewrite_rules( false );
			update_option( self::RULES_OPT, '1', false );
		}
	}

	/**
	 * The account view of this request, or an empty string.
	 */
	public static function current(): string {
		$view = (string) get_query_var( self::QUERY_VAR, '' );
		return in_array( $view, self::VIEWS, true ) ? $view : '';
	}

	/**
	 * The account pages are never indexed.
	 *
	 * @param mixed $robots Robots directives.
	 * @return array<string, mixed>
	 */
	public static function robots( $robots ): array {
		$robots = is_array( $robots ) ? $robots : array();
		if ( '' !== self::current() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * Answer an account request.
	 */
	public static function serve(): void {
		$view = self::current();
		if ( '' === $view ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$state  = self::for_site()->respond( $view, (int) get_query_var( self::ID_VAR, 0 ), $method, Request::from_globals(), FormInput::from_globals(), $ip, get_current_user_id() );
		if ( '' !== $state['redirect'] ) {
			wp_safe_redirect( $state['redirect'] );
			exit;
		}
		global $wp_query;
		$wp_query->is_404 = 404 === $state['status'];
		status_header( $state['status'] );
		echo (string) apply_filters( 'wiw_account_page', self::default_page( $state ), $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the default markup is escaped below; the theme escapes its own.
		exit;
	}

	/**
	 * Decide the outcome of one request (no output, so it can be tested).
	 *
	 * @param string    $view       home, edit, details, lost or signout.
	 * @param int       $profile_id Profile id from the address (edit).
	 * @param string    $method     GET or POST.
	 * @param Request   $request    Request values and files.
	 * @param FormInput $input      The same body, for the bot challenge.
	 * @param string    $ip         Visitor address.
	 * @param int       $user_id    Signed-in user, or 0.
	 * @return array{view: string, status: int, redirect: string, errors: string[], notice: string, data: array<string, mixed>}
	 */
	public function respond( string $view, int $profile_id, string $method, Request $request, FormInput $input, string $ip, int $user_id ): array {
		$post = 'POST' === strtoupper( $method );
		switch ( $view ) {
			case 'lost':
				return $this->lost( $post, $request, $input, $ip, $user_id );
			case 'signout':
				if ( $user_id > 0 && false !== wp_verify_nonce( $request->nonce(), 'wiw_account_signout' ) ) {
					wp_logout();
					return self::go( add_query_arg( 'signed_out', '1', OwnerRole::url() ) );
				}
				return self::go( OwnerRole::url() );
		}
		if ( $user_id < 1 ) {
			if ( 'home' !== $view ) {
				return self::go( OwnerRole::url() );
			}
			return $post ? $this->sign_in( $request, $input, $ip ) : $this->signin_page( $request, array() );
		}
		if ( ! OwnerRole::is_owner( $user_id ) ) {
			// Staff have no account pages: their place is wp-admin.
			return self::go( admin_url() );
		}
		if ( 'edit' === $view ) {
			return $this->edit( $profile_id, $post, $request, $user_id );
		}
		if ( 'details' === $view ) {
			return $this->details( $post, $request, $input, $user_id );
		}
		return $this->dashboard( $request, $user_id );
	}

	/**
	 * A redirect answer.
	 *
	 * @param string $url Address.
	 * @return array{view: string, status: int, redirect: string, errors: string[], notice: string, data: array<string, mixed>}
	 */
	private static function go( string $url ): array {
		return self::page( 'redirect', 302, array(), '', array(), $url );
	}

	/**
	 * A page answer.
	 *
	 * @param string               $view     View.
	 * @param int                  $status   HTTP status.
	 * @param string[]             $errors   Messages.
	 * @param string               $notice   Notice.
	 * @param array<string, mixed> $data     View data.
	 * @param string               $redirect Redirect address.
	 * @return array{view: string, status: int, redirect: string, errors: string[], notice: string, data: array<string, mixed>}
	 */
	private static function page( string $view, int $status, array $errors = array(), string $notice = '', array $data = array(), string $redirect = '' ): array {
		return array(
			'view'     => $view,
			'status'   => $status,
			'redirect' => $redirect,
			'errors'   => array_values( $errors ),
			'notice'   => $notice,
			'data'     => $data,
		);
	}

	/**
	 * Only an address under /account/ on this site is a place to go after signing in.
	 *
	 * @param string $requested Requested address.
	 */
	public static function safe_redirect( string $requested ): string {
		$base = OwnerRole::url();
		if ( '' === $requested || 0 !== strpos( $requested, $base ) || false !== strpos( $requested, '..' ) || false !== strpos( $requested, '\\' ) || 1 === preg_match( '/[\x00-\x1f]/', $requested ) ) {
			return $base;
		}
		return $requested;
	}

	/**
	 * Whether the POST carries a valid nonce for a page.
	 *
	 * @param Request $request Request.
	 * @param string  $page    Page name.
	 */
	private static function nonce_ok( Request $request, string $page ): bool {
		return false !== wp_verify_nonce( $request->string( self::NONCE_FIELD ), 'wiw_account_' . $page );
	}

	/**
	 * The raw value of a password field (never sanitised, never refilled).
	 *
	 * @param FormInput $input Body.
	 * @param string    $key   Field.
	 */
	private static function secret( FormInput $input, string $key ): string {
		return $input->raw( $key );
	}

	/**
	 * The sign-in page.
	 *
	 * @param Request  $request Request.
	 * @param string[] $errors  Messages.
	 * @param int      $status  HTTP status.
	 * @return array{view: string, status: int, redirect: string, errors: string[], notice: string, data: array<string, mixed>}
	 */
	private function signin_page( Request $request, array $errors, int $status = 200 ): array {
		$notice = '';
		if ( '1' === $request->string( 'signed_out' ) ) {
			$notice = __( 'You are signed out.', 'whoiswho' );
		} elseif ( '1' === $request->string( 'reset' ) ) {
			$notice = __( 'Your password is set. Sign in with it now.', 'whoiswho' );
		}
		return self::page(
			'signin',
			$status,
			$errors,
			$notice,
			array(
				'login'       => array() === $errors ? '' : $request->string( 'log' ),
				'redirect_to' => self::safe_redirect( $request->string( 'redirect_to' ) ),
				'lost_url'    => OwnerRole::url( 'lost-password/' ),
			)
		);
	}

	/**
	 * Sign in.
	 *
	 * @param Request   $request Request.
	 * @param FormInput $input   Body.
	 * @param string    $ip      Address.
	 * @return array{view: string, status: int, redirect: string, errors: string[], notice: string, data: array<string, mixed>}
	 */
	private function sign_in( Request $request, FormInput $input, string $ip ): array {
		$login = trim( $request->string( 'log' ) );
		if ( ! $this->limiter->allow( 'account_signin', $ip, strtolower( $login ) ) ) {
			return $this->signin_page( $request, array( __( 'Too many attempts. Please wait a few minutes and try again.', 'whoiswho' ) ), 429 );
		}
		if ( ! self::nonce_ok( $request, 'signin' ) || BotChallenge::OK !== $this->challenge->verify( $input, 'account_signin' ) ) {
			return $this->signin_page( $request, array( __( 'This form has expired. Please reload the page and try again.', 'whoiswho' ) ), 400 );
		}
		try {
			$user_id = $this->service->sign_in( $login, self::secret( $input, 'pwd' ), '1' === $request->string( 'rememberme' ) );
		} catch ( \DomainException $e ) {
			return $this->signin_page( $request, array( __( 'Sign in failed. Check your details and try again.', 'whoiswho' ) ), 200 );
		}
		return self::go( OwnerRole::is_owner( $user_id ) ? self::safe_redirect( $request->string( 'redirect_to' ) ) : admin_url() );
	}

	/**
	 * The dashboard.
	 *
	 * @param Request $request Request.
	 * @param int     $user_id Owner.
	 * @return array{view: string, status: int, redirect: string, errors: string[], notice: string, data: array<string, mixed>}
	 */
	private function dashboard( Request $request, int $user_id ): array {
		unset( $request );
		$user     = get_userdata( $user_id );
		$profiles = array();
		foreach ( ( new MyProfiles() )->for_user( $user_id ) as $row ) {
			$profiles[] = array(
				'id'       => $row['id'],
				'name'     => $row['name'],
				'status'   => $row['status'],
				'pending'  => MyProfiles::PENDING === $row['status'],
				'note'     => $row['note'],
				'edit_url' => OwnerRole::url( 'profiles/' . $row['id'] . '/edit/' ),
				'view_url' => (string) get_permalink( $row['id'] ),
			);
		}
		return self::page(
			'dashboard',
			200,
			array(),
			'',
			array(
				'name'        => $user instanceof \WP_User ? (string) $user->display_name : '',
				'profiles'    => $profiles,
				'details_url' => OwnerRole::url( 'details/' ),
				'signout_url' => self::signout_url(),
			)
		);
	}

	/**
	 * The sign-out address, with its nonce.
	 */
	public static function signout_url(): string {
		return add_query_arg( '_wpnonce', wp_create_nonce( 'wiw_account_signout' ), OwnerRole::url( 'sign-out/' ) );
	}

	/**
	 * Edit one owned profile.
	 *
	 * @param int     $profile_id Profile.
	 * @param bool    $post       Whether this is a POST.
	 * @param Request $request    Request.
	 * @param int     $user_id    Owner.
	 * @return array{view: string, status: int, redirect: string, errors: string[], notice: string, data: array<string, mixed>}
	 */
	private function edit( int $profile_id, bool $post, Request $request, int $user_id ): array {
		if ( $profile_id < 1 || ! $this->edits->owns( $user_id, $profile_id ) ) {
			return self::page( 'not_found', 404, array(), '', array( 'home_url' => OwnerRole::url() ) );
		}
		$errors = array();
		if ( $post ) {
			if ( ! self::nonce_ok( $request, 'edit' ) ) {
				$errors[] = __( 'This form has expired. Please reload the page and try again.', 'whoiswho' );
			} else {
				try {
					$this->edits->submit( $profile_id, $request, $user_id );
					return self::go( add_query_arg( 'sent', '1', OwnerRole::url( 'profiles/' . $profile_id . '/edit/' ) ) );
				} catch ( EditRefused $e ) {
					$errors[] = $e->getMessage();
				}
			}
		}
		$profiles = new MyProfiles();
		$pending  = $profiles->pending( $profile_id, $user_id );
		$form     = $this->edits->form( $profile_id );
		$type     = (string) ( ( new StateRepository() )->get( $profile_id )['entity_type'] ?? '' );
		$allow    = new FieldAllowlist();
		$rules    = $allow->for_type( $type );
		$fields   = array();
		foreach ( $form['fields'] as $key => $value ) {
			$fields[ $key ] = array(
				'label' => $allow->label( $type, $key ),
				'value' => $post && $request->has( $key ) ? ( in_array( $key, OwnerEdits::LONG, true ) ? $request->text( $key ) : $request->string( $key ) ) : $value,
				'long'  => in_array( $key, OwnerEdits::LONG, true ),
				'max'   => (int) ( $rules[ $key ]['max'] ?? 0 ),
			);
		}
		$labels  = array(
			'email'   => __( 'Contact email', 'whoiswho' ),
			'phone'   => __( 'Contact phone', 'whoiswho' ),
			'address' => __( 'Contact address', 'whoiswho' ),
		);
		$contact = array();
		foreach ( SideFields::CONTACT as $key => $kind ) {
			$contact[ $key ] = array(
				'label' => $labels[ $kind ],
				'value' => $post && $request->has( $key ) ? $request->string( $key ) : $form['contact'][ $key ],
				'type'  => 'email' === $kind ? 'email' : ( 'phone' === $kind ? 'tel' : 'text' ),
			);
		}
		$notice = '1' === $request->string( 'sent' ) ? __( 'Your changes were sent for review. The live profile stays as it is until a reviewer approves them, and we will email you the outcome.', 'whoiswho' ) : '';
		return self::page(
			'edit',
			array() === $errors ? 200 : 422,
			$errors,
			$notice,
			array(
				'id'         => $profile_id,
				'name'       => get_the_title( $profile_id ),
				'view_url'   => (string) get_permalink( $profile_id ),
				'action'     => OwnerRole::url( 'profiles/' . $profile_id . '/edit/' ),
				'home_url'   => OwnerRole::url(),
				'pending'    => $pending,
				'note'       => $pending ? '' : $profiles->returned_note( $profile_id, $user_id ),
				'fields'     => $fields,
				'contact'    => $contact,
				'image_html' => $form['image'] > 0 ? (string) wp_get_attachment_image( $form['image'], 'thumbnail', false, array( 'alt' => '' ) ) : '',
			)
		);
	}

	/**
	 * Account details.
	 *
	 * @param bool      $post    Whether this is a POST.
	 * @param Request   $request Request.
	 * @param FormInput $input   Body (the password fields).
	 * @param int       $user_id Owner.
	 * @return array{view: string, status: int, redirect: string, errors: string[], notice: string, data: array<string, mixed>}
	 */
	private function details( bool $post, Request $request, FormInput $input, int $user_id ): array {
		$errors = array();
		if ( $post ) {
			if ( ! self::nonce_ok( $request, 'details' ) ) {
				$errors[] = __( 'This form has expired. Please reload the page and try again.', 'whoiswho' );
			} else {
				$result = $this->service->update_details( $user_id, $request->string( 'display_name' ), self::secret( $input, 'current_password' ), self::secret( $input, 'pass1' ), self::secret( $input, 'pass2' ) );
				if ( array() === $result['errors'] ) {
					return self::go( add_query_arg( 'saved', '1', OwnerRole::url( 'details/' ) ) );
				}
				$errors = $result['errors'];
			}
		}
		$user = get_userdata( $user_id );
		return self::page(
			'details',
			array() === $errors ? 200 : 422,
			$errors,
			'1' === $request->string( 'saved' ) ? __( 'Your details are saved.', 'whoiswho' ) : '',
			array(
				'name'     => $post ? $request->string( 'display_name' ) : ( $user instanceof \WP_User ? (string) $user->display_name : '' ),
				'action'   => OwnerRole::url( 'details/' ),
				'home_url' => OwnerRole::url(),
			)
		);
	}

	/**
	 * Lost password: ask for the email, or choose a new password from a link.
	 *
	 * @param bool      $post    Whether this is a POST.
	 * @param Request   $request Request.
	 * @param FormInput $input   Body.
	 * @param string    $ip      Address.
	 * @param int       $user_id Signed-in user, or 0.
	 * @return array{view: string, status: int, redirect: string, errors: string[], notice: string, data: array<string, mixed>}
	 */
	private function lost( bool $post, Request $request, FormInput $input, string $ip, int $user_id ): array {
		$key  = $request->string( 'key' );
		$id   = $request->int( 'id' );
		$data = array(
			'action'     => OwnerRole::url( 'lost-password/' ),
			'signin_url' => OwnerRole::url(),
			'key'        => $key,
			'id'         => $id,
		);
		if ( '' !== $key || $id > 0 ) {
			$user = $this->service->reset_user( $id, $key );
			if ( null === $user ) {
				return self::page( 'reset_expired', 200, array(), '', $data );
			}
			if ( ! $post ) {
				return self::page( 'reset', 200, array(), '', $data );
			}
			if ( ! self::nonce_ok( $request, 'reset' ) ) {
				return self::page( 'reset', 400, array( __( 'This form has expired. Please reload the page and try again.', 'whoiswho' ) ), '', $data );
			}
			$problems = $this->service->reset( $user, self::secret( $input, 'pass1' ), self::secret( $input, 'pass2' ) );
			if ( array() !== $problems ) {
				return self::page( 'reset', 422, $problems, '', $data );
			}
			if ( $user_id > 0 ) {
				wp_logout();
			}
			return self::go( add_query_arg( 'reset', '1', OwnerRole::url() ) );
		}
		if ( ! $post ) {
			return self::page( 'lost', 200, array(), '', $data );
		}
		$email = trim( $request->string( 'user_email' ) );
		if ( ! $this->limiter->allow( 'account_lost', $ip, strtolower( $email ) ) ) {
			return self::page( 'lost', 429, array( __( 'Too many attempts. Please wait a while and try again.', 'whoiswho' ) ), '', $data );
		}
		if ( ! self::nonce_ok( $request, 'lost' ) || BotChallenge::OK !== $this->challenge->verify( $input, 'account_lost' ) ) {
			return self::page( 'lost', 400, array( __( 'This form has expired. Please reload the page and try again.', 'whoiswho' ) ), '', $data );
		}
		$this->service->lost_password( $email );
		return self::page( 'lost_sent', 200, array(), __( 'If an account uses that address, we have sent it an email with a link to choose a new password. The link works once and expires after a day.', 'whoiswho' ), $data );
	}

	/**
	 * Bare default markup until the theme supplies the page. Every value is escaped.
	 *
	 * @param array{view: string, status: int, redirect: string, errors: string[], notice: string, data: array<string, mixed>} $state State.
	 */
	public static function default_page( array $state ): string {
		$out = '<main><h1>' . esc_html__( 'Your account', 'whoiswho' ) . '</h1>';
		foreach ( $state['errors'] as $error ) {
			$out .= '<p role="alert">' . esc_html( $error ) . '</p>';
		}
		if ( '' !== $state['notice'] ) {
			$out .= '<p>' . esc_html( $state['notice'] ) . '</p>';
		}
		return $out . '<p><a href="' . esc_url( OwnerRole::url() ) . '">' . esc_html__( 'Go to your account', 'whoiswho' ) . '</a></p></main>';
	}
}
