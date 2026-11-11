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
echo 'installed, theme ' . $wiw_theme . "\n";
