<?php
/**
 * Seeds fictional profiles for the review-flow browser tests (tests/e2e/review-flows.spec.ts): a pending organisation imported by
 * Reviewer A, a flagged duplicate pair, and three reviewed profiles in one import batch for bulk unpublish. TEST ENVIRONMENT
 * ONLY: it refuses unless the site is local and WIW_E2E_TEST_MODE=1. Needs the staff users from seed-staff.php. Idempotent
 * (fixed slugs); "remove" deletes everything it made. Fictional names only; no personal data. Prints one line of JSON.
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

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Review\Duplicates\DuplicateRepository;
use WhoIsWho\Core\Review\StateMachine;

$wiw_slugs = array( 'e2e-review-org', 'e2e-dup-alpha', 'e2e-dup-alpha-ltd', 'e2e-bulk-one', 'e2e-bulk-two', 'e2e-bulk-three' );

if ( isset( $argv[1] ) && 'remove' === $argv[1] ) {
	global $wpdb;
	foreach ( $wiw_slugs as $slug ) {
		$gone = get_page_by_path( $slug, OBJECT, ProfileType::POST_TYPE );
		if ( $gone instanceof WP_Post ) {
			$wpdb->delete( DuplicateRepository::table(), array( 'profile_a' => $gone->ID ) );
			$wpdb->delete( DuplicateRepository::table(), array( 'profile_b' => $gone->ID ) );
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $gone->ID ) );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $gone->ID ) );
			wp_delete_post( $gone->ID, true );
		}
	}
	echo "{}\n";
	exit( 0 );
}

$importer = get_user_by( 'login', 'e2e_reviewer_a' );
if ( ! $importer instanceof WP_User ) {
	fwrite( STDERR, "seed refused: run seed-staff.php first\n" );
	exit( 1 );
}

/**
 * Create a profile once, by slug.
 *
 * @param string $slug     Slug.
 * @param string $title    Name.
 * @param string $type     Entity type.
 * @param string $reviewed Whether to review it.
 * @param array  $values   Extra state columns.
 */
function wiw_seed_profile( string $slug, string $title, string $type, bool $reviewed, array $values = array() ): int {
	$existing = get_page_by_path( $slug, OBJECT, ProfileType::POST_TYPE );
	if ( $existing instanceof WP_Post ) {
		return $existing->ID;
	}
	$id      = (int) wp_insert_post(
		array(
			'post_type'  => ProfileType::POST_TYPE,
			'post_name'  => $slug,
			'post_title' => $title,
		)
	);
	$machine = new StateMachine();
	$machine->enter( $id, PubState::PUBLISHED_PENDING, array_merge( array( 'entity_type' => $type ), $values ) );
	if ( $reviewed ) {
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
	}
	return $id;
}

$org = wiw_seed_profile(
	'e2e-review-org',
	'E2E Review Organisation',
	'organisation',
	false,
	array(
		'importer_id'  => $importer->ID,
		'source'       => 'e2e-review',
		'import_batch' => 'e2e-review-batch',
	)
);
// The fact gate wants a source for every imported field; the name is the only field the fictional organisation has.
$provenance = new ProvenanceRepository();
if ( array() === $provenance->for_profile( $org ) ) {
	$provenance->record(
		$org,
		array(
			'field'        => 'name',
			'source_url'   => 'https://example.invalid/e2e-review-org',
			'retrieved_at' => '2026-11-01',
			'confidence'   => 'high',
			'method'       => 'import',
		)
	);
}
$dup1 = wiw_seed_profile( 'e2e-dup-alpha', 'E2E Dup Alpha', 'organisation', false, array( 'source' => 'e2e-dup' ) );
$dup2 = wiw_seed_profile( 'e2e-dup-alpha-ltd', 'E2E Dup Alpha Ltd', 'organisation', false, array( 'source' => 'e2e-dup' ) );
( new DuplicateRepository() )->flag( $dup1, $dup2, 92, 'name' );
$bulk = array();
foreach ( array(
	'e2e-bulk-one'   => 'E2E Bulk One',
	'e2e-bulk-two'   => 'E2E Bulk Two',
	'e2e-bulk-three' => 'E2E Bulk Three',
) as $slug => $title ) {
	$bulk[ $slug ] = wiw_seed_profile(
		$slug,
		$title,
		'organisation',
		true,
		array(
			'source'       => 'e2e-bulk',
			'import_batch' => 'e2e-bulk-batch',
		)
	);
}

echo wp_json_encode(
	array(
		'org'   => 'e2e-review-org',
		'orgId' => $org,
		'dup'   => array( $dup1, $dup2 ),
		'bulk'  => array_keys( $bulk ),
	)
) . "\n";
