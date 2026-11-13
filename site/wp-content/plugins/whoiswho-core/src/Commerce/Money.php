<?php
/**
 * An amount of money as integer minor units (kobo for NGN) plus a currency code (REQ-112). Never a float.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

/**
 * Money value object.
 */
final class Money {

	public const DEFAULT_CURRENCY = 'NGN';

	/**
	 * Constructor.
	 *
	 * @param int    $minor    Amount in minor units, zero or more.
	 * @param string $currency Three upper-case letters.
	 * @throws \InvalidArgumentException When the amount is negative or the currency is not three letters.
	 */
	public function __construct( private int $minor, private string $currency = self::DEFAULT_CURRENCY ) {
		if ( $minor < 0 ) {
			throw new \InvalidArgumentException( 'An amount cannot be negative.' );
		}
		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			throw new \InvalidArgumentException( 'A currency is a three-letter code.' );
		}
	}

	/**
	 * Amount in minor units.
	 */
	public function minor(): int {
		return $this->minor;
	}

	/**
	 * Currency code.
	 */
	public function currency(): string {
		return $this->currency;
	}

	/**
	 * Whether this is the same amount in the same currency.
	 *
	 * @param Money $other Other.
	 */
	public function equals( Money $other ): bool {
		return $this->minor === $other->minor && $this->currency === $other->currency;
	}
}
