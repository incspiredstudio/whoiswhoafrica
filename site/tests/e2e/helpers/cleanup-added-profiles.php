<?php
/**
 * Removes the fictional profiles the Add profile browser test created (titles starting "E2E Addp "), with their state,
 * provenance and duplicate rows, and the pictures the Edit profile browser test uploaded. TEST ENVIRONMENT ONLY: refuses unless the site is local and WIW_E2E_TEST_MODE=1. Prints one
 * line of JSON with the number of profiles removed.
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.NamingConventions.PrefixAllGlobals, WordPress.DB

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() || '1' !== getenv( 'WIW_E2E_TEST_MODE' ) ) {
	fwrite( STDERR, "refused: needs a local environment and WIW_E2E_TEST_MODE=1\n" );
	exit( 1 );
}

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Review\Duplicates\DuplicateRepository;

global $wpdb;
$wiw_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", ProfileType::POST_TYPE, 'E2E Addp %' ) );
foreach ( $wiw_ids as $wiw_id ) {
	$wiw_id = (int) $wiw_id;
	$wpdb->delete( StateRepository::table(), array( 'profile_id' => $wiw_id ) );
	$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $wiw_id ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . DuplicateRepository::table() . ' WHERE profile_a = %d OR profile_b = %d', $wiw_id, $wiw_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	$wpdb->delete( AwardsTables::table( AwardsTables::NOMINATIONS ), array( 'profile_id' => $wiw_id ) );
	wp_delete_post( $wiw_id, true );
}
// The throw-away pictures the Edit profile browser test uploaded (file names starting "e2e-edit-portrait").
$wiw_media = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title LIKE %s", 'e2e-edit-portrait%' ) );
foreach ( $wiw_media as $wiw_media_id ) {
	wp_delete_attachment( (int) $wiw_media_id, true );
}
echo wp_json_encode(
	array(
		'removed' => count( $wiw_ids ),
		'media'   => count( $wiw_media ),
	)
) . "\n";
