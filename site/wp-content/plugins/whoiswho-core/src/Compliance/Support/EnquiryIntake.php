<?php
/**
 * Enquiry intake (REQ-177, REQ-039, REQ-018, REQ-205): Get listed, contact and profile enquiries all become one Enquiry item with
 * a reference such as ENQ-0004. It queues an acknowledgement with the reference to the sender (the address is looked up when
 * the job runs, never stored in the job) and a notice to the holders of wiw_support_inbox. An enquiry has no due date and no
 * price, package or tier.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Enquiry intake.
 */
final class EnquiryIntake {

	public const RECIPIENT_SOURCE = 'support_item';

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
	 * Constructor.
	 *
	 * @param SupportItems $items    Support items.
	 * @param Notifier     $notifier Notifier.
	 */
	public function __construct( SupportItems $items, Notifier $notifier ) {
		$this->items    = $items;
		$this->notifier = $notifier;
	}

	/**
	 * Hook the recipient lookup the acknowledgement job uses.
	 */
	public static function register(): void {
		add_filter(
			'wiw_mail_recipient_resolvers',
			static function ( $resolvers ): array {
				$resolvers                           = is_array( $resolvers ) ? $resolvers : array();
				$resolvers[ self::RECIPIENT_SOURCE ] = static function ( int $id ): string {
					$item = ( new SupportItems() )->get( $id );
					return null === $item ? '' : (string) $item['requester_email'];
				};
				return $resolvers;
			}
		);
		add_filter(
			'wiw_mail_name_resolvers',
			static function ( $resolvers ): array {
				$resolvers                           = is_array( $resolvers ) ? $resolvers : array();
				$resolvers[ self::RECIPIENT_SOURCE ] = static function ( int $id ): string {
					$item = ( new SupportItems() )->get( $id );
					return null === $item ? '' : (string) $item['requester_name'];
				};
				return $resolvers;
			}
		);
	}

	/**
	 * Create the enquiry and queue its emails. Values come from EnquiryValidator.
	 *
	 * @param array<string, string> $values     Validated name, organisation, interest, email, phone, message, source.
	 * @param int|null              $profile_id The profile, for a profile enquiry.
	 * @param string                $ip         Visitor IP (stored only as a keyed hash).
	 * @return array<string, mixed> The stored row.
	 */
	public function submit( array $values, ?int $profile_id = null, string $ip = '' ): array {
		$input = array(
			'type'         => ItemType::ENQUIRY,
			'name'         => $values['name'] ?? '',
			'organisation' => $values['organisation'] ?? '',
			'interest'     => $values['interest'] ?? '',
			'email'        => $values['email'] ?? '',
			'phone'        => $values['phone'] ?? '',
			'message'      => $values['message'] ?? '',
			'source'       => $values['source'] ?? '',
			'ip'           => $ip,
		);
		if ( 'profile' === $input['source'] && null !== $profile_id ) {
			$input['profile_id'] = $profile_id;
		}
		$row      = $this->items->create( $input );
		$interest = (string) ( $values['interest'] ?? '' );
		$context  = array(
			'reference' => (string) $row['reference'],
			'id'        => (int) $row['id'],
			'details'   => array_filter(
				array(
					'Type'          => 'profile' === ( $values['source'] ?? '' ) ? 'Enquiry about a profile' : 'General enquiry',
					'Interested in' => $interest,
				),
				static fn ( string $v ): bool => '' !== $v
			),
		);
		$this->notifier->send_to_record( Events::ENQUIRY_ACKNOWLEDGED, self::RECIPIENT_SOURCE, (int) $row['id'], $context );
		$this->notifier->send( Events::ENQUIRY_STAFF_NOTICE, '', $context );
		return $row;
	}
}
