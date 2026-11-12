<?php
/**
 * A direct profile edit that was refused; the message is fit to show to staff (REQ-125, REQ-139, REQ-244).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Edit;

/**
 * Refused edit.
 */
final class EditRefused extends \RuntimeException {

	/**
	 * Build a refusal.
	 *
	 * @param string $message Message for staff; never contains personal data.
	 */
	public static function because( string $message ): self {
		return new self( $message );
	}
}
