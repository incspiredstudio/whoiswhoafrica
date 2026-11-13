<?php
/**
 * Refuses card-like numbers in free-text payment fields (REQ-179, D-005). The site never takes card data; the payment reference
 * is text an Administrator copies from an offline transfer. A run of 13 to 19 digits, with spaces or dashes between them, is
 * treated as a card number and refused.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

/**
 * Card number guard.
 */
final class CardGuard {

	/**
	 * Whether the text holds a card-like digit run.
	 *
	 * @param string $text Free text.
	 */
	public static function looks_like_card( string $text ): bool {
		return 1 === preg_match( '/(?<![0-9])(?:[0-9][ -]?){12,18}[0-9](?![0-9])/', $text );
	}
}
