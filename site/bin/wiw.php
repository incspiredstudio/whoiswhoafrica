<?php
/**
 * Command line entry: php bin/wiw.php <command>. Refuses to run over HTTP. Loads WordPress, then hands over to the plugin's
 * command registry. Print identifiers only; never personal data.
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.Security.EscapeOutput,WordPress.NamingConventions.PrefixAllGlobals -- a command line script: STDERR output and WordPress's own constant.

if ( ! in_array( PHP_SAPI, array( 'cli', 'phpdbg' ), true ) ) {
	http_response_code( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( "Forbidden\n" );
}

$wiw_wp_load = dirname( __DIR__ ) . '/wordpress/wp-load.php';
if ( ! is_readable( $wiw_wp_load ) ) {
	fwrite( STDERR, "WordPress was not found. Run composer install first.\n" );
	exit( 1 );
}

// Make the request look like a plain command line run, so WordPress does not try to redirect or output pages.
$_SERVER['HTTP_HOST']   = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';
define( 'WP_USE_THEMES', false );

require $wiw_wp_load;

if ( ! class_exists( '\WhoIsWho\Core\Platform\Cli\Application' ) ) {
	fwrite( STDERR, "The whoiswho-core plugin is not active.\n" );
	exit( 1 );
}

exit( \WhoIsWho\Core\Platform\Cli\Application::for_site()->run( $GLOBALS['argv'], new \WhoIsWho\Core\Platform\Cli\Output() ) );
