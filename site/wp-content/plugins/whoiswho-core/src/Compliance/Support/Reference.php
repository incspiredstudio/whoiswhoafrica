<?php
/**
 * The reference a requester sees on the received page and in email, for example WIW-7K3Q9XPD. No look-alike letters.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

/**
 * Reference generator.
 */
final class Reference {

	private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	/**
	 * A new random reference.
	 */
	public static function generate(): string {
		$out = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$out .= self::ALPHABET[ random_int( 0, strlen( self::ALPHABET ) - 1 ) ];
		}
		return 'WIW-' . $out;
	}

	/**
	 * Whether a string has the shape of a reference.
	 *
	 * @param string $value Value.
	 */
	public static function is_valid( string $value ): bool {
		return 1 === preg_match( '/^WIW-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}$/', $value );
	}
}
