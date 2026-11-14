<?php
/**
 * Seeds fictional profiles for the profile display spec (REQ-009, REQ-203, REQ-144): a reviewed person with a CC BY portrait whose
 * credit repeats the licence (issue 829), and one with a CC0 portrait. Both carry sourced key facts, and a country, sector and
 * industry from the classification tree. Also switches the two profile display settings for the spec.
 * TEST ENVIRONMENT ONLY: it refuses unless the site is a local environment. Idempotent. Fictional names only, no personal data.
 * Prints one line of JSON.
 *
 * Usage: WIW_SECRETS_FILE=... php tests/e2e/helpers/seed-profile-display.php [seed | set <credit> <facts> | reset | remove]
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

use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateMachine;

$wiw_cmd      = $argv[1] ?? 'seed';
$wiw_profiles = array(
	'by'  => array(
		'slug'  => 'e2e-display-cc-by',
		'name'  => 'E2E Display Adaeze Okonkwo-Beaumont',
		'image' => array(
			ImageMeta::KIND        => 'photo',
			ImageMeta::ATTRIBUTION => 'Fictional Studio Lagos, CC BY 4.0',
			ImageMeta::LICENCE     => 'CC-BY-4.0',
			ImageMeta::LICENCE_URL => 'https://creativecommons.org/licenses/by/4.0/',
			ImageMeta::SOURCE_URL  => 'https://commons.wikimedia.org/wiki/Special:FilePath/Example.jpg',
		),
	),
	'cc0' => array(
		'slug'  => 'e2e-display-cc0',
		'name'  => 'E2E Display Public Domain Person',
		'image' => array(
			ImageMeta::KIND    => 'photo',
			ImageMeta::CREATOR => 'Fictional Archive',
			ImageMeta::LICENCE => 'CC0-1.0',
		),
	),
);

if ( 'set' === $wiw_cmd ) {
	update_option( SettingsService::OPTION_PREFIX . SettingsService::PROFILE_IMAGE_CREDIT, (string) ( $argv[2] ?? 'compact' ), false );
	update_option( SettingsService::OPTION_PREFIX . SettingsService::PROFILE_KEY_FACTS, (string) ( $argv[3] ?? 'hidden' ), false );
	echo "{}\n";
	exit( 0 );
}
if ( 'reset' === $wiw_cmd ) {
	delete_option( SettingsService::OPTION_PREFIX . SettingsService::PROFILE_IMAGE_CREDIT );
	delete_option( SettingsService::OPTION_PREFIX . SettingsService::PROFILE_KEY_FACTS );
	echo "{}\n";
	exit( 0 );
}
if ( 'remove' === $wiw_cmd ) {
	global $wpdb;
	foreach ( $wiw_profiles as $wiw_def ) {
		$wiw_found = get_page_by_path( $wiw_def['slug'], OBJECT, ProfileType::POST_TYPE );
		if ( $wiw_found instanceof WP_Post ) {
			$wiw_image = (int) get_post_meta( $wiw_found->ID, PublicProfile::IMAGE_META, true );
			if ( $wiw_image > 0 ) {
				wp_delete_attachment( $wiw_image, true );
			}
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $wiw_found->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $wiw_found->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_delete_post( $wiw_found->ID, true );
		}
	}
	echo "{}\n";
	exit( 0 );
}

$wiw_lock = fopen( sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wiw-seed-profile-display.lock', 'c' );
flock( $wiw_lock, LOCK_EX );

/**
 * A term by slug, created when missing.
 *
 * @param string $name      Name.
 * @param string $slug      Slug.
 * @param string $taxonomy  Taxonomy.
 * @param int    $parent_id Parent term id.
 */
function wiw_seed_display_term( string $name, string $slug, string $taxonomy, int $parent_id = 0 ): int {
	$found = get_term_by( 'slug', $slug, $taxonomy );
	if ( $found instanceof WP_Term ) {
		return (int) $found->term_id;
	}
	$made = wp_insert_term(
		$name,
		$taxonomy,
		array(
			'slug'   => $slug,
			'parent' => $parent_id,
		)
	);
	return is_array( $made ) ? (int) $made['term_id'] : 0;
}

/**
 * A fictional portrait: a warm gradient JPEG, 600 by 800 pixels, with its image sizes.
 *
 * @param array<string, string> $meta Credit meta.
 */
function wiw_seed_display_image( array $meta ): int {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$dir  = wp_upload_dir();
	$file = $dir['path'] . '/e2e-display-' . bin2hex( random_bytes( 3 ) ) . '.jpg';
	$im   = imagecreatetruecolor( 600, 800 );
	for ( $y = 0; $y < 800; $y++ ) {
		$t = $y / 800;
		imageline( $im, 0, $y, 599, $y, imagecolorallocate( $im, (int) ( 70 + 120 * $t ), (int) ( 60 + 60 * $t ), (int) ( 55 + 20 * $t ) ) );
	}
	imagejpeg( $im, $file, 85 );
	$id = (int) wp_insert_attachment(
		array(
			'post_title'     => 'Fictional portrait',
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
		),
		$file
	);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	update_post_meta( $id, '_wp_attachment_image_alt', 'Fictional portrait' );
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return $id;
}

$wiw_sector   = wiw_seed_display_term( 'E2E Financial services', 'e2e-financial-services', Taxonomies::INDUSTRY );
$wiw_industry = wiw_seed_display_term( 'E2E Banking', 'e2e-banking', Taxonomies::INDUSTRY, $wiw_sector );
$wiw_country  = wiw_seed_display_term( 'E2E Testland', 'e2e-testland', Taxonomies::PLACE );
update_term_meta( $wiw_country, 'wiw_iso_code', 'ZZ' );
update_term_meta( $wiw_country, 'wiw_place_kind', 'country' );

$wiw_machine = new StateMachine();
$wiw_out     = array();
foreach ( $wiw_profiles as $wiw_key => $wiw_def ) {
	$wiw_out[ $wiw_key ] = $wiw_def['slug'];
	if ( get_page_by_path( $wiw_def['slug'], OBJECT, ProfileType::POST_TYPE ) instanceof WP_Post ) {
		continue;
	}
	$wiw_id = (int) wp_insert_post(
		array(
			'post_type'  => ProfileType::POST_TYPE,
			'post_name'  => $wiw_def['slug'],
			'post_title' => $wiw_def['name'],
		)
	);
	$wiw_machine->enter( $wiw_id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'individual' ) );
	$wiw_store = new ProfileFields();
	$wiw_prov  = new ProvenanceRepository();
	foreach (
		array(
			'current_title'        => 'Managing Director',
			'current_organisation' => 'Fictional Bank of Testland',
			'industry'             => 'Banking',
			'bio'                  => "A fictional person used only by the browser tests.\n\nShe names no real person.",
		) as $wiw_field => $wiw_value
	) {
		$wiw_store->set( $wiw_id, $wiw_field, $wiw_value );
		$wiw_prov->record(
			$wiw_id,
			array(
				'field'        => $wiw_field,
				'source_url'   => 'https://www.example.org/registry/entry',
				'retrieved_at' => '2026-11-10T09:00:00Z',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
	}
	wp_set_object_terms( $wiw_id, array( $wiw_industry ), Taxonomies::INDUSTRY );
	wp_set_object_terms( $wiw_id, array( $wiw_country ), Taxonomies::PLACE );
	update_post_meta( $wiw_id, '_wiw_primary_industry', $wiw_industry );
	update_post_meta( $wiw_id, '_wiw_primary_country', $wiw_country );
	update_post_meta( $wiw_id, PublicProfile::IMAGE_META, wiw_seed_display_image( $wiw_def['image'] ) );
	$wiw_machine->transition( $wiw_id, PubState::PUBLISHED_REVIEWED, '', 1 );
}//end foreach
echo wp_json_encode( $wiw_out ) . "\n";
