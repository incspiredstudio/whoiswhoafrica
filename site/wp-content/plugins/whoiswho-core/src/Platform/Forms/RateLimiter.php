<?php
/**
 * Sliding-window rate limiter keyed by hashed IP and by hashed email or link token. The store only ever sees keyed hashes,
 * so no raw IP or email is kept. Limits per form type are set in code (see LIMITS), not in settings.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Rate limiter.
 */
final class RateLimiter {

	/**
	 * Limits per form type: [ max attempts, window in seconds ] for the IP and for the subject (email or token).
	 * Form issues in M1 and M3 add their own entries.
	 */
	public const LIMITS = array(
		'default' => array(
			'ip'      => array( 10, 3600 ),
			'subject' => array( 3, 3600 ),
		),
	);

	/**
	 * Store.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Secret used to hash identifiers.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Clock, for tests.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Limits.
	 *
	 * @var array<string, array<string, array{0: int, 1: int}>>
	 */
	private array $limits;

	/**
	 * Constructor.
	 *
	 * @param Store                                                    $store  Store.
	 * @param string                                                   $key    Secret for hashing.
	 * @param array<string, array<string, array{0: int, 1: int}>>|null $limits Limits; defaults to LIMITS.
	 * @param callable|null                                            $clock  Returns the current Unix time.
	 */
	public function __construct( Store $store, string $key, ?array $limits = null, ?callable $clock = null ) {
		$this->store  = $store;
		$this->key    = $key;
		$this->limits = $limits ?? self::LIMITS;
		$this->clock  = $clock ?? static fn (): int => time();
	}

	/**
	 * Record an attempt and say whether it is allowed. Blocked attempts are recorded too, so a bot that keeps trying stays blocked.
	 *
	 * @param string      $form    Form type, for example "claim".
	 * @param string      $ip      Visitor IP address.
	 * @param string|null $subject Email address or link token the attempt is about, if any.
	 */
	public function allow( string $form, string $ip, ?string $subject = null ): bool {
		$limits  = $this->limits[ $form ] ?? $this->limits['default'];
		$allowed = $this->hit( $form, 'ip', $ip, $limits['ip'] );
		if ( null !== $subject && '' !== $subject ) {
			$allowed = $this->hit( $form, 'subject', strtolower( trim( $subject ) ), $limits['subject'] ) && $allowed;
		}
		return $allowed;
	}

	/**
	 * Count one attempt against one limit.
	 *
	 * @param string             $form   Form type.
	 * @param string             $kind   ip or subject.
	 * @param string             $value  Raw identifier (hashed here, never stored).
	 * @param array{0:int,1:int} $limit  Max attempts and window.
	 */
	private function hit( string $form, string $kind, string $value, array $limit ): bool {
		list( $max, $window ) = $limit;
		$now                  = (int) ( $this->clock )();
		$key                  = hash_hmac( 'sha256', $form . '|' . $kind . '|' . $value, $this->key );
		$times                = array_values(
			array_filter(
				$this->store->get( $key ),
				static fn ( int $t ): bool => $t > $now - $window
			)
		);
		$allowed              = count( $times ) < $max;
		$times[]              = $now;
		$this->store->set( $key, $times, $window );
		return $allowed;
	}
}
