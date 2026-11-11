<?php
/**
 * One gate for public forms: token check, then rate limit. Whatever the outcome, the visitor sees the same response, so a
 * lookup cannot be used to learn whether a record exists. Only the server-side caller sees the real outcome.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Form gate.
 */
final class FormGate {

	/**
	 * Token verifier.
	 *
	 * @var FormToken
	 */
	private FormToken $token;

	/**
	 * Rate limiter.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $limiter;

	/**
	 * Constructor.
	 *
	 * @param FormToken   $token   Token verifier.
	 * @param RateLimiter $limiter Rate limiter.
	 */
	public function __construct( FormToken $token, RateLimiter $limiter ) {
		$this->token   = $token;
		$this->limiter = $limiter;
	}

	/**
	 * Decide whether to process a submission.
	 *
	 * @param string      $form    Form type.
	 * @param string      $token   Submitted token.
	 * @param string      $ip      Visitor IP.
	 * @param string|null $subject Email or link token the submission is about.
	 * @return string "ok", or the reason it was refused (for server-side logging only).
	 */
	public function check( string $form, string $token, string $ip, ?string $subject = null ): string {
		$verdict = $this->token->verify( $token, $form );
		if ( FormToken::OK !== $verdict ) {
			// Still count it against the IP so token probing is limited.
			$this->limiter->allow( $form, $ip, null );
			return $verdict;
		}
		return $this->limiter->allow( $form, $ip, $subject ) ? 'ok' : 'rate_limited';
	}

	/**
	 * The response every visitor gets, whatever happened.
	 *
	 * @return array{status: string, message: string}
	 */
	public static function public_response(): array {
		return array(
			'status'  => 'received',
			'message' => 'Thank you. If we can act on this, we will be in touch.',
		);
	}
}
