<?php
/**
 * Seeds fictional staff users for the browser tests, one per role: Reviewer (two, so a second reviewer can approve what the
 * first imported), Editor and Administrator. TEST ENVIRONMENT ONLY: it refuses unless the site is a local environment AND
 * WIW_E2E_TEST_MODE=1, so it can never create a login on production. Passwords come from E2E_STAFF_PASS (never committed; any
 * throw-away value). Idempotent; "remove" deletes the users. Prints one line of JSON with user names and ids.
 *
 * Usage: WIW_SECRETS_FILE=... WIW_E2E_TEST_MODE=1 E2E_STAFF_PASS=... php tests/e2e/helpers/seed-staff.php [remove]
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.PrefixAllGlobals

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() || '1' !== getenv( 'WIW_E2E_TEST_MODE' ) ) {
	fwrite( STDERR, "seed refused: needs a local environment and WIW_E2E_TEST_MODE=1\n" );
	exit( 1 );
}

$wiw_users = array(
	'e2e_reviewer_a' => 'wiw_reviewer',
	'e2e_reviewer_b' => 'wiw_reviewer',
	'e2e_editor'     => 'wiw_editor',
	'e2e_admin'      => 'administrator',
);

if ( isset( $argv[1] ) && 'remove' === $argv[1] ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array_keys( $wiw_users ) as $login ) {
		$found = get_user_by( 'login', $login );
		if ( $found instanceof WP_User ) {
			wp_delete_user( $found->ID );
		}
	}
	echo "{}\n";
	exit( 0 );
}

$pass = (string) getenv( 'E2E_STAFF_PASS' );
if ( strlen( $pass ) < 12 ) {
	fwrite( STDERR, "seed refused: set E2E_STAFF_PASS (12 characters or more)\n" );
	exit( 1 );
}

$out = array();
foreach ( $wiw_users as $login => $role ) {
	$user = get_user_by( 'login', $login );
	if ( ! $user instanceof WP_User ) {
		$id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => $pass,
				'user_email' => $login . '@example.invalid',
				'role'       => $role,
			)
		);
		if ( is_wp_error( $id ) ) {
			fwrite( STDERR, 'seed failed for ' . $login . "\n" );
			exit( 1 );
		}
		$user = get_user_by( 'id', $id );
	} else {
		wp_set_password( $pass, $user->ID );
		$user->set_role( $role );
	}
	// Test mode only (the seed refused above unless it is on): skip the two-factor enrolment redirect for these throw-away users.
	update_user_meta( $user->ID, 'wiw_e2e_staff', '1' );
	// And keep the two-factor reminder (issue 814) off for them, so it does not disturb the other specs; two-factor-reminder.spec.ts uses its own users.
	update_user_meta( $user->ID, 'wiw_e2e_quiet', '1' );
	$out[ $login ] = $user->ID;
}
echo wp_json_encode( $out ) . "\n";
