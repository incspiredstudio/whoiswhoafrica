<?php
/**
 * Plugin Name: WHOISWHO Guard
 * Description: Must-use safeguard. Keeps unreviewed records out of public output. Owns no data, tables or screens, and needs no other plugin.
 * Version:     0.1.0
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WIW_GUARD_VERSION', '0.1.0' );
define( 'WIW_GUARD_DIR', __DIR__ . '/whoiswho-guard/' );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'WhoIsWho\\Guard\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$path = WIW_GUARD_DIR . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

require_once WIW_GUARD_DIR . 'functions.php';

\WhoIsWho\Guard\Guard::init();
