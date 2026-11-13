<?php
/**
 * Places and ends a sponsored story for the sponsored journey spec, with dates set by the test. TEST ENVIRONMENT ONLY: it refuses
 * unless the site is a local environment. The deal itself is made in the browser (Administrator, New sponsored story); this helper
 * does the steps that have no screen yet (choose the story, schedule the slot) through the same services and runs the placement job
 * for a chosen date, so a test can move time. Fictional names only. Prints one line of JSON.
 *
 * Usage: WIW_SECRETS_FILE=... php tests/e2e/helpers/seed-sponsored.php <place|travel|remove> [customer name | Y-m-d]
 *   place   <customer name>  create a published story, attach it to that customer's deal and schedule it from yesterday to tomorrow
 *   travel  <Y-m-d>          run the placement job as if that were today
 *   remove                    delete the story, deals, slots and customers whose name starts with "E2E Sponsor"
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

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\PlacementJob;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Commerce\PlacementService;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

$wiw_cmd  = isset( $argv[1] ) ? (string) $argv[1] : '';
$wiw_arg  = isset( $argv[2] ) ? (string) $argv[2] : '';
$wiw_slug = 'e2e-sponsored-journey-story';

global $wpdb;

if ( 'travel' === $wiw_cmd ) {
	if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $wiw_arg ) ) {
		fwrite( STDERR, "give a date as Y-m-d\n" );
		exit( 1 );
	}
	PlacementJob::for_site()->run( $wiw_arg );
	echo wp_json_encode( array( 'today' => $wiw_arg ) ) . "\n";
	exit( 0 );
}

if ( 'remove' === $wiw_cmd ) {
	$wiw_customers = (array) $wpdb->get_col( 'SELECT id FROM ' . CustomerRepository::table() . " WHERE name LIKE 'E2E Sponsor%'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name, no variable.
	foreach ( $wiw_customers as $wiw_customer ) {
		$wiw_deals = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . ArrangementRepository::table() . ' WHERE customer_id = %d', (int) $wiw_customer ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		foreach ( $wiw_deals as $wiw_deal ) {
			$wpdb->delete( PlacementRepository::table(), array( 'arrangement_id' => (int) $wiw_deal ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ArrangementRepository::table(), array( 'id' => (int) $wiw_deal ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( CustomerRepository::table(), array( 'id' => (int) $wiw_customer ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	$wiw_story = get_posts(
		array(
			'name'           => $wiw_slug,
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => 1,
		)
	);
	if ( array() !== $wiw_story ) {
		wp_delete_post( $wiw_story[0]->ID, true );
	}
	echo "{}\n";
	exit( 0 );
}

if ( 'place' !== $wiw_cmd || '' === $wiw_arg ) {
	fwrite( STDERR, "unknown command\n" );
	exit( 1 );
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

$wiw_customer = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . CustomerRepository::table() . ' WHERE name = %s', $wiw_arg ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
$wiw_deal     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ArrangementRepository::table() . ' WHERE customer_id = %d ORDER BY id DESC LIMIT 1', $wiw_customer ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
if ( $wiw_deal < 1 ) {
	fwrite( STDERR, "no deal for that customer\n" );
	exit( 1 );
}
$wiw_story   = (int) wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_name'    => $wiw_slug,
		'post_title'   => 'E2E Sponsored Journey Story',
		'post_status'  => 'publish',
		'post_excerpt' => 'A fictional sponsored story.',
		'post_content' => 'Fictional body text.',
	)
);
$wiw_service = new PlacementService( new ArrangementRepository(), new PlacementRepository(), new CustomerRepository(), AuditLogger::for_site() );
$wiw_service->choose_story( $wiw_deal, $wiw_story );
$wiw_result = $wiw_service->schedule( $wiw_deal, 'both', gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ), gmdate( 'Y-m-d', time() + DAY_IN_SECONDS ) );
PlacementJob::for_site()->run( gmdate( 'Y-m-d' ) );
echo wp_json_encode(
	array(
		'story'     => $wiw_slug,
		'placement' => $wiw_result['placement_id'],
		'warnings'  => $wiw_result['warnings'],
	)
) . "\n";
