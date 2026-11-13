<?php
/**
 * Failure alerts (REQ-050): a job that failed after its retries, or a burst of form failures, reaches the person named in the
 * runbook by email. The message holds job names, form names, codes and ids only, never an address or a name. Alerts are
 * throttled so one fault sends one message an hour. The recipient is the option below, or the site administrator address.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Monitoring;

/**
 * Alerts.
 */
final class Alerts {

	public const OPTION_RECIPIENT = 'wiw_alert_email';
	public const THROTTLE_PREFIX  = 'wiw_alert_';
	public const FORM_THRESHOLD   = 10;
	private const WINDOW          = 3600;

	/**
	 * Mail sender: fn( to, subject, body ): bool.
	 *
	 * @var callable
	 */
	private $mailer;

	/**
	 * Constructor.
	 *
	 * @param callable|null $mailer Sends one message; wp_mail by default.
	 */
	public function __construct( ?callable $mailer = null ) {
		$this->mailer = $mailer ?? static fn ( string $to, string $subject, string $body ): bool => wp_mail( $to, $subject, $body );
	}

	/**
	 * Attach to the failure actions.
	 */
	public function register(): void {
		add_action( 'wiw_job_failed', array( $this, 'job_failed' ), 20, 2 );
		add_action( 'wiw_form_failure', array( $this, 'form_failure' ), 20, 3 );
	}

	/**
	 * Who gets alerts.
	 */
	public function recipient(): string {
		$set = (string) get_option( self::OPTION_RECIPIENT, '' );
		if ( '' !== $set && false !== is_email( $set ) ) {
			return $set;
		}
		return (string) get_option( 'admin_email', '' );
	}

	/**
	 * A job failed after its retries.
	 *
	 * @param string $job Job name.
	 * @param string $ref Reference id.
	 */
	public function job_failed( string $job, string $ref = '' ): void {
		$key = self::THROTTLE_PREFIX . 'job_' . md5( $job );
		if ( false !== get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, self::WINDOW );
		$this->send( 'A background job failed', "The job \"{$job}\" failed after its retries (reference {$ref}). See the failed jobs notice in wp-admin." );
	}

	/**
	 * A public form failed on the server. One alert when failures pile up within the hour.
	 *
	 * @param string   $form   Form name.
	 * @param string   $reason Code.
	 * @param string[] $fields Field names (unused; never values).
	 */
	public function form_failure( string $form, string $reason, array $fields = array() ): void {
		unset( $fields );
		$count_key = self::THROTTLE_PREFIX . 'formcount';
		$count     = (int) get_transient( $count_key ) + 1;
		set_transient( $count_key, $count, self::WINDOW );
		if ( $count < self::FORM_THRESHOLD || false !== get_transient( self::THROTTLE_PREFIX . 'form' ) ) {
			return;
		}
		set_transient( self::THROTTLE_PREFIX . 'form', 1, self::WINDOW );
		$this->send( 'Form failures are piling up', "{$count} form failures in the last hour; the latest was form \"{$form}\", code {$reason}. See the log for request ids." );
	}

	/**
	 * Send one message.
	 *
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 */
	private function send( string $subject, string $body ): void {
		$to = $this->recipient();
		if ( '' === $to ) {
			return;
		}
		( $this->mailer )( $to, '[' . wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $subject, $body );
	}
}
