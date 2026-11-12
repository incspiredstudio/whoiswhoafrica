<?php
/**
 * Cookie consent support (REQ-248, REQ-062, REQ-058): the choice lives in one first-party cookie, "wiw_consent", set by the
 * cookie notice script. Essential cookies are always allowed; a non-essential script (analytics) is never printed by the server
 * and loads in the browser only after Accept, from the address a filter provides. Pages may be cached at the edge, so the page
 * never differs by visitor: the notice markup is always present and the script decides whether to show it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

const WIW_CONSENT_COOKIE = 'wiw_consent';

if ( ! function_exists( 'wiw_consent_choice' ) ) {
	/**
	 * The visitor's choice from the cookie: "all", "essential" or "" when none was made.
	 */
	function wiw_consent_choice(): string {
		$value = isset( $_COOKIE[ WIW_CONSENT_COOKIE ] ) ? sanitize_key( wp_unslash( (string) $_COOKIE[ WIW_CONSENT_COOKIE ] ) ) : '';
		return in_array( $value, array( 'all', 'essential' ), true ) ? $value : '';
	}
}

if ( ! function_exists( 'wiw_consent_accepted' ) ) {
	/**
	 * Whether a purpose was accepted. Only "essential" is true without a choice; analytics needs Accept.
	 *
	 * @param string $purpose essential or analytics.
	 */
	function wiw_consent_accepted( string $purpose ): bool {
		if ( 'essential' === $purpose ) {
			return true;
		}
		return 'all' === wiw_consent_choice();
	}
}

if ( ! function_exists( 'wiw_analytics_url' ) ) {
	/**
	 * The address of the analytics script, or an empty string when none is configured (the default). The script is loaded by the
	 * browser only after Accept; the server never prints it.
	 */
	function wiw_analytics_url(): string {
		$url = apply_filters( 'wiw_analytics_url', '' );
		return is_string( $url ) ? esc_url_raw( $url, array( 'https' ) ) : '';
	}
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! wp_script_is( 'wiw-main', 'enqueued' ) ) {
			return;
		}
		wp_add_inline_script( 'wiw-main', 'window.wiwConsent=' . wp_json_encode( array( 'analytics' => wiw_analytics_url() ) ) . ';', 'before' );
	},
	40
);

add_action(
	'wp_footer',
	static function (): void {
		get_template_part( 'template-parts/cookie-notice' );
	},
	5
);
