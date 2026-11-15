<?php
/**
 * Seeds fictional Awards data for the staff UI phase 2 browser tests of group B (tests/e2e/staff-ui-b.spec.ts, REQ-275): one award
 * category and two reviewed organisation profiles, each nominated in it (Nominated, Not live). Every call starts them afresh, so a
 * test can set their stage again. TEST ENVIRONMENT ONLY: it refuses unless the site is local and WIW_E2E_TEST_MODE=1. The first
 * argument is a suffix that keeps parallel projects apart; "remove" as the second argument deletes them. Fictional names only; no
 * personal data. Prints one line of JSON.
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

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Review\StateMachine;

$wiw_suffix = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $argv[1] ?? 'x' ) ) );
$wiw_title  = 'E2E UiB Category ' . $wiw_suffix;
$wiw_rows   = array(
	'e2e-uib-alpha-' . $wiw_suffix => 'E2E UiB Alpha ' . $wiw_suffix,
	'e2e-uib-beta-' . $wiw_suffix  => 'E2E UiB Beta ' . $wiw_suffix,
);

global $wpdb;
$wiw_nom_table = AwardsTables::table( AwardsTables::NOMINATIONS );
$wiw_cat_table = AwardsTables::table( AwardsTables::CATEGORIES );
foreach ( array_keys( $wiw_rows ) as $wiw_slug ) {
	$wiw_old = get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE );
	if ( $wiw_old instanceof WP_Post ) {
		$wpdb->delete( $wiw_nom_table, array( 'profile_id' => $wiw_old->ID ) );
		$wpdb->delete( StateRepository::table(), array( 'profile_id' => $wiw_old->ID ) );
		wp_delete_post( $wiw_old->ID, true );
	}
}
$wpdb->delete( $wiw_cat_table, array( 'title' => $wiw_title ) );
if ( isset( $argv[2] ) && 'remove' === $argv[2] ) {
	echo "{}\n";
	exit( 0 );
}

// The services check the capability of the current user, so act as the first Administrator.
$wiw_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) $wiw_admins[0] );

$wiw_category    = ( new CategoryService( AuditLogger::for_site() ) )->add( 'technology-innovation', $wiw_title );
$wiw_nominations = NominationService::for_site();
$wiw_machine     = new StateMachine();
$wiw_out         = array(
	'category'    => $wiw_category,
	'nominations' => array(),
	'profiles'    => array(),
	'names'       => array_values( $wiw_rows ),
);
foreach ( $wiw_rows as $wiw_slug => $wiw_name ) {
	$wiw_id = (int) wp_insert_post(
		array(
			'post_type'  => ProfileType::POST_TYPE,
			'post_name'  => $wiw_slug,
			'post_title' => $wiw_name,
		)
	);
	$wiw_machine->enter(
		$wiw_id,
		PubState::PUBLISHED_PENDING,
		array(
			'entity_type' => 'organisation',
			'origin'      => 'manual',
			'source'      => 'e2e-uib',
		)
	);
	$wiw_machine->transition( $wiw_id, PubState::PUBLISHED_REVIEWED, '', 1 );
	$wiw_out['profiles'][]    = $wiw_id;
	$wiw_out['nominations'][] = $wiw_nominations->add_profile( $wiw_id, $wiw_category, 'A fictional note for the browser tests.' );
}
echo wp_json_encode( $wiw_out ) . "\n";
