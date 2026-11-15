<?php
/**
 * Seeds two fictional pending organisations for the staff UI kit browser tests (tests/e2e/staff-ui-kit.spec.ts, REQ-275): they
 * sit at the top of the review queue (pilot tier, added by hand) and in the Profiles list under the source "e2e-kit". Every call
 * starts them afresh (pending review), so a test can approve them again. TEST ENVIRONMENT ONLY: it refuses unless the site is
 * local and WIW_E2E_TEST_MODE=1. The first argument is a suffix that keeps parallel projects apart; "remove" as the second
 * argument deletes them. Fictional names only; no personal data. Prints one line of JSON.
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

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Review\StateMachine;

$wiw_suffix = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $argv[1] ?? 'x' ) ) );
$wiw_rows   = array(
	'e2e-kit-alpha-' . $wiw_suffix => 'E2E Kit Alpha ' . $wiw_suffix,
	'e2e-kit-beta-' . $wiw_suffix  => 'E2E Kit Beta ' . $wiw_suffix,
);

global $wpdb;
foreach ( array_keys( $wiw_rows ) as $wiw_slug ) {
	$wiw_old = get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE );
	if ( $wiw_old instanceof WP_Post ) {
		$wpdb->delete( StateRepository::table(), array( 'profile_id' => $wiw_old->ID ) );
		wp_delete_post( $wiw_old->ID, true );
	}
}
if ( isset( $argv[2] ) && 'remove' === $argv[2] ) {
	echo "{}\n";
	exit( 0 );
}

$wiw_ids = array();
foreach ( $wiw_rows as $wiw_slug => $wiw_title ) {
	$wiw_id = (int) wp_insert_post(
		array(
			'post_type'  => ProfileType::POST_TYPE,
			'post_name'  => $wiw_slug,
			'post_title' => $wiw_title,
		)
	);
	( new StateMachine() )->enter(
		$wiw_id,
		PubState::PUBLISHED_PENDING,
		array(
			'entity_type' => 'organisation',
			'origin'      => 'manual',
			'source'      => 'e2e-kit',
			'tier'        => 'T1',
		)
	);
	$wiw_ids[] = $wiw_id;
}

echo wp_json_encode(
	array(
		'ids'   => $wiw_ids,
		'names' => array_values( $wiw_rows ),
	)
) . "\n";
