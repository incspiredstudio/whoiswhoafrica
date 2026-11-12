<?php
/**
 * Confirming the requester's email by a single-use signed link (REQ-233, REQ-232, REQ-157). A correction, removal or report is
 * issued a link when it is submitted; the shared /link route shows a button on GET (a mail scanner's prefetch consumes nothing)
 * and consumes the link on POST, where the handler here stamps the item's confirmed_at. Until then staff see the item labelled
 * "email not confirmed"; a used, revoked or expired link shows the route's expired-or-used page and changes nothing. No name or
 * address is put in the link.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Confirm;

use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Links\SignedLinks;

/**
 * Requester confirmation.
 */
final class RequesterConfirmation {

	public const PURPOSE      = 'verify_requester';
	public const SUBJECT_TYPE = 'support_item';

	/**
	 * Item types whose requester must confirm an email address.
	 *
	 * @return string[]
	 */
	public static function types(): array {
		return array( ItemType::CORRECTION, ItemType::REMOVAL, ItemType::REPORT );
	}

	/**
	 * Items.
	 *
	 * @var SupportItems
	 */
	private SupportItems $items;

	/**
	 * Links.
	 *
	 * @var SignedLinks
	 */
	private SignedLinks $links;

	/**
	 * Constructor.
	 *
	 * @param SupportItems $items Items.
	 * @param SignedLinks  $links Links.
	 */
	public function __construct( SupportItems $items, SignedLinks $links ) {
		$this->items = $items;
		$this->links = $links;
	}

	/**
	 * For this site: register the link handler on the shared signed link service.
	 */
	public static function register(): void {
		$links = SignedLinks::for_site();
		$links->register( self::PURPOSE, array( new self( new SupportItems(), $links ), 'handle' ) );
	}

	/**
	 * Issue the confirmation link for a stored item; any earlier link for the item stops working.
	 *
	 * @param array<string, mixed> $item A support item row.
	 * @return string The URL to put in the email.
	 * @throws \InvalidArgumentException For an item type that needs no confirmation.
	 */
	public function issue( array $item ): string {
		if ( ! in_array( (string) $item['item_type'], self::types(), true ) ) {
			throw new \InvalidArgumentException( 'That kind of item needs no email confirmation.' );
		}
		$token = $this->links->reissue( self::PURPOSE, self::SUBJECT_TYPE, (string) $item['id'], (string) $item['requester_email'] );
		return $this->links->url( $token );
	}

	/**
	 * The link was used: confirm the item's email and return the text of the received page.
	 *
	 * @param array<string, mixed> $link The link row.
	 */
	public function handle( array $link ): string {
		$item = $this->items->get( (int) $link['subject_id'] );
		if ( null === $item || self::SUBJECT_TYPE !== (string) $link['subject_type'] ) {
			return 'This link is no longer valid.';
		}
		if ( null === $item['confirmed_at'] ) {
			$this->items->confirm_email( (int) $item['id'] );
		}
		/* translators: %s: the request reference. */
		return sprintf( __( 'Thank you. Your email is confirmed and we have received your request. Your reference is %s.', 'whoiswho' ), (string) $item['reference'] );
	}

	/**
	 * The label staff see for an item's email confirmation, or an empty string for an item that needs none.
	 *
	 * @param array<string, mixed> $item A support item row.
	 */
	public static function label( array $item ): string {
		if ( ! in_array( (string) $item['item_type'], self::types(), true ) ) {
			return '';
		}
		return null === ( $item['confirmed_at'] ?? null ) ? __( 'Email not confirmed', 'whoiswho' ) : __( 'Email confirmed', 'whoiswho' );
	}

	/**
	 * Whether the requester has confirmed.
	 *
	 * @param array<string, mixed> $item A support item row.
	 */
	public static function is_confirmed( array $item ): bool {
		return null !== ( $item['confirmed_at'] ?? null );
	}
}
