<?php
/**
 * Seeds one fictional Profile owner account (REQ-274) and one reviewed, claimed profile it owns, for the account browser specs, and
 * removes them. One pair per suffix, so the 360 and 1280 projects never share state. Seeding again resets the pair: any change
 * waiting for review is dropped, the summary goes back to its first text and the sign-in lockout is cleared. TEST ENVIRONMENT
 * ONLY: refuses unless the site is local and WIW_E2E_TEST_MODE=1. The password is E2E_STAFF_PASS. Prints one line of JSON.
 *
 * Usage: WIW_SECRETS_FILE=... WIW_E2E_TEST_MODE=1 E2E_STAFF_PASS=... php tests/e2e/helpers/seed-owner.php <suffix> [seed|remove]
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.PrefixAllGlobals, WordPress.DB

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() || '1' !== getenv( 'WIW_E2E_TEST_MODE' ) || strlen( (string) getenv( 'E2E_STAFF_PASS' ) ) < 12 ) {
	fwrite( STDERR, "seed refused: needs a local environment, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS\n" );
	exit( 1 );
}

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Trust\Account\OwnerRole;

global $wpdb;
$wiw_suffix = isset( $argv[1] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $argv[1] ) ) : 'x';
$wiw_login  = 'e2e_owner_' . $wiw_suffix;
$wiw_email  = 'e2e-owner-' . $wiw_suffix . '@example.invalid';
$wiw_slug   = 'e2e-owner-profile-' . $wiw_suffix;
$wiw_found  = get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE );
$wiw_user   = get_user_by( 'login', $wiw_login );

if ( isset( $argv[2] ) && 'remove' === $argv[2] ) {
	if ( $wiw_found instanceof WP_Post ) {
		$wpdb->delete( VersionRepository::table(), array( 'profile_id' => $wiw_found->ID ) );
		$wpdb->delete( StateRepository::table(), array( 'profile_id' => $wiw_found->ID ) );
		wp_delete_post( $wiw_found->ID, true );
	}
	if ( $wiw_user instanceof WP_User ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $wiw_user->ID );
	}
	echo "{}\n";
	exit( 0 );
}

( new \WhoIsWho\Core\Platform\Capabilities\RoleSync( new \WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue() ) )->maybe_sync();
if ( ! $wiw_user instanceof WP_User ) {
	$wiw_id   = wp_insert_user(
		array(
			'user_login'   => $wiw_login,
			'user_email'   => $wiw_email,
			'user_pass'    => (string) getenv( 'E2E_STAFF_PASS' ),
			'display_name' => 'Fictional Owner ' . strtoupper( $wiw_suffix ),
			'role'         => OwnerRole::ROLE,
		)
	);
	$wiw_user = get_userdata( (int) $wiw_id );
} else {
	wp_set_password( (string) getenv( 'E2E_STAFF_PASS' ), $wiw_user->ID );
	wp_update_user(
		array(
			'ID'           => $wiw_user->ID,
			'display_name' => 'Fictional Owner ' . strtoupper( $wiw_suffix ),
		)
	);
}

if ( ! $wiw_found instanceof WP_Post ) {
	$wiw_pid     = (int) wp_insert_post(
		array(
			'post_type'  => ProfileType::POST_TYPE,
			'post_name'  => $wiw_slug,
			'post_title' => 'E2E Owner Profile ' . strtoupper( $wiw_suffix ),
		)
	);
	$wiw_machine = new StateMachine();
	$wiw_machine->enter( $wiw_pid, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
	$wiw_machine->transition( $wiw_pid, PubState::PUBLISHED_REVIEWED, '', 1 );
} else {
	$wiw_pid = (int) $wiw_found->ID;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . VersionRepository::table() . " WHERE profile_id = %d AND created_via = 'revision'", $wiw_pid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
}
update_post_meta( $wiw_pid, 'wiw_summary', 'A fictional organisation used only in browser tests.' );
( new StateRepository() )->update(
	$wiw_pid,
	array(
		'claimed'          => 1,
		'claimed_at'       => gmdate( 'Y-m-d H:i:s' ),
		'assigned_user_id' => (int) $wiw_user->ID,
	)
);
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wiw\_lock%' OR option_name LIKE '\_transient\_timeout\_wiw\_lock%' OR option_name LIKE '\_transient\_wiw\_rl\_%' OR option_name LIKE '\_transient\_timeout\_wiw\_rl\_%'" );
wp_cache_flush();
echo wp_json_encode(
	array(
		'email'      => $wiw_email,
		'profile_id' => $wiw_pid,
		'slug'       => $wiw_slug,
		'user_id'    => (int) $wiw_user->ID,
	)
) . "\n";
