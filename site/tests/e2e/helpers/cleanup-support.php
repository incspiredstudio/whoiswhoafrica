<?php
/**
 * Removes the support items (corrections, removals, reports, enquiries and data requests) and the suppression entries that the
 * browser tests created. TEST ENVIRONMENT ONLY: refuses unless the site is local and WIW_E2E_TEST_MODE=1. Only rows whose
 * requester address ends in @example.invalid are touched. Prints one line of JSON with the number of items removed.
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

use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Compliance\Suppression\Suppression;

global $wpdb;
$wiw_items = SupportItems::table();
$wiw_ids   = $wpdb->get_col( "SELECT id FROM {$wiw_items} WHERE requester_email LIKE '%@example.invalid'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
foreach ( $wiw_ids as $wiw_id ) {
	$wpdb->delete( Suppression::table(), array( 'support_item_id' => (int) $wiw_id ) );
	$wpdb->delete( $wiw_items, array( 'id' => (int) $wiw_id ) );
}
echo wp_json_encode( array( 'removed' => count( $wiw_ids ) ) ) . "\n";
