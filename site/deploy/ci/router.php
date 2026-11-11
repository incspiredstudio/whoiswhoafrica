<?php
/**
 * Router for PHP's built-in server in CI and local runs. Maps the public URL layout to the Composer layout:
 * /wp-content/* to site/wp-content, /wp-admin, /wp-includes and /wp-login.php to site/wordpress, everything else to index.php.
 * The live host does the same with its own web server rules. Not used in production.
 *
 * @package WhoIsWho
 */

$wiw_site = dirname( __DIR__, 2 );
$wiw_path = rawurldecode( (string) wp_parse_url_path( $_SERVER['REQUEST_URI'] ?? '/' ) );

/**
 * Path part of a URL.
 *
 * @param string $uri Request URI.
 */
function wp_parse_url_path( string $uri ): string {
	$path = parse_url( $uri, PHP_URL_PATH );
	return is_string( $path ) ? $path : '/';
}

if ( false !== strpos( $wiw_path, '..' ) ) {
	http_response_code( 400 );
	return true;
}

$wiw_map = array(
	'#^/wp-content/#'               => $wiw_site,
	'#^/(wp-admin|wp-includes)/#'   => $wiw_site . '/wordpress',
	'#^/(wp-login|wp-cron)\.php$#'  => $wiw_site . '/wordpress',
);
foreach ( $wiw_map as $wiw_pattern => $wiw_root ) {
	if ( preg_match( $wiw_pattern, $wiw_path ) ) {
		$wiw_file = $wiw_root . $wiw_path;
		if ( is_file( $wiw_file ) ) {
			if ( 'php' === pathinfo( $wiw_file, PATHINFO_EXTENSION ) ) {
				chdir( dirname( $wiw_file ) );
				$_SERVER['SCRIPT_NAME']     = $wiw_path;
				$_SERVER['SCRIPT_FILENAME'] = $wiw_file;
				require $wiw_file;
				return true;
			}
			header( 'Content-Type: ' . ( 'css' === pathinfo( $wiw_file, PATHINFO_EXTENSION ) ? 'text/css' : ( 'js' === pathinfo( $wiw_file, PATHINFO_EXTENSION ) ? 'application/javascript' : mime_content_type( $wiw_file ) ) ) );
			readfile( $wiw_file );
			return true;
		}
	}
}

$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $wiw_site . '/index.php';
chdir( $wiw_site );
require $wiw_site . '/index.php';
return true;
