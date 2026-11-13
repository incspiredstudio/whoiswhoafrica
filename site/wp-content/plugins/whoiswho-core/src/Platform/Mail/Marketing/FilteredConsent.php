<?php
/**
 * The consent checker the site uses: it asks the wiw_marketing_consent_checker filter at send time, so modules loaded after the
 * platform can supply the real checker (REQ-061). Deny-all when nothing does.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail\Marketing;

/**
 * Filtered consent.
 */
final class FilteredConsent implements ConsentChecker {

	/**
	 * Whether the address has opted in, according to the checker the filter returns.
	 *
	 * @param string $address Recipient address.
	 */
	public function has_opted_in( string $address ): bool {
		$checker = apply_filters( 'wiw_marketing_consent_checker', new DenyAllConsent() );
		return $checker instanceof ConsentChecker && $checker->has_opted_in( $address );
	}
}
