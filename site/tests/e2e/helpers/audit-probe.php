<?php
/**
 * Counts audit log rows of one action, for the browser specs that check a staff action was recorded. TEST ENVIRONMENT ONLY: it
 * refuses unless the site is a local environment. It prints a number and the ids of the actors, never the content of a row.
 *
 * Usage: WIW_SECRETS_FILE=... php tests/e2e/helpers/audit-probe.php <action>      (for example export.download)
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.PrefixAllGlobals

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() ) {
	fwrite( STDERR, "probe refused: not a local environment\n" );
	exit( 1 );
}

$wiw_action = isset( $argv[1] ) ? (string) $argv[1] : '';
if ( 1 !== preg_match( '/^[a-z_.]{3,60}$/', $wiw_action ) ) {
	fwrite( STDERR, "probe refused: give an action such as export.download\n" );
	exit( 1 );
}

global $wpdb;
$wiw_rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT actor_id FROM ' . $wpdb->prefix . 'wiw_audit_log WHERE action = %s', $wiw_action ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
echo wp_json_encode(
	array(
		'count'  => count( $wiw_rows ),
		'actors' => array_map( static fn ( array $r ): int => (int) $r['actor_id'], $wiw_rows ),
	)
) . "\n";
