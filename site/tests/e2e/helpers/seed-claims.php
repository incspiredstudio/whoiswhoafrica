<?php
/**
 * Seeds one reviewed, unclaimed fictional profile for the claims browser specs, and removes it with its claims. TEST ENVIRONMENT
 * ONLY: it refuses unless the site is a local environment. Idempotent. Prints one line of JSON with the slug.
 *
 * Usage: WIW_SECRETS_FILE=... php tests/e2e/helpers/seed-claims.php [seed|remove]
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

$wiw_slug  = 'e2e-claim-target';
$wiw_found = get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE );

if ( isset( $argv[1] ) && 'remove' === $argv[1] ) {
	global $wpdb;
	if ( $wiw_found instanceof WP_Post ) {
		$wpdb->delete( $wpdb->prefix . 'wiw_profile_claims', array( 'profile_id' => $wiw_found->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( StateRepository::table(), array( 'profile_id' => $wiw_found->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( $wiw_found->ID, true );
	}
	echo "{}\n";
	exit( 0 );
}

if ( ! $wiw_found instanceof WP_Post ) {
	$wiw_id      = (int) wp_insert_post(
		array(
			'post_type'  => ProfileType::POST_TYPE,
			'post_name'  => $wiw_slug,
			'post_title' => 'E2E Claim Target',
		)
	);
	$wiw_machine = new StateMachine();
	$wiw_machine->enter( $wiw_id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
	$wiw_machine->transition( $wiw_id, PubState::PUBLISHED_REVIEWED, '', 1 );
}
echo wp_json_encode( array( 'slug' => $wiw_slug ) ) . "\n";
