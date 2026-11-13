<?php
/**
 * Seeds fictional events and stories for the events browser specs, and grants or withdraws the events capability for the seeded
 * Reviewer. TEST ENVIRONMENT ONLY: it refuses unless the site is a local environment. Idempotent (fixed slugs). Fictional names
 * only, no personal data. Prints one line of JSON.
 *
 * Usage: WIW_SECRETS_FILE=... php tests/e2e/helpers/seed-events.php [seed|remove|hide|show|grant|revoke]
 *   seed    create an upcoming event, an Online event, a past event, a draft event and two stories (one linked to the upcoming event)
 *   hide    draft every seeded event that has not happened yet, so the site has no upcoming event (the home block must vanish)
 *   show    publish them again
 *   grant   give the seeded Reviewer (e2e_reviewer_a, see seed-staff.php) the events capability; revoke takes it away
 *   remove  delete everything seeded here, and any event whose address starts with e2e-
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

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Stories\RelatedEvent;

$wiw_mode = isset( $argv[1] ) ? (string) $argv[1] : 'seed';

$wiw_events  = array(
	'e2e-evt-upcoming' => array( 'E2E Event Upcoming', 30, 'publish', false ),
	'e2e-evt-online'   => array( 'E2E Event Online', 40, 'publish', true ),
	'e2e-evt-past'     => array( 'E2E Event Past', -30, 'publish', false ),
	'e2e-evt-draft'    => array( 'E2E Event Draft', 20, 'draft', false ),
);
$wiw_stories = array( 'e2e-evt-story-linked', 'e2e-evt-story-plain' );

/**
 * Find a post by slug and type.
 *
 * @param string $slug Slug.
 * @param string $type Post type.
 */
function wiw_find_post( string $slug, string $type ): ?WP_Post {
	$found = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => $type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
		)
	);
	return $found[0] ?? null;
}

if ( 'grant' === $wiw_mode || 'revoke' === $wiw_mode ) {
	$wiw_user = get_user_by( 'login', 'e2e_reviewer_a' );
	if ( ! $wiw_user instanceof WP_User ) {
		fwrite( STDERR, "run seed-staff.php first\n" );
		exit( 1 );
	}
	'grant' === $wiw_mode ? $wiw_user->add_cap( EventPostType::CAP ) : $wiw_user->remove_cap( EventPostType::CAP );
	echo wp_json_encode( array( 'granted' => 'grant' === $wiw_mode ) ) . "\n";
	exit( 0 );
}

if ( 'remove' === $wiw_mode ) {
	// Every event whose address starts with e2e-, which includes the one a spec creates through the admin screen.
	foreach ( get_posts(
		array(
			'post_type'      => EventPostType::TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
		)
	) as $wiw_post ) {
		if ( str_starts_with( $wiw_post->post_name, 'e2e-' ) ) {
			wp_delete_post( $wiw_post->ID, true );
		}
	}
	foreach ( $wiw_stories as $wiw_slug ) {
		$wiw_post = wiw_find_post( $wiw_slug, 'post' );
		if ( $wiw_post instanceof WP_Post ) {
			wp_delete_post( $wiw_post->ID, true );
		}
	}
	echo "{}\n";
	exit( 0 );
}

if ( 'hide' === $wiw_mode || 'show' === $wiw_mode ) {
	// Every upcoming published event on the site, not only the seeded ones, so the home block is really empty while hidden.
	$wiw_now = gmdate( 'Y-m-d H:i' );
	foreach ( get_posts(
		array(
			'post_type'      => EventPostType::TYPE,
			'post_status'    => 'hide' === $wiw_mode ? 'publish' : 'draft',
			'posts_per_page' => -1,
		)
	) as $wiw_post ) {
		$wiw_hidden = '1' === (string) get_post_meta( $wiw_post->ID, '_e2e_hidden', true );
		if ( 'hide' === $wiw_mode && (string) get_post_meta( $wiw_post->ID, 'wiw_event_start', true ) >= $wiw_now ) {
			update_post_meta( $wiw_post->ID, '_e2e_hidden', '1' );
			wp_update_post(
				array(
					'ID'          => $wiw_post->ID,
					'post_status' => 'draft',
				)
			);
		} elseif ( 'show' === $wiw_mode && $wiw_hidden ) {
			delete_post_meta( $wiw_post->ID, '_e2e_hidden' );
			wp_update_post(
				array(
					'ID'          => $wiw_post->ID,
					'post_status' => 'publish',
				)
			);
		}
	}
	echo wp_json_encode( array( 'mode' => $wiw_mode ) ) . "\n";
	exit( 0 );
}

$wiw_ids = array();
foreach ( $wiw_events as $wiw_slug => $wiw_def ) {
	$wiw_post = wiw_find_post( $wiw_slug, EventPostType::TYPE );
	$wiw_id   = $wiw_post instanceof WP_Post ? $wiw_post->ID : (int) wp_insert_post(
		array(
			'post_type'    => EventPostType::TYPE,
			'post_name'    => $wiw_slug,
			'post_title'   => $wiw_def[0],
			'post_status'  => $wiw_def[2],
			'post_content' => '<p>A fictional programme for the browser tests.</p>',
			'post_excerpt' => 'A fictional event.',
		)
	);
	update_post_meta( $wiw_id, 'wiw_event_start', gmdate( 'Y-m-d H:i', time() + $wiw_def[1] * DAY_IN_SECONDS ) );
	update_post_meta( $wiw_id, 'wiw_event_organiser', 'Fictional Organiser' );
	update_post_meta( $wiw_id, 'wiw_event_registration_url', 'https://organiser.example/register' );
	if ( $wiw_def[3] ) {
		update_post_meta( $wiw_id, 'wiw_event_online', '1' );
	} else {
		update_post_meta( $wiw_id, 'wiw_event_city', 'Lagos' );
		update_post_meta( $wiw_id, 'wiw_event_country', 'Nigeria' );
	}
	$wiw_ids[ $wiw_slug ] = $wiw_id;
}
foreach ( $wiw_stories as $wiw_slug ) {
	$wiw_post = wiw_find_post( $wiw_slug, 'post' );
	$wiw_id   = $wiw_post instanceof WP_Post ? $wiw_post->ID : (int) wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_name'    => $wiw_slug,
			'post_title'   => ucwords( str_replace( '-', ' ', $wiw_slug ) ),
			'post_status'  => 'publish',
			'post_excerpt' => 'A fictional story for the events browser tests.',
			'post_content' => 'Fictional body text.',
		)
	);
	'e2e-evt-story-linked' === $wiw_slug ? update_post_meta( $wiw_id, RelatedEvent::META, $wiw_ids['e2e-evt-upcoming'] ) : delete_post_meta( $wiw_id, RelatedEvent::META );
}
echo wp_json_encode( array_keys( $wiw_ids ) ) . "\n";
