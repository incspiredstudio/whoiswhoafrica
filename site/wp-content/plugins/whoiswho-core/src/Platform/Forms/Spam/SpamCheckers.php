<?php
/**
 * Picks the spam checker for the site: the honeypot-only checker, unless code returns another one from the wiw_spam_checker
 * filter. There is no setting that turns on a third-party service (REQ-081, REQ-122).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms\Spam;

/**
 * Spam checker factory.
 */
final class SpamCheckers {

	/**
	 * Checker for this site.
	 */
	public static function for_site(): SpamChecker {
		$checker = apply_filters( 'wiw_spam_checker', new HoneypotSpamChecker() );
		return $checker instanceof SpamChecker ? $checker : new HoneypotSpamChecker();
	}
}
