<?php
/**
 * Answers one question for the marketing-mail gate: has this address opted in to marketing messages (REQ-061)? The consent store
 * arrives with privacy (M3) and newsletter (M2); until then the default implementation denies everyone, so no marketing message
 * is sent by accident.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail\Marketing;

/**
 * Consent checker.
 */
interface ConsentChecker {

	/**
	 * Whether the address has opted in to marketing messages.
	 *
	 * @param string $address Recipient address.
	 */
	public function has_opted_in( string $address ): bool;
}
