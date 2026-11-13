<?php
/**
 * The newsletter sign-up handler (REQ-061, REQ-058, REQ-081, REQ-122). It validates with one validator, runs the bot challenge,
 * form token and per-IP and per-email throttles (PublicSubmission), records a pending subscriber with a timestamp and the current
 * policy version, and queues the confirmation email. The address is looked up when the job runs, never stored in the job. A
 * repeat sign-up, a confirmed subscriber and a new address all get the same reply, so the form never reveals who is on the list.
 * Nothing is logged with the address.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Newsletter;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Newsletter sign-up.
 */
final class NewsletterSignup {

	public const INVALID = 'invalid';

	public const RECIPIENT_SOURCE = 'newsletter_subscriber';

	/**
	 * Subscribers.
	 *
	 * @var SubscriberRepository
	 */
	private SubscriberRepository $subscribers;

	/**
	 * Notifier.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Form protection.
	 *
	 * @var PublicSubmission
	 */
	private PublicSubmission $submission;

	/**
	 * Constructor.
	 *
	 * @param SubscriberRepository $subscribers Subscriber storage.
	 * @param Notifier             $notifier    Notifier.
	 * @param PublicSubmission     $submission  Bot challenge, token and throttles.
	 */
	public function __construct( SubscriberRepository $subscribers, Notifier $notifier, PublicSubmission $submission ) {
		$this->subscribers = $subscribers;
		$this->notifier    = $notifier;
		$this->submission  = $submission;
	}

	/**
	 * Hook the recipient lookup the confirmation job uses.
	 */
	public static function register(): void {
		add_filter(
			'wiw_mail_recipient_resolvers',
			static function ( $resolvers ): array {
				$resolvers                           = is_array( $resolvers ) ? $resolvers : array();
				$resolvers[ self::RECIPIENT_SOURCE ] = static function ( int $id ): string {
					return ( new SubscriberRepository() )->email_for_id( $id );
				};
				return $resolvers;
			}
		);
	}

	/**
	 * Handle a sign-up.
	 *
	 * @param FormInput $input Submitted fields (with the form token and challenge response).
	 * @param string    $ip    Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	public function submit( FormInput $input, string $ip ): array {
		$result = ( new NewsletterValidator() )->validate( $input );
		if ( ! $result->is_valid() ) {
			return $this->reply( self::INVALID, 'Please check the highlighted fields.', $result->errors() );
		}
		$email  = $result->value( 'email' );
		$answer = $this->submission->handle(
			'newsletter',
			$input,
			$ip,
			$email,
			fn (): bool => $this->record( $email )
		);
		if ( PublicSubmission::RECEIVED !== $answer['status'] ) {
			return $this->reply( $answer['status'], $answer['message'] );
		}
		return $this->reply( PublicSubmission::RECEIVED, 'Thank you. Please check your email to confirm your subscription.' );
	}

	/**
	 * Record the pending sign-up and queue the confirmation when one is needed.
	 *
	 * @param string $email Validated address.
	 */
	private function record( string $email ): bool {
		$added = $this->subscribers->add_pending( $email );
		if ( null === $added['token'] ) {
			return true;
		}
		$row = $this->subscribers->find_by_email_hash( ConsentRecorder::hash_email( $email ) );
		if ( null === $row ) {
			return true;
		}
		$link = (string) apply_filters( 'wiw_newsletter_confirm_url', home_url( '/confirm/' . $added['token'] ), $added['token'] );
		$this->notifier->send_to_record(
			Events::NEWSLETTER_CONFIRMATION,
			self::RECIPIENT_SOURCE,
			(int) $row['id'],
			array( 'lead' => 'Please confirm your subscription to the stories newsletter: ' . $link )
		);
		return true;
	}

	/**
	 * Build a reply.
	 *
	 * @param string                $status  Status.
	 * @param string                $message Message for the visitor.
	 * @param array<string, string> $errors  Field errors.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	private function reply( string $status, string $message, array $errors = array() ): array {
		return array(
			'status'    => $status,
			'message'   => $message,
			'errors'    => $errors,
			'reference' => '',
		);
	}
}
