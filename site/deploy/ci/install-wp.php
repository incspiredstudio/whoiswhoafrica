<?php
/**
 * Installs WordPress into an empty database for CI and local browser tests, using the template wp-config and the secrets file
 * named by WIW_SECRETS_FILE. Creates a staff user from E2E_USER and E2E_PASS if set. Test environments only.
 *
 * @package WhoIsWho
 */

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

define( 'WP_INSTALLING', true );
require dirname( __DIR__, 2 ) . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if ( ! is_blog_installed() ) {
	wp_install( 'WHOISWHO test', getenv( 'E2E_USER' ) ? getenv( 'E2E_USER' ) : 'admin', 'admin@example.org', true, '', getenv( 'E2E_PASS' ) ? getenv( 'E2E_PASS' ) : wp_generate_password( 20 ), 'en_US' );
}
$wiw_theme = getenv( 'WIW_THEME' ) ? getenv( 'WIW_THEME' ) : 'whoiswho-child';
if ( ! is_readable( WP_CONTENT_DIR . "/themes/{$wiw_theme}/style.css" ) ) {
	$wiw_theme = 'whoiswho';
}
switch_theme( $wiw_theme );

// Activate the core plugin so pages that need it (profiles, link pages, legal pages) render. The guard is a must-use plugin and loads by itself.
$wiw_plugin = 'whoiswho-core/whoiswho-core.php';
if ( is_readable( WP_PLUGIN_DIR . '/' . $wiw_plugin ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( ! is_plugin_active( $wiw_plugin ) ) {
		$wiw_result = activate_plugin( $wiw_plugin );
		if ( is_wp_error( $wiw_result ) ) {
			fwrite( STDERR, 'could not activate ' . $wiw_plugin . ': ' . $wiw_result->get_error_message() . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			exit( 1 );
		}
	}
}
// Pretty permalinks, so the plugin's /profile/{slug} route and the page addresses work in the browser tests.
update_option( 'permalink_structure', '/%postname%/' );
delete_option( 'wiw_rewrite_version' );

// Create the plugin's tables (forward-only migrations) through the same command the deploy script uses.
if ( is_plugin_active( $wiw_plugin ) ) {
	$wiw_status = 0;
	passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/bin/wiw.php' ) . ' migrate', $wiw_status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	if ( 0 !== $wiw_status ) {
		fwrite( STDERR, "migrations failed\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit( 1 );
	}
}
echo 'installed, theme ' . $wiw_theme . "\n";
