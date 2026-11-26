<?php
/**
 * Functions that themes and plugins may call.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_csp_nonce' ) ) {
	/**
	 * The Content-Security-Policy nonce for this request. Put it on every inline script you print yourself: <script nonce="...">.
	 * It marks the script as trusted; the finished page replaces it with the script's hash in the header (issue 719).
	 */
	function wiw_csp_nonce(): string {
		return \WhoIsWho\Guard\Headers\Headers::nonce();
	}
}
