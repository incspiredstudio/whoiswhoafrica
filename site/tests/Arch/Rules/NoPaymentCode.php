<?php
/**
 * No payment processing, card data or gateway code anywhere (D-005, REQ-046). Customers pay offline and staff record the
 * payment in the commercial register. Two checks: no gateway string in code, and no payment package in composer.lock.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * No gateway code.
 */
final class NoPaymentCode implements Rule {

	/**
	 * Gateway and processor names, matched in code strings and names.
	 */
	public const GATEWAYS = '/(stripe|paystack|flutterwave|paypal|braintree|razorpay|adyen|checkout\.com|\bsquareup\b|mollie|interswitch|cardinal ?commerce)/i';

	/**
	 * Package names that must not appear in composer.lock.
	 */
	public const PACKAGES = '/(stripe|paystack|flutterwave|paypal|braintree|razorpay|adyen|mollie|woocommerce\/woocommerce|wpackagist-plugin\/woocommerce|easy-digital-downloads|wpackagist-plugin\/give)/i';

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			foreach ( array_merge( $sources->strings( $path ), $sources->names( $path ) ) as $text ) {
				if ( 1 === preg_match( self::GATEWAYS, $text ) ) {
					$bad[] = "$path names a payment gateway";
					break;
				}
			}
		}
		return array_values( array_unique( $bad ) );
	}

	/**
	 * Payment packages in a composer.lock text.
	 *
	 * @param string $lock composer.lock contents.
	 * @return string[]
	 */
	public function lock_violations( string $lock ): array {
		$data = json_decode( $lock, true );
		$bad  = array();
		foreach ( array_merge( (array) ( $data['packages'] ?? array() ), (array) ( $data['packages-dev'] ?? array() ) ) as $package ) {
			$name = (string) ( $package['name'] ?? '' );
			if ( 1 === preg_match( self::PACKAGES, $name ) ) {
				$bad[] = "composer.lock holds the payment package $name";
			}
		}
		return $bad;
	}
}
