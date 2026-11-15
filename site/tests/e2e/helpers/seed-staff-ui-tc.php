<?php
/**
 * Seeds fictional rows for the Trust and Compliance staff screens browser test (tests/e2e/staff-ui-trust-compliance.spec.ts,
 * REQ-275): two reviewed organisations with one open claim each, two enquiries, two reports and two corrections about the first
 * organisation, and one suppression entry. Every call starts them afresh. TEST ENVIRONMENT ONLY: it refuses unless the site is
 * local and WIW_E2E_TEST_MODE=1. The first argument is a suffix that keeps parallel projects apart; "remove" as the second
 * argument deletes them. Fictional names and @example.invalid addresses only; no personal data. Prints one line of JSON.
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

use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Trust\ClaimRepository;

$wiw_suffix = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $argv[1] ?? 'x' ) ) );
$wiw_mail   = 'e2e-tc-' . $wiw_suffix . '@example.invalid';
$wiw_rows   = array(
	'e2e-tc-alpha-' . $wiw_suffix => 'E2E TC Alpha ' . $wiw_suffix,
	'e2e-tc-beta-' . $wiw_suffix  => 'E2E TC Beta ' . $wiw_suffix,
);
$wiw_hidden = 'E2E TC Suppressed ' . $wiw_suffix;

global $wpdb;
foreach ( array_keys( $wiw_rows ) as $wiw_slug ) {
	$wiw_old = get_page_by_path( $wiw_slug, OBJECT, ProfileType::POST_TYPE );
	if ( $wiw_old instanceof WP_Post ) {
		$wpdb->delete( ClaimRepository::table(), array( 'profile_id' => $wiw_old->ID ) );
		$wpdb->delete( StateRepository::table(), array( 'profile_id' => $wiw_old->ID ) );
		wp_delete_post( $wiw_old->ID, true );
	}
}
$wpdb->delete( SupportItems::table(), array( 'requester_email' => $wiw_mail ) );
$wpdb->delete( Suppression::table(), array( 'name' => $wiw_hidden ) );
if ( isset( $argv[2] ) && 'remove' === $argv[2] ) {
	echo "{}\n";
	exit( 0 );
}

$wiw_profiles = array();
foreach ( $wiw_rows as $wiw_slug => $wiw_title ) {
	$wiw_id      = (int) wp_insert_post(
		array(
			'post_type'  => ProfileType::POST_TYPE,
			'post_name'  => $wiw_slug,
			'post_title' => $wiw_title,
		)
	);
	$wiw_machine = new StateMachine();
	$wiw_machine->enter( $wiw_id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
	$wiw_machine->transition( $wiw_id, PubState::PUBLISHED_REVIEWED, '', 1 );
	$wiw_profiles[] = $wiw_id;
}

$wiw_claims = array();
foreach ( $wiw_profiles as $wiw_profile ) {
	$wiw_claims[] = ( new ClaimRepository() )->create(
		array(
			'profile_id'   => $wiw_profile,
			'relationship' => 'representative',
			'name'         => 'Fictional Claimant',
			'role'         => 'Director',
			'work_email'   => $wiw_mail,
			'phone'        => '+000 000 0000',
			'evidence'     => 'Fictional evidence for a browser test.',
			'consent'      => true,
		)
	)->reference;
}

$wiw_items = new SupportItems();
$wiw_out   = array(
	'claims'      => $wiw_claims,
	'enquiries'   => array(),
	'reports'     => array(),
	'corrections' => array(),
);
foreach ( array(
	'enquiries'   => ItemType::ENQUIRY,
	'reports'     => ItemType::REPORT,
	'corrections' => ItemType::CORRECTION,
) as $wiw_key => $wiw_type ) {
	for ( $wiw_i = 1; $wiw_i <= 2; $wiw_i++ ) {
		$wiw_item              = $wiw_items->create(
			array(
				'type'         => $wiw_type,
				'name'         => 'Fictional Requester',
				'email'        => $wiw_mail,
				'relationship' => 'person',
				'message'      => 'Fictional message ' . $wiw_i . ' for a browser test.',
				'source'       => 'contact',
				'profile_id'   => $wiw_profiles[0],
			)
		);
		$wiw_out[ $wiw_key ][] = (string) $wiw_item['reference'];
	}
}

$wiw_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
if ( array() !== $wiw_admins ) {
	wp_set_current_user( (int) $wiw_admins[0] );
	( new Suppression( AuditLogger::for_site() ) )->add_manual( $wiw_hidden, 'organisation', array(), 'Fictional entry for a browser test' );
	wp_set_current_user( 0 );
}
$wiw_out['suppressed'] = $wiw_hidden;

echo wp_json_encode( $wiw_out ) . "\n";
