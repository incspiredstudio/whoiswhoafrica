<?php
/**
 * Test bootstrap. Unit, Arch and Security tests run without WordPress. Integration and Visibility tests load the WordPress
 * test suite from WP_TESTS_DIR (set it to a checkout of wordpress-develop/tests/phpunit, with a MariaDB test database);
 * without it they are skipped, so empty or WordPress-less runs never fail the gate. This file reads no .env* file.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$wiw_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( $wiw_tests_dir && is_readable( $wiw_tests_dir . '/includes/functions.php' ) ) {
	define( 'WIW_WP_TESTS', true );
	require_once $wiw_tests_dir . '/includes/functions.php';
	tests_add_filter(
		'muplugins_loaded',
		static function (): void {
			$root = dirname( __DIR__ ) . '/wp-content';
			foreach ( glob( $root . '/mu-plugins/whoiswho-guard*.php' ) ?: array() as $file ) {
				require_once $file;
			}
			if ( is_readable( $root . '/plugins/whoiswho-core/whoiswho-core.php' ) ) {
				require_once $root . '/plugins/whoiswho-core/whoiswho-core.php';
			}
		}
	);
	require $wiw_tests_dir . '/includes/bootstrap.php';
} else {
	define( 'WIW_WP_TESTS', false );
}
