<?php
/**
 * The default spam checker: a submission whose hidden honeypot field is filled is spam. It reads only the submitted form and
 * sends nothing anywhere (REQ-081, REQ-122).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms\Spam;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\NullChallenge;

/**
 * Honeypot-only spam checker.
 */
final class HoneypotSpamChecker implements SpamChecker {

	/**
	 * Judge a submission.
	 *
	 * @param FormInput $input Submitted values.
	 * @param string    $form  Form type.
	 */
	public function check( FormInput $input, string $form ): string {
		unset( $form );
		return '' !== $input->raw( NullChallenge::HONEYPOT_FIELD ) ? self::SPAM : self::HAM;
	}
}
