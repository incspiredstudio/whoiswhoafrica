<?php
/**
 * Signed single-use links for emails (confirm a correction, claim a profile, verify a nomination). A token is 32 random bytes,
 * shown once in the URL and stored only as a SHA-256 hash. It is bound to one purpose and one record, expires (14 days by
 * default, shorter per purpose), and is consumed atomically on POST. A GET never consumes it, so mail scanners that open links
 * cannot spend them. No name or address ever appears in the URL.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Links;

/**
 * Signed links.
 */
final class SignedLinks {

	public const DEFAULT_TTL = 1209600;
	// 14 days.

	/**
	 * Store.
	 *
	 * @var LinkStore
	 */
	private LinkStore $store;

	/**
	 * Clock returning Unix time.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Purpose handlers.
	 *
	 * @var array<string, callable>
	 */
	private array $handlers = array();

	/**
	 * Constructor.
	 *
	 * @param LinkStore     $store Store.
	 * @param callable|null $clock Returns Unix time.
	 */
	public function __construct( LinkStore $store, ?callable $clock = null ) {
		$this->store = $store;
		$this->clock = $clock ?? static fn (): int => time();
	}

	/**
	 * Service for this site (shared, so handlers registered by modules are seen by the route).
	 */
	public static function for_site(): self {
		static $shared = null;
		if ( null === $shared ) {
			$shared = new self( new WpdbLinkStore() );
		}
		return $shared;
	}

	/**
	 * Register the handler for a purpose. It receives the consumed link row and returns a message to show.
	 *
	 * @param string   $purpose Purpose.
	 * @param callable $handler fn( array $link ): string.
	 */
	public function register( string $purpose, callable $handler ): void {
		$this->handlers[ $purpose ] = $handler;
	}

	/**
	 * Issue a link.
	 *
	 * @param string      $purpose      What it is for, for example "verify_correction".
	 * @param string      $subject_type Record type.
	 * @param string      $subject_id   Record id.
	 * @param string|null $email        Address it is sent to (personal data; never put in the URL).
	 * @param int         $ttl          Lifetime in seconds.
	 * @return string The token, to put in the URL path once.
	 */
	public function issue( string $purpose, string $subject_type, string $subject_id, ?string $email = null, int $ttl = self::DEFAULT_TTL ): string {
		$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$now   = (int) ( $this->clock )();
		$this->store->insert(
			array(
				'token_hash'   => self::hash( $token ),
				'purpose'      => $purpose,
				'subject_type' => $subject_type,
				'subject_id'   => $subject_id,
				'email'        => $email,
				'created_at'   => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'   => gmdate( 'Y-m-d H:i:s', $now + max( 60, min( $ttl, self::DEFAULT_TTL ) ) ),
			)
		);
		return $token;
	}

	/**
	 * The public URL for a token.
	 *
	 * @param string $token Token.
	 */
	public function url( string $token ): string {
		return function_exists( 'home_url' ) ? home_url( '/link/' . $token ) : '/link/' . $token;
	}

	/**
	 * Look at a link without consuming it. Null for unknown, used, revoked and expired alike.
	 *
	 * @param string $token Token.
	 * @return array<string, mixed>|null
	 */
	public function peek( string $token ): ?array {
		$row = $this->store->find( self::hash( $token ) );
		if ( null === $row || null !== $row['used_at'] || null !== $row['revoked_at'] || (string) $row['expires_at'] <= gmdate( 'Y-m-d H:i:s', (int) ( $this->clock )() ) ) {
			return null;
		}
		return $row;
	}

	/**
	 * Consume a link (single use) and run its purpose handler.
	 *
	 * @param string $token Token.
	 * @return string|null The handler's message, or null when the link is not usable (always the same outcome).
	 */
	public function use_link( string $token ): ?string {
		$row = $this->peek( $token );
		if ( null === $row || ! isset( $this->handlers[ (string) $row['purpose'] ] ) ) {
			return null;
		}
		if ( ! $this->store->consume( self::hash( $token ), gmdate( 'Y-m-d H:i:s', (int) ( $this->clock )() ) ) ) {
			return null;
		}
		return (string) ( $this->handlers[ (string) $row['purpose'] ] )( $row );
	}

	/**
	 * Revoke unused links for a record, for example before reissuing.
	 *
	 * @param string $purpose      Purpose.
	 * @param string $subject_type Record type.
	 * @param string $subject_id   Record id.
	 */
	public function revoke_for( string $purpose, string $subject_type, string $subject_id ): int {
		return $this->store->revoke_for( $purpose, $subject_type, $subject_id, gmdate( 'Y-m-d H:i:s', (int) ( $this->clock )() ) );
	}

	/**
	 * Revoke and issue again.
	 *
	 * @param string      $purpose      Purpose.
	 * @param string      $subject_type Record type.
	 * @param string      $subject_id   Record id.
	 * @param string|null $email        Address.
	 * @param int         $ttl          Lifetime.
	 */
	public function reissue( string $purpose, string $subject_type, string $subject_id, ?string $email = null, int $ttl = self::DEFAULT_TTL ): string {
		$this->revoke_for( $purpose, $subject_type, $subject_id );
		return $this->issue( $purpose, $subject_type, $subject_id, $email, $ttl );
	}

	/**
	 * Retention job: delete spent links older than a number of days.
	 *
	 * @param int $days Days.
	 */
	public function purge_spent( int $days = 30 ): int {
		return $this->store->purge_spent( gmdate( 'Y-m-d H:i:s', (int) ( $this->clock )() - $days * 86400 ) );
	}

	/**
	 * Hash of a token, the only form that is stored.
	 *
	 * @param string $token Token.
	 */
	public static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}
}
