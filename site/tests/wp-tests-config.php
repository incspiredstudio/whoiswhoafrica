<?php
/**
 * Config for the WordPress test suite. Holds no secrets: every value comes from the environment.
 * Set WIW_TEST_DB_NAME, WIW_TEST_DB_USER, WIW_TEST_DB_PASSWORD and WIW_TEST_DB_HOST for a throwaway test database.
 * The database is emptied by the suite, so never point it at a real site.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/wordpress/' );
define( 'WP_CONTENT_DIR', dirname( __DIR__ ) . '/wp-content' );
define( 'WP_CONTENT_URL', 'http://example.org/wp-content' );
define( 'WP_DEFAULT_THEME', 'whoiswho' );
define( 'WP_DEBUG', true );

define( 'DB_NAME', (string) getenv( 'WIW_TEST_DB_NAME' ) );
define( 'DB_USER', (string) getenv( 'WIW_TEST_DB_USER' ) );
define( 'DB_PASSWORD', (string) getenv( 'WIW_TEST_DB_PASSWORD' ) );
define( 'DB_HOST', getenv( 'WIW_TEST_DB_HOST' ) ? (string) getenv( 'WIW_TEST_DB_HOST' ) : '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY', 'test' );
define( 'SECURE_AUTH_KEY', 'test' );
define( 'LOGGED_IN_KEY', 'test' );
define( 'NONCE_KEY', 'test' );
define( 'AUTH_SALT', 'test' );
define( 'SECURE_AUTH_SALT', 'test' );
define( 'LOGGED_IN_SALT', 'test' );
define( 'NONCE_SALT', 'test' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals,WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'WHOISWHO tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
