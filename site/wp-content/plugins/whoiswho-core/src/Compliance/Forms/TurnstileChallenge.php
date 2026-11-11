<?php
/**
 * Cloudflare Turnstile behind the BotChallenge interface (REQ-081). The call to Cloudflare is a callable, so tests use a stub
 * and nothing else in the code knows the vendor. The secret comes from the secrets file through the caller; this class never
 * reads the environment.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Forms;

use WhoIsWho\Core\Platform\Forms\BotChallenge;
use WhoIsWho\Core\Platform\Forms\FailureReport;
use WhoIsWho\Core\Platform\Forms\FormInput;

/**
 * Turnstile challenge.
 */
final class TurnstileChallenge implements BotChallenge {

	public const RESPONSE_FIELD = 'cf-turnstile-response';
	public const VERIFY_URL     = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Verifier: receives the response token and returns whether Cloudflare accepted it.
	 *
	 * @var callable(string): bool
	 */
	private $verifier;

	/**
	 * Constructor.
	 *
	 * @param callable(string): bool $verifier Checks a response token with Cloudflare.
	 */
	public function __construct( callable $verifier ) {
		$this->verifier = $verifier;
	}

	/**
	 * A verifier that posts to Cloudflare with wp_remote_post.
	 *
	 * @param string $secret Turnstile secret key.
	 * @return callable(string): bool
	 */
	public static function remote_verifier( string $secret ): callable {
		return static function ( string $response ) use ( $secret ): bool {
			$reply = wp_remote_post(
				self::VERIFY_URL,
				array(
					'timeout' => 5,
					'body'    => array(
						'secret'   => $secret,
						'response' => $response,
					),
				)
			);
			if ( is_wp_error( $reply ) || 200 !== (int) wp_remote_retrieve_response_code( $reply ) ) {
				return false;
			}
			$body = json_decode( (string) wp_remote_retrieve_body( $reply ), true );
			return is_array( $body ) && true === ( $body['success'] ?? false );
		};
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param FormInput $input Submitted fields.
	 * @param string    $form  Form type.
	 */
	public function verify( FormInput $input, string $form ): string {
		$response = $input->raw( self::RESPONSE_FIELD );
		$reason   = self::OK;
		if ( '' === $response ) {
			$reason = 'challenge_missing';
		} elseif ( ! (bool) call_user_func( $this->verifier, $response ) ) {
			$reason = 'challenge_failed';
		}
		if ( self::OK !== $reason ) {
			FailureReport::fire( $form, $reason );
		}
		return $reason;
	}
}
