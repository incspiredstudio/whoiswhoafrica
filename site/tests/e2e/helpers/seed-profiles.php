<?php
/**
 * Seeds fictional profiles for the list and profile browser specs: a reviewed individual (with an email address and a phone number
 * stored on the record, which no public page may print), a reviewed organisation and an individual still pending review.
 * TEST ENVIRONMENT ONLY: it refuses unless the site is a local environment. Idempotent and safe to start from several workers at
 * once (it takes a lock). Fictional names only, no personal data. Prints one line of JSON with the slugs.
 *
 * Usage: WIW_SECRETS_FILE=... php tests/e2e/helpers/seed-profiles.php [remove]
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

$wiw_lock = fopen( sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wiw-seed-profiles.lock', 'c' );
flock( $wiw_lock, LOCK_EX );

$wiw_profiles = array(
	'e2e-a11y-person'  => array( 'E2E A11y Person', 'individual', true ),
	'e2e-a11y-org'     => array( 'E2E A11y Organisation', 'organisation', true ),
	'e2e-a11y-pending' => array( 'E2E A11y Pending Person', 'individual', false ),
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
foreach ( $wiw_profiles as $wiw_slug => $wiw_def ) {
	if ( get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE ) instanceof WP_Post ) {
		continue;
	}
	$wiw_id = (int) wp_insert_post(
		array(
			'post_type'  => ProfileType::POST_TYPE,
			'post_name'  => $wiw_slug,
			'post_title' => $wiw_def[0],
		)
	);
	$wiw_machine->enter( $wiw_id, PubState::PUBLISHED_PENDING, array( 'entity_type' => $wiw_def[1] ) );
	if ( $wiw_def[2] ) {
		$wiw_machine->transition( $wiw_id, PubState::PUBLISHED_REVIEWED, '', 1 );
	}
	// Private contact details kept on the record: the public read model has no such field, so none may reach a page.
	update_post_meta( $wiw_id, 'email', 'e2e-private-person@example.invalid' );
	update_post_meta( $wiw_id, 'phone', '+000 555 0100 e2e' );
}
echo wp_json_encode(
	array(
		'person'  => 'e2e-a11y-person',
		'org'     => 'e2e-a11y-org',
		'pending' => 'e2e-a11y-pending',
	)
) . "\n";
