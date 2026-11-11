<?php
/**
 * WHOISWHO wp-config template (REQ-046, REQ-204).
 *
 * Holds no secrets. Every secret comes from a secrets file kept outside the web root.
 * Path: the WIW_SECRETS_FILE server variable, or ../wiw-secrets.env next to the site folder for local work.
 * The file is plain NAME=value lines, mode 600, created by the owner on the host. See .env.example for the names.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

$wiw_secrets_path = getenv( 'WIW_SECRETS_FILE' ) ? getenv( 'WIW_SECRETS_FILE' ) : dirname( __DIR__ ) . '/wiw-secrets.env';
$wiw_secrets      = is_readable( $wiw_secrets_path ) ? parse_ini_file( $wiw_secrets_path, false, INI_SCANNER_RAW ) : false;

if ( ! is_array( $wiw_secrets ) ) {
	// Fail closed with a plain message: no path, no credential detail.
	http_response_code( 503 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( "The site is not configured yet.\n" );
}

/**
 * Read one value from the secrets file.
 *
 * @param string $name    Secret name.
 * @param string $default Fallback when the name is absent.
 * @return string
 */
function wiw_secret( string $name, string $default = '' ): string {
	global $wiw_secrets;
	return isset( $wiw_secrets[ $name ] ) ? trim( (string) $wiw_secrets[ $name ], "\"' " ) : $default;
}

define( 'DB_NAME', wiw_secret( 'DB_NAME' ) );
define( 'DB_USER', wiw_secret( 'DB_USER' ) );
define( 'DB_PASSWORD', wiw_secret( 'DB_PASSWORD' ) );
define( 'DB_HOST', wiw_secret( 'DB_HOST', 'localhost' ) );
define( 'WIW_FORM_TOKEN_KEY', wiw_secret( 'FORM_TOKEN_KEY' ) );
define( 'WIW_SIGNED_LINK_KEY', wiw_secret( 'SIGNED_LINK_KEY' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $wiw_salt ) {
	define( $wiw_salt, wiw_secret( $wiw_salt ) );
}

$table_prefix = 'wiw_'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound

$wiw_production = 'production' === wiw_secret( 'WIW_ENV', 'production' );

define( 'WP_ENVIRONMENT_TYPE', $wiw_production ? 'production' : 'local' );
define( 'WP_DEBUG', ! $wiw_production );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', ! $wiw_production );
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_FILE_MODS', $wiw_production );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'FORCE_SSL_ADMIN', $wiw_production );
define( 'DISABLE_WP_CRON', 'true' === wiw_secret( 'WIW_DISABLE_WP_CRON', 'false' ) );

// Core lives in wordpress/ (installed by Composer); our code lives in wp-content/ next to it.
$wiw_site_url = rtrim( wiw_secret( 'WIW_SITE_URL' ), '/' );
if ( '' !== $wiw_site_url ) {
	define( 'WP_HOME', $wiw_site_url );
	define( 'WP_SITEURL', $wiw_site_url . '/wordpress' );
	define( 'WP_CONTENT_URL', $wiw_site_url . '/wp-content' );
}
define( 'WP_CONTENT_DIR', __DIR__ . '/wp-content' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wordpress/' );
}

require_once ABSPATH . 'wp-settings.php';
