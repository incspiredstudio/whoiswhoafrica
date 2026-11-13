<?php
/**
 * Information sent to a subject on request (REQ-236). A person may ask what we hold or how their page is doing. Staff send, from
 * the item, any of four kinds: the status of the request, the all-time view count of the profile, the sources the profile came
 * from, and the number of enquiries about it. Every line is built from data staff already see on the same screens; nothing new is
 * collected. The email is queued to the requester's address on the item (looked up when the job runs), and each send is written
 * to the audit log with who sent which kinds.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Info;

use WhoIsWho\Core\Compliance\Support\EnquiryIntake;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\Views\ViewCounter;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Review\Duplicates\Matcher;

/**
 * Information sender.
 */
final class InformationSender {

	public const CAP = 'wiw_handle_requests';

	public const STATUS    = 'status';
	public const VIEWS     = 'views';
	public const SOURCES   = 'sources';
	public const ENQUIRIES = 'enquiries';

	public const KINDS = array(
		self::STATUS    => 'Status of the request',
		self::VIEWS     => 'View count',
		self::SOURCES   => 'Sources used',
		self::ENQUIRIES => 'Enquiries received',
	);

	/**
	 * Support items.
	 *
	 * @var SupportItems
	 */
	private SupportItems $items;

	/**
	 * View counter.
	 *
	 * @var ViewCounter
	 */
	private ViewCounter $views;

	/**
	 * Notifier.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param SupportItems $items    Support items.
	 * @param ViewCounter  $views    View counter.
	 * @param Notifier     $notifier Notifier.
	 * @param AuditLogger  $audit    Audit logger.
	 */
	public function __construct( SupportItems $items, ViewCounter $views, Notifier $notifier, AuditLogger $audit ) {
		$this->items    = $items;
		$this->views    = $views;
		$this->notifier = $notifier;
		$this->audit    = $audit;
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		return new self( new SupportItems(), new ViewCounter(), Notifier::for_site(), AuditLogger::for_site() );
	}

	/**
	 * The lines of information for an item, one per kind asked for.
	 *
	 * @param int      $item_id Support item.
	 * @param string[] $kinds   Kinds from KINDS.
	 * @return string[]
	 * @throws \InvalidArgumentException For an unknown item or kind, or no kind.
	 */
	public function lines( int $item_id, array $kinds ): array {
		$item = $this->items->get( $item_id );
		if ( null === $item ) {
			throw new \InvalidArgumentException( 'That item does not exist.' );
		}
		$kinds = array_values( array_unique( $kinds ) );
		if ( array() === $kinds ) {
			throw new \InvalidArgumentException( 'Choose what to send.' );
		}
		$profile = null !== $item['profile_id'] ? (int) $item['profile_id'] : 0;
		$lines   = array();
		foreach ( $kinds as $kind ) {
			if ( ! isset( self::KINDS[ $kind ] ) ) {
				throw new \InvalidArgumentException( 'Unknown kind of information.' );
			}
			$lines[] = $this->line( $kind, $item, $profile );
		}
		return $lines;
	}

	/**
	 * Send the chosen information to the requester and log it.
	 *
	 * @param int      $item_id Support item.
	 * @param string[] $kinds   Kinds from KINDS.
	 * @throws \DomainException Without the capability.
	 * @throws \InvalidArgumentException For an unknown item or kind, or an item with no address.
	 */
	public function send( int $item_id, array $kinds ): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to send information.' );
		}
		$lines = $this->lines( $item_id, $kinds );
		$item  = $this->items->get( $item_id );
		if ( null === $item || '' === (string) $item['requester_email'] ) {
			throw new \InvalidArgumentException( 'That item has no address to send to.' );
		}
		$this->notifier->send_to_record(
			Events::INFORMATION_SENT,
			EnquiryIntake::RECIPIENT_SOURCE,
			$item_id,
			array(
				'reference' => (string) $item['reference'],
				'id'        => $item_id,
				'lead'      => implode( "\n", $lines ),
			)
		);
		$this->audit->log(
			'info.sent',
			array(
				'type' => 'support_item',
				'id'   => $item_id,
			),
			null,
			array( 'kinds' => array_values( array_unique( $kinds ) ) )
		);
	}

	/**
	 * One line, from data staff already see.
	 *
	 * @param string               $kind    Kind.
	 * @param array<string, mixed> $item    Item.
	 * @param int                  $profile Profile id, or 0.
	 */
	private function line( string $kind, array $item, int $profile ): string {
		switch ( $kind ) {
			case self::STATUS:
				return 'Status of your request ' . (string) $item['reference'] . ': ' . str_replace( '_', ' ', (string) $item['status'] ) . '.';
			case self::VIEWS:
				return 0 === $profile ? 'There is no profile to count views for.' : 'Your profile has been viewed ' . $this->views->total( $profile ) . ' times in total.';
			case self::SOURCES:
				$sources = 0 === $profile ? array() : array_keys( $this->external_ids( $profile ) );
				return array() === $sources ? 'We do not hold source identifiers for this profile.' : 'The profile was built from these sources: ' . implode( ', ', $sources ) . '.';
			default:
				return 0 === $profile ? 'There are no enquiries.' : 'Enquiries received about your profile: ' . $this->enquiry_count( $profile ) . '.';
		}
	}

	/**
	 * Enquiries recorded for a profile.
	 *
	 * @param int $profile Profile id.
	 */
	private function enquiry_count( int $profile ): int {
		$count = 0;
		foreach ( $this->items->list_by_types( array( ItemType::ENQUIRY ), '', 1000 ) as $row ) {
			if ( (int) ( $row['profile_id'] ?? 0 ) === $profile ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * The profile's external ids, source => id.
	 *
	 * @param int $profile Profile id.
	 * @return array<string, string>
	 */
	private function external_ids( int $profile ): array {
		$ids = json_decode( (string) get_post_meta( $profile, Matcher::EXTERNAL_IDS_META, true ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- decoding our own stored JSON.
		$out = array();
		foreach ( is_array( $ids ) ? $ids : array() as $source => $value ) {
			if ( is_string( $value ) || is_int( $value ) ) {
				$out[ (string) $source ] = (string) $value;
			}
		}
		return $out;
	}
}
