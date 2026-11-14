<?php
/**
 * Sets or clears the "last email could not be sent" flag (issue 829, REQ-075). `php mail-error.php set` records a failed send
 * three hours ago; `clear` removes it. TEST ENVIRONMENT ONLY: refuses unless the site is local and WIW_E2E_TEST_MODE=1. Prints one
 * line of JSON with the flag afterwards.
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.NamingConventions.PrefixAllGlobals

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() || '1' !== getenv( 'WIW_E2E_TEST_MODE' ) ) {
	fwrite( STDERR, "refused: needs a local environment and WIW_E2E_TEST_MODE=1\n" );
	exit( 1 );
}

use WhoIsWho\Core\Platform\Mail\Transport;

if ( 'set' === ( $argv[1] ?? '' ) ) {
	update_option( Transport::OPTION_ERROR, 'send_failed', false );
	update_option( Transport::OPTION_ERROR_AT, time() - 3 * HOUR_IN_SECONDS, false );
} else {
	Transport::clear_error();
}
wp_cache_flush();
echo wp_json_encode( array( 'flag' => get_option( Transport::OPTION_ERROR ) ) ) . "\n";
