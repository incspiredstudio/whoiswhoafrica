<?php
/**
 * Prints the ids of the pages at the given paths as one line of JSON (for example: php page-ids.php about faq awards/rules).
 * Read only. TEST ENVIRONMENT ONLY: refuses unless the site is local and WIW_E2E_TEST_MODE=1. A path with no page is null.
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

$wiw_ids = array();
foreach ( array_slice( $argv, 1 ) as $wiw_path ) {
	$wiw_page             = get_page_by_path( $wiw_path, OBJECT, 'page' );
	$wiw_ids[ $wiw_path ] = $wiw_page instanceof WP_Post ? $wiw_page->ID : null;
}
echo wp_json_encode( $wiw_ids ) . "\n";
