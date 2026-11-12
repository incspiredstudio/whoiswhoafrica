<?php
/**
 * Launch safeguards on the staging copy built from the real import (M5-LCH-01; REQ-208, REQ-209, REQ-210, REQ-217). Read only:
 * it counts profiles per state, checks that every public individual is reviewed, that WordPress status agrees with the
 * publication state, that the sitemap lists only reviewed profiles, and that the status-write guard is in place. It prints counts and
 * check names, never a name or any other personal data, and exits non-zero when a check fails.
 *
 * Usage (from the site folder on the staging host): php deploy/launch-safeguards.php
 * Run the rest of launch checklist item 10 beside it: composer test -- --testsuite Visibility, and rehearse bulk unpublish and restore.
 *
 * @package WhoIsWho
 */

// phpcs:disable -- a command line deploy script outside the linted source.

declare(strict_types=1);

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Forbidden\n" );
}

require __DIR__ . '/safeguards/Safeguards.php';

use WhoIsWho\Deploy\Safeguards;

$wp_load = dirname( __DIR__ ) . '/wordpress/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	fwrite( STDERR, "FAIL  WordPress was not found next to the deploy folder\n" );
	exit( 1 );
}
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';
define( 'WP_USE_THEMES', false );
require $wp_load;

global $wpdb;
$state_table = \WhoIsWho\Core\Directory\State\StateRepository::table();
$type        = \WhoIsWho\Core\Directory\ProfileType::POST_TYPE;
$reviewed    = \WhoIsWho\Core\Directory\State\PubState::PUBLISHED_REVIEWED;
$pending     = \WhoIsWho\Core\Directory\State\PubState::PUBLISHED_PENDING;

$facts = array( 'states' => array() );
foreach ( (array) $wpdb->get_results( "SELECT pub_state, entity_type, COUNT(*) AS n FROM {$state_table} GROUP BY pub_state, entity_type", ARRAY_A ) as $row ) {
	$facts['states'][ (string) $row['pub_state'] ][ (string) $row['entity_type'] ] = (int) $row['n'];
}

$people                           = $wpdb->prepare( "SELECT COUNT(*) FROM {$state_table} WHERE pub_state = %s AND entity_type IN ('individual','entrepreneur')", $pending );
$facts['unreviewed_public_people'] = (int) $wpdb->get_var( $people );

$facts['status_mismatch'] = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$state_table} s ON s.profile_id = p.ID WHERE p.post_type = %s AND ( ( s.pub_state IN (%s, %s) AND p.post_status <> 'publish' ) OR ( s.pub_state NOT IN (%s, %s) AND p.post_status = 'publish' ) )",
		$type,
		$reviewed,
		$pending,
		$reviewed,
		$pending
	)
);

$facts['reviewed_slugs']     = array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.post_name FROM {$wpdb->posts} p INNER JOIN {$state_table} s ON s.profile_id = p.ID WHERE p.post_type = %s AND s.pub_state = %s", $type, $reviewed ) ) );
$facts['not_reviewed_slugs'] = array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.post_name FROM {$wpdb->posts} p INNER JOIN {$state_table} s ON s.profile_id = p.ID WHERE p.post_type = %s AND s.pub_state <> %s", $type, $reviewed ) ) );

$sitemap = new \WhoIsWho\Core\Directory\Sitemap\Sitemap();
$sitemap->purge();
$slugs = array();
for ( $part = 1; $part <= 1000; $part++ ) {
	$response = $sitemap->respond( 'sitemap-profiles-' . $part . '.xml' );
	if ( null === $response || 200 !== $response['status'] ) {
		break;
	}
	if ( preg_match_all( '#<loc>[^<]*/profile/([^/<]+)/?</loc>#', $response['body'], $found ) ) {
		$slugs = array_merge( $slugs, $found[1] );
	}
	if ( 0 === count( $found[1] ?? array() ) ) {
		break;
	}
}
$facts['sitemap_slugs'] = $slugs;

$facts['direct_status_writes_refused'] = false !== has_filter( 'wp_insert_post_data', array( \WhoIsWho\Guard\Status\StatusWriteGuard::class, 'filter' ) );

$results = Safeguards::evaluate( $facts );
echo Safeguards::render( $results );
exit( Safeguards::passed( $results ) ? 0 : 1 );
