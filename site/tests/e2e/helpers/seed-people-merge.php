<?php
/**
 * Seeds fictional profiles for the People merge spec (REQ-001, REQ-040, D-072): one reviewed individual and one reviewed
 * entrepreneur, both of which belong on /people/. TEST ENVIRONMENT ONLY: it refuses unless the site is a local environment.
 * Idempotent; takes a lock. Fictional names only, no personal data. Prints one line of JSON with the slugs and names.
 *
 * Usage: WIW_SECRETS_FILE=... php tests/e2e/helpers/seed-people-merge.php [remove]
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.PrefixAllGlobals

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() ) {
	fwrite( STDERR, "seed refused: not a local environment\n" );
	exit( 1 );
}

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Review\StateMachine;

$wiw_lock = fopen( sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wiw-seed-people-merge.lock', 'c' );
flock( $wiw_lock, LOCK_EX );

$wiw_profiles = array(
	'e2e-merge-person'  => array( 'Aaa E2E Merge Person', 'individual' ),
	'e2e-merge-founder' => array( 'Aaa E2E Merge Founder', 'entrepreneur' ),
);

if ( isset( $argv[1] ) && 'remove' === $argv[1] ) {
	global $wpdb;
	foreach ( array_keys( $wiw_profiles ) as $wiw_slug ) {
		$wiw_found = get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE );
		if ( $wiw_found instanceof WP_Post ) {
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $wiw_found->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_delete_post( $wiw_found->ID, true );
		}
	}
	echo "{}\n";
	exit( 0 );
}

$wiw_machine = new StateMachine();
$wiw_out     = array();
foreach ( $wiw_profiles as $wiw_slug => $wiw_def ) {
	if ( ! get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE ) instanceof WP_Post ) {
		$wiw_id = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_name'  => $wiw_slug,
				'post_title' => $wiw_def[0],
			)
		);
		$wiw_machine->enter( $wiw_id, PubState::PUBLISHED_PENDING, array( 'entity_type' => $wiw_def[1] ) );
		$wiw_machine->transition( $wiw_id, PubState::PUBLISHED_REVIEWED, '', 1 );
	}
	$wiw_out[ $wiw_def[1] ] = array(
		'slug' => $wiw_slug,
		'name' => $wiw_def[0],
	);
}
echo wp_json_encode( $wiw_out ) . "\n";
