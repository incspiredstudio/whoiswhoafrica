<?php
/**
 * The one entry point for the public support forms (enquiry, correction, removal, report). It runs the bot challenge, the
 * form token and the per-IP and per-email throttles, and only then calls the creator that makes the item. The visitor gets
 * the same reply whether or not the profile or the email is known (REQ-122, REQ-081); three outcomes are distinguishable
 * because none of them reveals a record: received, try later, and form expired (a stale token or an unsolved challenge).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Forms;

use WhoIsWho\Core\Platform\Forms\BotChallenge;
use WhoIsWho\Core\Platform\Forms\FormGate;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\NullChallenge;

/**
 * Public submission handler.
 */
final class PublicSubmission {

	public const RECEIVED  = 'received';
	public const TRY_LATER = 'try_later';
	public const EXPIRED   = 'form_expired';

	/**
	 * Bot challenge.
	 *
	 * @var BotChallenge
	 */
	private BotChallenge $challenge;

	/**
	 * Token and rate-limit gate.
	 *
	 * @var FormGate
	 */
	private FormGate $gate;

	/**
	 * Constructor.
	 *
	 * @param BotChallenge $challenge Bot challenge (Turnstile, or the honeypot fallback).
	 * @param FormGate     $gate      Token and throttle gate.
	 */
	public function __construct( BotChallenge $challenge, FormGate $gate ) {
		$this->challenge = $challenge;
		$this->gate      = $gate;
	}

	/**
	 * Handle one submission. The creator runs only when every check passed.
	 *
	 * @param string            $form    Form type (enquiry, correction, removal or report).
	 * @param FormInput         $input   Submitted fields.
	 * @param string            $ip      Visitor IP.
	 * @param string            $subject Email the submission is about, for the per-email throttle.
	 * @param callable(): mixed $create  Creates the item; its result is returned in "item".
	 * @return array{status: string, message: string, outcome: string, item: mixed} The first two keys are for the visitor; outcome and item are for the server only.
	 */
	public function handle( string $form, FormInput $input, string $ip, string $subject, callable $create ): array {
		$challenge = $this->challenge->verify( $input, $form );
		if ( BotChallenge::OK !== $challenge ) {
			return $this->reply( self::EXPIRED, $challenge, null );
		}
		$verdict = $this->gate->check( $form, $input->raw( NullChallenge::TOKEN_FIELD ), $ip, $subject );
		if ( 'rate_limited' === $verdict ) {
			return $this->reply( self::TRY_LATER, $verdict, null );
		}
		if ( 'ok' !== $verdict ) {
			return $this->reply( self::EXPIRED, $verdict, null );
		}
		return $this->reply( self::RECEIVED, 'ok', call_user_func( $create ) );
	}

	/**
	 * Build the reply.
	 *
	 * @param string $status  Public status.
	 * @param string $outcome Real outcome, server side only.
	 * @param mixed  $item    Created item or null.
	 * @return array{status: string, message: string, outcome: string, item: mixed}
	 */
	private function reply( string $status, string $outcome, $item ): array {
		$messages = array(
			self::RECEIVED  => FormGate::public_response()['message'],
			self::TRY_LATER => 'Too many requests from here. Please try again later.',
			self::EXPIRED   => 'This form has expired. Please reload the page and try again.',
		);
		return array(
			'status'  => $status,
			'message' => $messages[ $status ],
			'outcome' => $outcome,
			'item'    => $item,
		);
	}
}
