<?php
/**
 * The spam check a public form runs on the content of a submission, after the bot challenge (REQ-081, REQ-122). Forms depend only
 * on this interface. The shipped checker works on the site alone (a hidden honeypot field), so by default no data leaves the site.
 * No third-party spam service is wired: the owner decided against Akismet (issue #2). A service adapter could be added later by
 * implementing this interface and returning it from the wiw_spam_checker filter, but it would need the owner's approval first,
 * because it would send visitor data to a third party.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms\Spam;

use WhoIsWho\Core\Platform\Forms\FormInput;

/**
 * Spam checker.
 */
interface SpamChecker {

	public const HAM  = 'ham';
	public const SPAM = 'spam';

	/**
	 * Judge a submission.
	 *
	 * @param FormInput $input Submitted values.
	 * @param string    $form  Form type.
	 * @return string HAM or SPAM. The sender is never told which (the response is the same uniform one for both).
	 */
	public function check( FormInput $input, string $form ): string;
}
