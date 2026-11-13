<?php
/**
 * The default consent checker: nobody has opted in (REQ-061).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail\Marketing;

/**
 * Deny-all consent.
 */
final class DenyAllConsent implements ConsentChecker {

	/**
	 * Always false.
	 *
	 * @param string $address Recipient address.
	 */
	public function has_opted_in( string $address ): bool {
		unset( $address );
		return false;
	}
}
