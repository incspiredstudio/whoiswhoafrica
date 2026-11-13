<?php
/**
 * Seeds fictional Awards data and switches Nominations on or off for the awards browser specs. TEST ENVIRONMENT ONLY: it
 * refuses unless the site is a local environment. Idempotent (fixed slugs and titles). Fictional names only, no personal data.
 *
 * Usage: WIW_SECRETS_FILE=... php tests/e2e/helpers/seed-awards.php [on|off|seed|remove]
 *   on / off  set the Nominations switch (the only feature flag) and print the state;
 *   seed      (default) create one category, a Live nominee, a Live winner and a Not live nomination, print their references;
 *   remove    delete the seeded rows and profiles and switch Nominations off.
 * Prints one line of JSON.
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

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Awards\PublicNominee;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateMachine;

$wiw_mode   = isset( $argv[1] ) ? (string) $argv[1] : 'seed';
$wiw_switch = SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_ENABLED;
$wiw_title  = 'E2E Awards Category';
$wiw_slugs  = array( 'e2e-awards-live', 'e2e-awards-winner', 'e2e-awards-hidden' );

if ( 'on' === $wiw_mode || 'off' === $wiw_mode ) {
	update_option( $wiw_switch, 'on' === $wiw_mode ? '1' : '0' );
	echo wp_json_encode( array( 'nominations' => 'on' === $wiw_mode ) ) . "\n";
	exit( 0 );
}

global $wpdb;
$wiw_nom_table = AwardsTables::table( AwardsTables::NOMINATIONS );
$wiw_cat_table = AwardsTables::table( AwardsTables::CATEGORIES );

if ( 'remove' === $wiw_mode ) {
	foreach ( $wiw_slugs as $wiw_slug ) {
		$wiw_found = get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE );
		if ( $wiw_found instanceof WP_Post ) {
			$wpdb->delete( $wiw_nom_table, array( 'profile_id' => $wiw_found->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $wiw_found->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_delete_post( $wiw_found->ID, true );
		}
	}
	$wpdb->delete( $wiw_cat_table, array( 'title' => $wiw_title ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	update_option( $wiw_switch, '0' );
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

$wiw_categories = new CategoryService( WhoIsWho\Core\Platform\Audit\AuditLogger::for_site() );
$wiw_category   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $wiw_cat_table . ' WHERE title = %s', $wiw_title ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
if ( $wiw_category < 1 ) {
	$wiw_category = $wiw_categories->add( 'technology-innovation', $wiw_title );
}

$wiw_nominations = NominationService::for_site();
$wiw_machine     = new StateMachine();
$wiw_out         = array( 'category' => $wiw_category );
foreach ( $wiw_slugs as $wiw_slug ) {
	$wiw_profile = get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE );
	if ( ! $wiw_profile instanceof WP_Post ) {
		$wiw_id = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_name'  => $wiw_slug,
				'post_title' => ucwords( str_replace( '-', ' ', $wiw_slug ) ),
			)
		);
		$wiw_machine->enter( $wiw_id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'individual' ) );
		$wiw_machine->transition( $wiw_id, PubState::PUBLISHED_REVIEWED, '', 1 );
	} else {
		$wiw_id = $wiw_profile->ID;
	}
	$wiw_nomination = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $wiw_nom_table . ' WHERE profile_id = %d', $wiw_id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	if ( $wiw_nomination < 1 ) {
		$wiw_nomination = $wiw_nominations->add_profile( $wiw_id, $wiw_category, 'A fictional note for the browser tests.' );
	}
	if ( 'e2e-awards-hidden' !== $wiw_slug ) {
		$wiw_nominations->set_status( $wiw_nomination, 'live' );
	}
	if ( 'e2e-awards-winner' === $wiw_slug ) {
		$wiw_nominations->set_stage( $wiw_nomination, 'winner' );
	}
	$wiw_out[ str_replace( 'e2e-awards-', '', $wiw_slug ) ] = PublicNominee::ref_for( $wiw_nomination );
}
echo wp_json_encode( $wiw_out ) . "\n";
