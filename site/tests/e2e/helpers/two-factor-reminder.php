<?php
/**
 * Seeds two fictional editors for the two-factor reminder spec (issue 814): e2e_tf_off has no two-factor method, e2e_tf_on has the
 * Email method on (and the test-mode mark, so the browser sign-in skips the code prompt). TEST ENVIRONMENT ONLY: it refuses unless
 * the site is a local environment AND WIW_E2E_TEST_MODE=1. Passwords come from E2E_STAFF_PASS. "seed" (default) creates or resets
 * them, "state <login>" prints the reminder meta, "remove" deletes them. "seed" and "remove" take a suffix (the viewport
 * width), so each browser project has its own users. Prints one line of JSON.
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.PrefixAllGlobals

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() || '1' !== getenv( 'WIW_E2E_TEST_MODE' ) ) {
	fwrite( STDERR, "refused: needs a local environment and WIW_E2E_TEST_MODE=1\n" );
	exit( 1 );
}

$cmd    = $argv[1] ?? 'seed';
$suffix = 'state' === $cmd ? '' : preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $argv[2] ?? '' ) ) );
$users  = array( 'e2e_tf_off' . $suffix, 'e2e_tf_on' . $suffix );

if ( 'remove' === $cmd ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $login ) {
		$found = get_user_by( 'login', $login );
		if ( $found instanceof WP_User ) {
			wp_delete_user( $found->ID );
		}
	}
	echo "{}\n";
	exit( 0 );
}

if ( 'state' === $cmd ) {
	$found = get_user_by( 'login', (string) ( $argv[2] ?? '' ) );
	$until = $found instanceof WP_User ? (int) get_user_meta( $found->ID, 'wiw_two_factor_remind_after', true ) : 0;
	echo wp_json_encode(
		array(
			'snoozed_for' => max( 0, $until - time() ),
			'emailed'     => $found instanceof WP_User && '' !== (string) get_user_meta( $found->ID, 'wiw_two_factor_reminder_emailed', true ),
		)
	) . "\n";
	exit( 0 );
}

$pass = (string) getenv( 'E2E_STAFF_PASS' );
if ( strlen( $pass ) < 12 ) {
	fwrite( STDERR, "refused: set E2E_STAFF_PASS (12 characters or more)\n" );
	exit( 1 );
}

$out = array();
foreach ( $users as $login ) {
	$user = get_user_by( 'login', $login );
	if ( ! $user instanceof WP_User ) {
		$id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => $pass,
				'user_email' => $login . '@example.invalid',
				'role'       => 'wiw_editor',
			)
		);
		if ( is_wp_error( $id ) ) {
			fwrite( STDERR, 'seed failed for ' . $login . "\n" );
			exit( 1 );
		}
		$user = get_user_by( 'id', $id );
	} else {
		wp_set_password( $pass, $user->ID );
		$user->set_role( 'wiw_editor' );
	}
	delete_user_meta( $user->ID, 'wiw_two_factor_remind_after' );
	delete_user_meta( $user->ID, 'wiw_two_factor_reminder_emailed' );
	delete_user_meta( $user->ID, 'wiw_e2e_quiet' );
	$is_on = 0 === strpos( $login, 'e2e_tf_on' );
	if ( $is_on ) {
		update_user_meta( $user->ID, '_two_factor_enabled_providers', array( 'Two_Factor_Email' ) );
		update_user_meta( $user->ID, '_two_factor_provider', 'Two_Factor_Email' );
		update_user_meta( $user->ID, 'wiw_e2e_staff', '1' );
	} else {
		delete_user_meta( $user->ID, '_two_factor_enabled_providers' );
		delete_user_meta( $user->ID, 'wiw_e2e_staff' );
	}
	$out[ $is_on ? 'on' : 'off' ] = $login;
}
echo wp_json_encode( $out ) . "\n";
