<?php
/**
 * The marketing-mail gate (REQ-061). A marketing message is sent only to an address that has opted in (ConsentChecker, deny by
 * default) and has not unsubscribed, and always carries a one-click unsubscribe: a signed link, the List-Unsubscribe and
 * List-Unsubscribe-Post headers, and an unsubscribe_url in the template context. Unsubscribing stores a keyed hash of the address
 * (never the address) and is checked before consent on every later send. Transactional messages never pass through this gate.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail\Marketing;

use WhoIsWho\Core\Platform\Links\SignedLinks;

/**
 * Marketing gate.
 */
final class Marketing {

	public const PURPOSE = 'unsubscribe';
	public const OPTION  = 'wiw_marketing_unsubscribed';

	/**
	 * Consent checker.
	 *
	 * @var ConsentChecker
	 */
	private ConsentChecker $consent;

	/**
	 * Signed links.
	 *
	 * @var SignedLinks
	 */
	private SignedLinks $links;

	/**
	 * Keyed hash of an address.
	 *
	 * @var callable
	 */
	private $ref;

	/**
	 * Unsubscribed store: reads the list of hashes and writes it back.
	 *
	 * @var callable
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param ConsentChecker $consent Consent checker.
	 * @param SignedLinks    $links   Signed links.
	 * @param callable       $ref     fn( string $address ): string, the keyed hash.
	 * @param callable|null  $store   fn( ?array $write = null ): array, reads the unsubscribed hashes (or replaces them).
	 */
	public function __construct( ConsentChecker $consent, SignedLinks $links, callable $ref, ?callable $store = null ) {
		$this->consent = $consent;
		$this->links   = $links;
		$this->ref     = $ref;
		$this->store   = $store ?? static function ( ?array $write = null ): array {
			if ( null !== $write ) {
				update_option( self::OPTION, $write, false );
			}
			return (array) get_option( self::OPTION, array() );
		};
	}

	/**
	 * The gate for this site. The checker is looked up through the wiw_marketing_consent_checker filter when a message is
	 * sent (the newsletter and privacy modules supply the real one) and is deny-all otherwise.
	 */
	public static function for_site(): self {
		return new self(
			new FilteredConsent(),
			SignedLinks::for_site(),
			static fn ( string $address ): string => hash_hmac( 'sha256', strtolower( trim( $address ) ), wp_salt( 'secure_auth' ) )
		);
	}

	/**
	 * Register the unsubscribe link handler.
	 */
	public function register(): void {
		$this->links->register(
			self::PURPOSE,
			function ( array $link ): string {
				$this->unsubscribe_ref( (string) $link['subject_id'] );
				if ( function_exists( 'do_action' ) ) {
					do_action( 'wiw_marketing_unsubscribed', $link );
				}
				return 'You have been unsubscribed.';
			}
		);
	}

	/**
	 * Whether an event is a marketing message.
	 *
	 * @param string $event Event id.
	 */
	public static function is_marketing( string $event ): bool {
		return in_array( $event, \WhoIsWho\Core\Platform\Mail\Events::marketing(), true );
	}

	/**
	 * Whether a marketing message may go to the address: opted in and not unsubscribed.
	 *
	 * @param string $address Recipient address.
	 */
	public function may_send( string $address ): bool {
		return ! $this->is_unsubscribed( $address ) && $this->consent->has_opted_in( $address );
	}

	/**
	 * Whether the address has unsubscribed.
	 *
	 * @param string $address Address.
	 */
	public function is_unsubscribed( string $address ): bool {
		return in_array( ( $this->ref )( $address ), ( $this->store )(), true );
	}

	/**
	 * Record an unsubscribe by keyed hash.
	 *
	 * @param string $ref Keyed hash of the address.
	 */
	public function unsubscribe_ref( string $ref ): void {
		$list = ( $this->store )();
		if ( '' !== $ref && ! in_array( $ref, $list, true ) ) {
			$list[] = $ref;
			( $this->store )( $list );
		}
	}

	/**
	 * Issue the unsubscribe link for an address.
	 *
	 * @param string $address Recipient address.
	 * @return string The public URL.
	 */
	public function unsubscribe_url( string $address ): string {
		$token = $this->links->issue( self::PURPOSE, 'marketing', ( $this->ref )( $address ), strtolower( trim( $address ) ) );
		return $this->links->url( $token );
	}

	/**
	 * The one-click unsubscribe headers (RFC 8058) for a URL.
	 *
	 * @param string $url Unsubscribe URL.
	 * @return string[]
	 */
	public static function headers( string $url ): array {
		return array(
			'List-Unsubscribe: <' . $url . '>',
			'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
		);
	}
}
