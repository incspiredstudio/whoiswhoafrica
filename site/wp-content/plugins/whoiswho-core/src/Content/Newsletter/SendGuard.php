<?php
/**
 * The newsletter's send guard (REQ-061, REQ-249). Any marketing send asks canSend(): only a confirmed, active subscriber passes,
 * so unconfirmed, unsubscribed and unknown addresses are refused. It is also the platform's consent checker for marketing mail
 * (through the wiw_marketing_consent_checker filter), and it withdraws the consent when an unsubscribe link is used, keeping the
 * row with its revoked time.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Newsletter;

use WhoIsWho\Core\Platform\Mail\Marketing\ConsentChecker;

/**
 * Send guard.
 */
final class SendGuard implements ConsentChecker {

	/**
	 * Subscribers.
	 *
	 * @var SubscriberRepository
	 */
	private SubscriberRepository $subscribers;

	/**
	 * Constructor.
	 *
	 * @param SubscriberRepository|null $subscribers Subscriber storage.
	 */
	public function __construct( ?SubscriberRepository $subscribers = null ) {
		$this->subscribers = $subscribers ?? new SubscriberRepository();
	}

	/**
	 * Attach hooks: be the marketing consent checker and honour unsubscribe links.
	 */
	public function register(): void {
		add_filter( 'wiw_marketing_consent_checker', fn (): ConsentChecker => $this );
		add_action( 'wiw_marketing_unsubscribed', array( $this, 'on_unsubscribed' ) );
	}

	/**
	 * Whether a marketing message may go to the address.
	 *
	 * @param string $email Address.
	 */
	public function canSend( string $email ): bool { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- name fixed by the issue.
		$email = strtolower( trim( $email ) );
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) && $this->subscribers->is_subscribed( $email );
	}

	/**
	 * ConsentChecker: opted in means a confirmed, active subscriber.
	 *
	 * @param string $address Recipient address.
	 */
	public function has_opted_in( string $address ): bool {
		return $this->canSend( $address );
	}

	/**
	 * An unsubscribe link was used: record the withdrawal on the subscriber, if there is one.
	 *
	 * @param array<string, mixed> $link The consumed link row.
	 */
	public function on_unsubscribed( array $link ): void {
		$email = isset( $link['email'] ) && is_string( $link['email'] ) ? strtolower( trim( $link['email'] ) ) : '';
		if ( '' !== $email ) {
			$this->subscribers->unsubscribe( $email );
		}
	}
}
