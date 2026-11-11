<?php
/**
 * Functions that themes and plugins may call.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_csp_nonce' ) ) {
	/**
	 * The Content-Security-Policy nonce for this request. Put it on every inline script: <script nonce="...">.
	 */
	function wiw_csp_nonce(): string {
		return \WhoIsWho\Guard\Headers\Headers::nonce();
	}
}
