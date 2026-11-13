<?php
/**
 * Seeds fictional stories and one reviewed profile into the site named by WIW_SECRETS_FILE, for the stories journeys in
 * tests/e2e/stories/. TEST ENVIRONMENT ONLY: it refuses unless WIW_ENV is local. It is idempotent (fixed slugs) and prints one
 * line of JSON with the slugs. Fictional names only; no personal data.
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
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Content\Stories\StoryFlags;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Create a story once, by slug.
 *
 * @param string $slug   Post name.
 * @param string $title  Title.
 * @param string $date   Post date.
 * @param string $status Post status.
 */
function wiw_seed_story( string $slug, string $title, string $date, string $status = 'publish' ): int {
	$existing = get_page_by_path( $slug, OBJECT, 'post' );
	if ( $existing instanceof WP_Post ) {
		return $existing->ID;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_excerpt' => 'A fictional story used by the browser tests.',
			'post_content' => "Fictional body text for the browser tests.\n\nA second paragraph.",
			'post_status'  => $status,
			'post_date'    => $date,
		)
	);
	if ( taxonomy_exists( 'wiw_story_type' ) ) {
		wp_set_object_terms( $id, 'news', 'wiw_story_type' );
	}
	return (int) $id;
}

if ( isset( $argv[1] ) && 'remove' === $argv[1] ) {
	global $wpdb;
	foreach ( array( 'e2e-story-newest', 'e2e-story-middle', 'e2e-story-oldest', 'e2e-story-draft', 'e2e-story-sponsored', 'e2e-story-expired' ) as $slug ) {
		$found = get_page_by_path( $slug, OBJECT, 'post' );
		if ( $found instanceof WP_Post ) {
			wp_delete_post( $found->ID, true );
		}
	}
	$gone = get_page_by_path( 'e2e-profile-qwv', OBJECT, ProfileType::POST_TYPE );
	if ( $gone instanceof WP_Post ) {
		$wpdb->delete( StateRepository::table(), array( 'profile_id' => $gone->ID ) );
		wp_delete_post( $gone->ID, true );
	}
	echo "{}\n";
	exit( 0 );
}

$stories = array(
	'e2e-story-newest'    => wiw_seed_story( 'e2e-story-newest', 'E2E Story Newest', '2026-10-30 10:00:00' ),
	'e2e-story-middle'    => wiw_seed_story( 'e2e-story-middle', 'E2E Story Middle', '2026-10-20 10:00:00' ),
	'e2e-story-oldest'    => wiw_seed_story( 'e2e-story-oldest', 'E2E Story Oldest', '2026-10-10 10:00:00' ),
	'e2e-story-draft'     => wiw_seed_story( 'e2e-story-draft', 'E2E Story Draft', '2026-10-25 10:00:00', 'draft' ),
	'e2e-story-sponsored' => wiw_seed_story( 'e2e-story-sponsored', 'E2E Story Sponsored Active', '2026-09-01 10:00:00' ),
	'e2e-story-expired'   => wiw_seed_story( 'e2e-story-expired', 'E2E Story Sponsored Expired', '2026-09-02 10:00:00' ),
);

// The sponsor fields have one sanctioned writer. One placement covers today; the other ended long ago.
$flags = new StoryFlags( AuditLogger::for_site() );
$flags->commerce_write( $stories['e2e-story-sponsored'], 'Fictional Sponsor Ltd', 'both', '2000-01-01', '2099-12-31' );
$flags->commerce_write( $stories['e2e-story-expired'], 'Fictional Sponsor Ltd', 'both', '2000-01-01', '2000-01-02' );

// One reviewed profile with a portrait, so the profile surfaces can be checked for Sponsored and Featured marks.
$profile = get_page_by_path( 'e2e-profile-qwv', OBJECT, ProfileType::POST_TYPE );
if ( ! $profile instanceof WP_Post ) {
	$profile_id = wp_insert_post(
		array(
			'post_type'  => ProfileType::POST_TYPE,
			'post_name'  => 'e2e-profile-qwv',
			'post_title' => 'E2E Profile Qwv',
		)
	);
	$machine    = new StateMachine();
	$machine->enter( (int) $profile_id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'individual' ) );
	$machine->transition( (int) $profile_id, PubState::PUBLISHED_REVIEWED, '', 1 );
	update_post_meta( $profile_id, PublicProfile::IMAGE_META, 99 );
}

echo wp_json_encode(
	array(
		'stories' => array_keys( $stories ),
		'profile' => 'e2e-profile-qwv',
	)
) . "\n";
