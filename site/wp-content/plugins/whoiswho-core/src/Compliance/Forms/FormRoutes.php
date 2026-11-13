<?php
/**
 * The public POST routes for the claim, Get listed and contact forms (REQ-251, REQ-177, REQ-039, REQ-058, REQ-081, REQ-122).
 * POST /claim/{slug}, POST /subscribe (the Get listed lead) and POST /contact each read the form once through FormInput, call the
 * service that validates, protects and stores it, and answer: a 303 to the done page when the item was received, JSON when the
 * visitor asked for JSON, otherwise a 4xx status and the `wiw_public_form_result` action so the theme page can show the errors.
 * GET requests are never touched: the theme builds those pages.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Forms;

use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Awards\NominationIntake;
use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Compliance\Support\EnquiryIntake;
use WhoIsWho\Core\Compliance\Support\EnquirySubmission;
use WhoIsWho\Core\Compliance\Support\RequestIntake;
use WhoIsWho\Core\Compliance\Support\RequestSubmission;
use WhoIsWho\Core\Compliance\Confirm\RequesterConfirmation;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Content\Newsletter\NewsletterSignup;
use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Forms\FormGate;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\NullChallenge;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Forms\TransientStore;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Trust\ClaimIntake;
use WhoIsWho\Core\Trust\ClaimRepository;

/**
 * Form routes.
 */
final class FormRoutes {

	/**
	 * Path patterns (relative to the site root) mapped to form names. The first capture, if any, is the slug.
	 */
	private const ROUTES = array(
		'#^claim/([A-Za-z0-9_-]+)/?$#'   => 'claim',
		'#^subscribe/?$#'                => 'get_listed',
		'#^contact/?$#'                  => 'contact',
		'#^enquire/([A-Za-z0-9_-]+)/?$#' => 'profile_enquiry',
		'#^newsletter/?$#'               => 'newsletter',
		'#^correct/([A-Za-z0-9_-]+)/?$#' => 'correction',
		'#^remove/([A-Za-z0-9_-]+)/?$#'  => 'removal',
		'#^nominate/start/?$#'           => 'nomination_start',
		'#^nominate/?$#'                 => 'nomination',
		'#^report/([A-Za-z0-9_-]+)/?$#'  => 'report',
	);

	/**
	 * Handlers by form name: fn( FormInput, string $ip, string $slug ): array with status, message, errors, reference.
	 *
	 * @var array<string, callable>
	 */
	private array $handlers;

	/**
	 * Constructor.
	 *
	 * @param array<string, callable> $handlers Handlers by form name (claim, get_listed, contact).
	 */
	public function __construct( array $handlers ) {
		$this->handlers = $handlers;
	}

	/**
	 * Routes wired to the real services.
	 */
	public static function for_site(): self {
		$secret    = defined( 'WIW_TURNSTILE_SECRET' ) ? (string) constant( 'WIW_TURNSTILE_SECRET' ) : '';
		$token     = FormToken::for_site();
		$challenge = '' !== $secret ? new TurnstileChallenge( TurnstileChallenge::remote_verifier( $secret ) ) : new NullChallenge( $token );
		$gate      = new FormGate( $token, new RateLimiter( new TransientStore(), wp_salt( 'auth' ), self::limits() ) );
		$protect   = new PublicSubmission( $challenge, $gate );
		$consent   = new ConsentRecorder( null, new SettingsService( SettingsService::default_registry() ) );
		$notifier  = Notifier::for_site();
		return new self(
			array(
				'claim'            => static fn ( FormInput $input, string $ip, string $slug ): array => ( new ClaimIntake( new ClaimRepository(), $notifier, $protect, $consent ) )->submit( $slug, $input, $ip ),
				'get_listed'       => static fn ( FormInput $input, string $ip ): array => ( new EnquirySubmission( new EnquiryIntake( new SupportItems(), $notifier ), $protect, $consent ) )->get_listed( $input, $ip ),
				'contact'          => static fn ( FormInput $input, string $ip ): array => ( new EnquirySubmission( new EnquiryIntake( new SupportItems(), $notifier ), $protect, $consent ) )->contact( $input, $ip ),
				'profile_enquiry'  => static fn ( FormInput $input, string $ip, string $slug ): array => ( new EnquirySubmission( new EnquiryIntake( new SupportItems(), $notifier ), $protect, $consent ) )->profile( $slug, $input, $ip ),
				'correction'       => static fn ( FormInput $input, string $ip, string $slug ): array => ( new RequestSubmission( new RequestIntake( new SupportItems(), $notifier, new RequesterConfirmation( new SupportItems(), SignedLinks::for_site() ) ), $protect ) )->correction( $slug, $input, $ip ),
				'report'           => static fn ( FormInput $input, string $ip, string $slug ): array => ( new RequestSubmission( new RequestIntake( new SupportItems(), $notifier, new RequesterConfirmation( new SupportItems(), SignedLinks::for_site() ) ), $protect ) )->report( $slug, $input, $ip ),
				'removal'          => static fn ( FormInput $input, string $ip, string $slug ): array => ( new RequestSubmission( new RequestIntake( new SupportItems(), $notifier, new RequesterConfirmation( new SupportItems(), SignedLinks::for_site() ) ), $protect ) )->removal( $slug, $input, $ip ),
				'newsletter'       => static fn ( FormInput $input, string $ip ): array => ( new NewsletterSignup( new SubscriberRepository(), $notifier, $protect ) )->submit( $input, $ip ),
				'nomination_start' => static fn ( FormInput $input, string $ip ): array => ( new NominationIntake( $notifier, $protect, SignedLinks::for_site(), CategoryService::for_site() ) )->begin( $input, $ip ),
				'nomination'       => static fn ( FormInput $input, string $ip ): array => ( new NominationIntake( $notifier, $protect, SignedLinks::for_site(), CategoryService::for_site() ) )->submit( $input, $ip ),
			)
		);
	}

	/**
	 * Throttles: the defaults.
	 *
	 * @return array<string, array<string, array{0: int, 1: int}>>
	 */
	private static function limits(): array {
		return RateLimiter::LIMITS;
	}

	/**
	 * Attach the hook; the services are built only when a POST arrives.
	 */
	public static function register_for_site(): void {
		add_action(
			'template_redirect',
			static function (): void {
				if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
					self::for_site()->serve();
				}
			},
			1
		);
	}

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'serve' ), 1 );
	}

	/**
	 * Which form a path belongs to.
	 *
	 * @param string $path Path relative to the site root.
	 * @return array{form: string, slug: string}|null
	 */
	public function match( string $path ): ?array {
		$path = trim( $path, '/' );
		foreach ( self::ROUTES as $pattern => $form ) {
			if ( 1 === preg_match( $pattern, $path, $found ) ) {
				return array(
					'form' => $form,
					'slug' => $found[1] ?? '',
				);
			}
		}
		return null;
	}

	/**
	 * Run a request. Returns null when it is not a form submission.
	 *
	 * @param string    $method HTTP method.
	 * @param string    $path   Path relative to the site root.
	 * @param FormInput $input  Submitted fields.
	 * @param string    $ip     Visitor IP.
	 * @return array{form: string, status: string, message: string, errors: array<string, string>, reference: string, http: int, draft?: string}|null
	 */
	public function dispatch( string $method, string $path, FormInput $input, string $ip ): ?array {
		if ( 'POST' !== strtoupper( $method ) ) {
			return null;
		}
		$route = $this->match( $path );
		if ( null === $route || ! isset( $this->handlers[ $route['form'] ] ) ) {
			return null;
		}
		$result         = call_user_func( $this->handlers[ $route['form'] ], $input, $ip, $route['slug'] );
		$result['form'] = $route['form'];
		$result['http'] = self::http_status( (string) $result['status'] );
		return $result;
	}

	/**
	 * The HTTP status for a service status.
	 *
	 * @param string $status Service status.
	 */
	public static function http_status( string $status ): int {
		$map = array(
			PublicSubmission::RECEIVED       => 303,
			'invalid'                        => 422,
			PublicSubmission::TRY_LATER      => 429,
			PublicSubmission::EXPIRED        => 400,
			ClaimIntake::NOT_FOUND           => 404,
			ClaimIntake::REVIEW_FIRST        => 409,
			ClaimIntake::ALREADY_CLAIMED     => 409,
			EnquirySubmission::ENQUIRIES_OFF => 409,
			NominationIntake::CLOSED         => 409,
			NominationIntake::UNVERIFIED     => 409,
			NominationIntake::DUPLICATE      => 409,
		);
		return $map[ $status ] ?? 400;
	}

	/**
	 * Where the visitor goes after a received submission. The theme may replace it with the filter.
	 *
	 * @param array{form: string, reference: string} $result Dispatch result.
	 */
	public static function done_url( array $result ): string {
		$kind = in_array( $result['form'], array( 'claim', 'newsletter', 'correction', 'removal', 'report', 'nomination', 'nomination_start' ), true ) ? $result['form'] : 'enquiry';
		$url  = add_query_arg(
			array(
				'kind' => $kind,
				'ref'  => $result['reference'],
			),
			home_url( '/done/' )
		);
		return (string) apply_filters( 'wiw_form_done_url', $url, $result );
	}

	/**
	 * The template_redirect handler.
	 */
	public function serve(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( 'POST' !== strtoupper( $method ) ) {
			return;
		}
		$request = isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->request ) ? (string) $GLOBALS['wp']->request : '';
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$result  = $this->dispatch( $method, $request, FormInput::from_globals(), $ip );
		if ( null === $result ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		if ( false !== strpos( $accept, 'application/json' ) ) {
			wp_send_json(
				array(
					'status'    => $result['status'],
					'message'   => $result['message'],
					'errors'    => $result['errors'],
					'reference' => $result['reference'],
					'draft'     => $result['draft'] ?? '',
				),
				303 === $result['http'] ? 200 : $result['http']
			);
		}
		if ( 303 === $result['http'] ) {
			wp_safe_redirect( self::done_url( $result ), 303 );
			exit;
		}
		status_header( $result['http'] );
		/**
		 * A form was refused; the theme page for this URL shows the message and field errors.
		 *
		 * @param array $result Form, status, message, errors, reference, http.
		 */
		do_action( 'wiw_public_form_result', $result );
	}
}
