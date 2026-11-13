<?php
/**
 * Issues fictional single-use links for the invitation and consent browser tests (tests/e2e/invite-flow.spec.ts and
 * consent-flow.spec.ts): an invitation for a new customer, and the agree and decline consent links for a complimentary listing.
 * TEST ENVIRONMENT ONLY: it refuses unless the site is local and WIW_E2E_TEST_MODE=1. Every run makes new links (a link works once).
 * Fictional names and an @example.invalid address only. Prints one line of JSON with the paths. "remove" deletes the profile it made.
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

use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\Invitations\Invitations;
use WhoIsWho\Core\Compliance\Consent\ListingConsent;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Review\StateMachine;

$wiw_prefix = 'e2e-complimentary-';
$wiw_slug   = $wiw_prefix . strtolower( wp_generate_password( 8, false ) );

if ( isset( $argv[1] ) && 'remove' === $argv[1] ) {
	global $wpdb;
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name LIKE %s", ProfileType::POST_TYPE, $wpdb->esc_like( $wiw_prefix ) . '%' ) ) as $wiw_id ) {
		$gone = get_post( (int) $wiw_id );
		if ( $gone instanceof WP_Post ) {
			$wpdb->delete( ListingConsent::table(), array( 'profile_id' => $gone->ID ) );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $gone->ID ) );
			wp_delete_post( $gone->ID, true );
		}
	}
	echo "{}\n";
	exit( 0 );
}

// No mail leaves the test site: the invitation email is dropped instead of waiting for an SMTP server.
add_filter( 'pre_wp_mail', '__return_true' );
wp_set_current_user( 1 );
$customer   = ( new CustomerRepository() )->insert( array( 'name' => 'E2E Invitation Co' ), null, new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
$invitation = Invitations::for_site()->issue( $customer, 'e2e.invite@example.invalid' );

$profile = get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE );
if ( ! $profile instanceof WP_Post ) {
	$id = (int) wp_insert_post(
		array(
			'post_type'  => ProfileType::POST_TYPE,
			'post_name'  => $wiw_slug,
			'post_title' => 'E2E Complimentary Co',
		)
	);
	( new StateMachine() )->enter(
		$id,
		PubState::IMPORTED_NOT_PUBLIC,
		array(
			'entity_type' => 'organisation',
			'origin'      => ListingConsent::ORIGIN,
		)
	);
	$profile = get_post( $id );
}
$links = ( new ListingConsent() );
$pair  = $links->issue_links( SignedLinks::for_site(), (int) $profile->ID, 'e2e.consent@example.invalid' );

echo wp_json_encode(
	array(
		'invite'  => wp_parse_url( $invitation, PHP_URL_PATH ),
		'accept'  => '/link/' . $pair['accept'],
		'decline' => '/link/' . $pair['decline'],
	)
), "\n";
