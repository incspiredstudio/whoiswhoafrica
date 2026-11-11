<?php
/**
 * Failure monitoring hook: public form failures fire one action carrying the form type, a reason code and field names only,
 * never submitted values (REQ-050). Logging and alerting subscribe to it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Failure report.
 */
final class FailureReport {

	public const ACTION = 'wiw_form_failure';

	/**
	 * Fire the event.
	 *
	 * @param string             $form   Form type.
	 * @param string             $reason Reason code (honeypot, too_fast, expired, invalid, validation, rate_limited).
	 * @param array<int, string> $fields Names of failing fields, if any.
	 */
	public static function fire( string $form, string $reason, array $fields = array() ): void {
		if ( function_exists( 'do_action' ) ) {
			do_action( 'wiw_form_failure', $form, $reason, $fields );
		}
	}
}
