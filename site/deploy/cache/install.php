<?php
/**
 * Installs the page-cache files on a host (M5-PRF-03; REQ-041, REQ-054). Run once per release by the deploy, from the site root:
 *
 *   php deploy/cache/install.php [--site DIR]
 *
 * It copies the plugin's advanced-cache.php drop-in to wp-content/advanced-cache.php, copies deploy/cache/wp-cache-config.php to
 * wp-content/wp-cache-config.php, and creates wp-content/cache/. It only runs when the secrets file turns the cache on
 * (WIW_PAGE_CACHE=true, which sets WP_CACHE in wp-config.php); the caller decides that. It reads no secret and touches no database.
 * Running it twice changes nothing. Exit codes: 0 done, 1 a file is missing or could not be written, 2 bad usage.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$wiw_site = dirname( __DIR__, 2 );
foreach ( array_slice( $argv, 1 ) as $i => $wiw_arg ) {
	if ( '--site' === $wiw_arg && isset( $argv[ $i + 2 ] ) ) {
		$wiw_site = rtrim( $argv[ $i + 2 ], '/\\' );
	}
}

$wiw_plugin   = $wiw_site . '/wp-content/plugins/wp-super-cache';
$wiw_content  = $wiw_site . '/wp-content';
$wiw_template = __DIR__ . '/wp-cache-config.php';

foreach ( array( $wiw_plugin . '/advanced-cache.php', $wiw_template ) as $wiw_needed ) {
	if ( ! is_readable( $wiw_needed ) ) {
		fwrite( STDERR, 'Missing: ' . $wiw_needed . "\n" );
		exit( 1 );
	}
}

$wiw_cache_dir = $wiw_content . '/cache';
if ( ! is_dir( $wiw_cache_dir ) && ! mkdir( $wiw_cache_dir, 0755, true ) ) {
	fwrite( STDERR, 'Could not create ' . $wiw_cache_dir . "\n" );
	exit( 1 );
}

$wiw_copies = array(
	$wiw_plugin . '/advanced-cache.php' => $wiw_content . '/advanced-cache.php',
	$wiw_template                        => $wiw_content . '/wp-cache-config.php',
);
foreach ( $wiw_copies as $wiw_from => $wiw_to ) {
	if ( is_readable( $wiw_to ) && md5_file( $wiw_from ) === md5_file( $wiw_to ) ) {
		echo 'unchanged ' . basename( $wiw_to ) . "\n";
		continue;
	}
	if ( ! copy( $wiw_from, $wiw_to ) ) {
		fwrite( STDERR, 'Could not write ' . $wiw_to . "\n" );
		exit( 1 );
	}
	echo 'wrote ' . basename( $wiw_to ) . "\n";
}
echo "page cache files in place\n";
