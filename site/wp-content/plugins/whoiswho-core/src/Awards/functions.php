<?php
/**
 * The theme-facing helper for the one feature flag (REQ-259): templates ask this function and never read the option.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_nominations_open' ) ) {
	/**
	 * Whether the public nomination form is open. Closed on a clean install, and closed if the settings service cannot answer.
	 */
	function wiw_nominations_open(): bool {
		try {
			return \WhoIsWho\Core\Platform\Settings\SettingsService::for_site()->nominations_enabled();
		} catch ( \Throwable $e ) {
			unset( $e );
			return false;
		}
	}
}
