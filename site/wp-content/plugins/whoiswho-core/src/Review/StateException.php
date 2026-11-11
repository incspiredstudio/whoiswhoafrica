<?php
/**
 * A state change the machine refused.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review;

/**
 * Refusal.
 */
final class StateException extends \RuntimeException {

	/**
	 * Build a refusal with a fixed message.
	 *
	 * @param string $message Plain message, never personal data.
	 */
	public static function refuse( string $message ): self {
		return new self( $message );
	}
}
