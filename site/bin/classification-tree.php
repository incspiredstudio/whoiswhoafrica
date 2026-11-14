<?php
/**
 * Writes docs/contracts/classification-tree.json from the plugin's config/classification.php (REQ-009). Run after changing the
 * tree: php site/bin/classification-tree.php. A unit test fails when the file and the config differ.
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.Security.EscapeOutput -- a command line script without WordPress.

if ( ! in_array( PHP_SAPI, array( 'cli', 'phpdbg' ), true ) ) {
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$wiw_target = dirname( __DIR__, 2 ) . '/docs/contracts/classification-tree.json';
file_put_contents( $wiw_target, \WhoIsWho\Core\Directory\Classification::tree_json() );
fwrite( STDOUT, "Wrote docs/contracts/classification-tree.json\n" );
