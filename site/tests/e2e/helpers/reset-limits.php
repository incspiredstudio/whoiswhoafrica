<?php
/**
 * Clears the form rate-limit counters (the wiw_rl_ transients) so repeated browser runs are not throttled. TEST ENVIRONMENT
 * ONLY: refuses unless the site is local and WIW_E2E_TEST_MODE=1. Prints one line of JSON with the number of counters cleared.
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.NamingConventions.PrefixAllGlobals, WordPress.DB

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() || '1' !== getenv( 'WIW_E2E_TEST_MODE' ) ) {
	fwrite( STDERR, "refused: needs a local environment and WIW_E2E_TEST_MODE=1\n" );
	exit( 1 );
}

global $wpdb;
$wiw_cleared = (int) $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wiw\_rl\_%' OR option_name LIKE '\_transient\_timeout\_wiw\_rl\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed pattern, no input.
wp_cache_flush();
echo wp_json_encode( array( 'cleared' => $wiw_cleared ) ) . "\n";
