<?php
/**
 * The default bot check: a hidden honeypot field that humans leave empty, plus the signed time-based FormToken.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Honeypot and time check.
 */
final class NullChallenge implements BotChallenge {

	public const HONEYPOT_FIELD = 'wiw_website';
	public const TOKEN_FIELD    = 'wiw_form_token';

	/**
	 * Token verifier.
	 *
	 * @var FormToken
	 */
	private FormToken $token;

	/**
	 * Constructor.
	 *
	 * @param FormToken $token Token verifier.
	 */
	public function __construct( FormToken $token ) {
		$this->token = $token;
	}

	/**
	 * Check a submission.
	 *
	 * @param FormInput $input Submitted values.
	 * @param string    $form  Form type.
	 */
	public function verify( FormInput $input, string $form ): string {
		$reason = self::OK;
		if ( '' !== $input->raw( self::HONEYPOT_FIELD ) ) {
			$reason = 'honeypot';
		} else {
			$verdict = $this->token->verify( $input->raw( self::TOKEN_FIELD ), $form );
			if ( FormToken::OK !== $verdict ) {
				$reason = $verdict;
			}
		}
		if ( self::OK !== $reason ) {
			FailureReport::fire( $form, $reason );
		}
		return $reason;
	}
}
