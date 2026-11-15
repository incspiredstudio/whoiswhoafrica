<?php
/**
 * Seeds the staff UI phase 2 browser tests for the Platform and Import screens (tests/e2e/staff-ui-platform-import.spec.ts,
 * REQ-275): two applied import batches with no records (so Undo finishes at once) and one email log row. Every call starts them
 * afresh. TEST ENVIRONMENT ONLY: it refuses unless the site is local and WIW_E2E_TEST_MODE=1. The first argument is a suffix that
 * keeps parallel projects apart; "remove" as the second argument deletes them (audit rows stay: the log is append-only). No
 * personal data: the batch ids are made up and the email row's recipient reference is a fixed made-up hash. Prints one line of
 * JSON.
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.PrefixAllGlobals, WordPress.DB

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() || '1' !== getenv( 'WIW_E2E_TEST_MODE' ) ) {
	fwrite( STDERR, "seed refused: needs a local environment and WIW_E2E_TEST_MODE=1\n" );
	exit( 1 );
}

use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Platform\Mail\Admin\EmailLogScreen;
use WhoIsWho\Core\Platform\Mail\WpdbEmailLog;

$wiw_suffix  = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $argv[1] ?? 'x' ) ) );
$wiw_batches = array( 'e2e-p2-alpha-' . $wiw_suffix, 'e2e-p2-beta-' . $wiw_suffix );
$wiw_message = 'e2e-p2-msg-' . $wiw_suffix;

global $wpdb;
foreach ( $wiw_batches as $wiw_batch ) {
	$wpdb->delete( BatchRepository::table(), array( 'batch_id' => $wiw_batch ) );
}
$wpdb->delete( EmailLogScreen::table(), array( 'message_id' => $wiw_message ) );
if ( isset( $argv[2] ) && 'remove' === $argv[2] ) {
	echo "{}\n";
	exit( 0 );
}

foreach ( $wiw_batches as $wiw_batch ) {
	$wpdb->insert(
		BatchRepository::table(),
		array(
			'batch_id'          => $wiw_batch,
			'run_id'            => 'e2e-run',
			'contract_version'  => '1.0',
			'file_hash'         => 'sha256:e2e',
			'record_count'      => 0,
			'status'            => 'applied',
			'progress_cursor'   => 0,
			'created_by'        => 1,
			'created_at'        => gmdate( 'Y-m-d H:i:s' ),
			'updated_at'        => gmdate( 'Y-m-d H:i:s' ),
			'settings_snapshot' => '{}',
		)
	);
}
( new WpdbEmailLog() )->add( $wiw_message, 'e2e.staff_ui', str_repeat( 'e', 64 ), 'sent', 'failed' );

echo wp_json_encode( array( 'batches' => $wiw_batches ) ) . "\n";
