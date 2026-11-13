<?php
/**
 * Intake of a correction, removal or report (REQ-157, REQ-158, REQ-232, REQ-233, REQ-270, REQ-271): creates the typed support
 * item with its due dates, and queues the emails. The requester gets one acknowledgement with the reference, and, when they say
 * they are the person or their representative (or for any report that gives an address), the single-use link that confirms their
 * email. The address is looked up when the mail job runs, never stored in the job. Staff holding the type's capability get a
 * notice. Nothing here changes a profile: staff act on the item.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Compliance\Confirm\RequesterConfirmation;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Request intake.
 */
final class RequestIntake {

	/**
	 * Relationships whose email must be confirmed by link; "someone else" goes in directly.
	 */
	public const VERIFIED_RELATIONSHIPS = array( 'person', 'representative' );

	/**
	 * Staff notice event by item type.
	 */
	private const STAFF_EVENTS = array(
		ItemType::CORRECTION => Events::CORRECTION_RECEIVED,
		ItemType::REMOVAL    => Events::REMOVAL_RECEIVED,
		ItemType::REPORT     => Events::REPORT_RECEIVED,
	);

	/**
	 * Items.
	 *
	 * @var SupportItems
	 */
	private SupportItems $items;

	/**
	 * Notifier.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Confirmation links.
	 *
	 * @var RequesterConfirmation
	 */
	private RequesterConfirmation $confirmation;

	/**
	 * Constructor.
	 *
	 * @param SupportItems          $items        Items.
	 * @param Notifier              $notifier     Notifier.
	 * @param RequesterConfirmation $confirmation Confirmation links.
	 */
	public function __construct( SupportItems $items, Notifier $notifier, RequesterConfirmation $confirmation ) {
		$this->items        = $items;
		$this->notifier     = $notifier;
		$this->confirmation = $confirmation;
	}

	/**
	 * Create the item and queue its emails.
	 *
	 * @param string                $type       Correction, removal or report.
	 * @param array<string, string> $values     Validated values: name, relationship, email, message.
	 * @param int                   $profile_id The public profile the request is about.
	 * @param string                $ip         Visitor IP (stored only as a keyed hash).
	 * @param bool                  $verify     Whether to send the email-confirmation link.
	 * @return array<string, mixed> The stored row.
	 * @throws \InvalidArgumentException For a type that is not a request.
	 */
	public function submit( string $type, array $values, int $profile_id, string $ip, bool $verify ): array {
		if ( ! isset( self::STAFF_EVENTS[ $type ] ) ) {
			throw new \InvalidArgumentException( 'Not a request type.' );
		}
		$row     = $this->items->create(
			array(
				'type'         => $type,
				'profile_id'   => $profile_id,
				'name'         => $values['name'] ?? '',
				'relationship' => $values['relationship'] ?? '',
				'email'        => $values['email'] ?? '',
				'message'      => $values['message'] ?? '',
				'ip'           => $ip,
			)
		);
		$context = array(
			'reference' => (string) $row['reference'],
			'id'        => (int) $row['id'],
			'lead'      => 'We have received your request.',
		);
		if ( '' !== (string) $row['requester_email'] ) {
			if ( $verify ) {
				$context['link_token'] = $this->confirmation->issue_token( $row );
				$context['lead']       = 'We have received your request. Please confirm your email with the link below.';
			}
			$this->notifier->send_to_record( Events::REQUEST_ACKNOWLEDGED, EnquiryIntake::RECIPIENT_SOURCE, (int) $row['id'], $context );
		}
		$this->notifier->send(
			self::STAFF_EVENTS[ $type ],
			'',
			array(
				'reference' => $context['reference'],
				'id'        => $context['id'],
			)
		);
		return $row;
	}
}
