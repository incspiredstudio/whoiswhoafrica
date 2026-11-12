<?php
/**
 * The helper the dashboard sprint card asks (REQ-272): templates and cards never read the option themselves.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'sprint_tracking_on' ) ) {
	/**
	 * Whether Sprint tracking is on (default on).
	 */
	function sprint_tracking_on(): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- the name is fixed by the issue (M0-PLG-13) and the M3 dashboard card.
		try {
			return \WhoIsWho\Core\Platform\Settings\SettingsService::for_site()->sprint_tracking_enabled();
		} catch ( \Throwable $e ) {
			unset( $e );
			return true;
		}
	}
}
