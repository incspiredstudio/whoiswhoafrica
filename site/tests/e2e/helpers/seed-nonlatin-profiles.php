<?php
/**
 * Seeds three fictional, reviewed profiles whose names give three kinds of slug (#810): an Arabic name (stored percent-encoded),
 * an accented Latin name (transliterated) and a plain name. TEST ENVIRONMENT ONLY: refuses unless the site is a local environment.
 * Idempotent. Fictional names only, no personal data. Prints one line of JSON: label => {name, slug}.
 *
 * Usage: WIW_SECRETS_FILE=... php tests/e2e/helpers/seed-nonlatin-profiles.php [remove]
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.NamingConventions.PrefixAllGlobals

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() ) {
	fwrite( STDERR, "seed refused: not a local environment\n" );
	exit( 1 );
}

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Review\StateMachine;

$wiw_names = array(
	'arabic'   => "\u{645}\u{624}\u{633}\u{633}\u{629} \u{627}\u{644}\u{62a}\u{62c}\u{631}\u{628}\u{629} Qwv",
	'accented' => "Soci\u{e9}t\u{e9} d'\u{c9}preuve C\u{f4}te Qwv",
	'plain'    => 'Plain Trial Holdings Qwv',
);

/**
 * The profile with this exact title, if any.
 *
 * @param string $name Title.
 */
function wiw_seed_find( string $name ): ?WP_Post {
	$found = get_posts(
		array(
			'post_type'        => ProfileType::POST_TYPE,
			'post_status'      => 'any',
			'title'            => $name,
			'posts_per_page'   => 1,
			'suppress_filters' => true,
		)
	);
	return $found[0] ?? null;
}

if ( isset( $argv[1] ) && 'remove' === $argv[1] ) {
	global $wpdb;
	foreach ( $wiw_names as $wiw_name ) {
		$wiw_post = wiw_seed_find( $wiw_name );
		if ( $wiw_post instanceof WP_Post ) {
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $wiw_post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_delete_post( $wiw_post->ID, true );
		}
	}
	echo "{}\n";
	exit( 0 );
}

$wiw_out     = array();
$wiw_machine = new StateMachine();
foreach ( $wiw_names as $wiw_label => $wiw_name ) {
	$wiw_post = wiw_seed_find( $wiw_name );
	if ( ! $wiw_post instanceof WP_Post ) {
		$wiw_id = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $wiw_name,
			)
		);
		$wiw_machine->enter( $wiw_id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$wiw_machine->transition( $wiw_id, PubState::PUBLISHED_REVIEWED, '', 1 );
		$wiw_post = get_post( $wiw_id );
	}
	$wiw_out[ $wiw_label ] = array(
		'name' => $wiw_name,
		'slug' => (string) $wiw_post->post_name,
	);
}
echo wp_json_encode( $wiw_out ) . "\n";
