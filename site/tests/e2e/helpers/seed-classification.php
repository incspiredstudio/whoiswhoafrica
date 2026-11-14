<?php
/**
 * Seeds a small fictional classification tree for the classification admin browser test, or removes it with "remove": a sector
 * "E2E Sector" with the industries "E2E Oil" and "E2E Power", an organisation type "E2E Company", a city "E2E City" under Nigeria,
 * and the fixed "unclassified" industry when the site has none yet. Idempotent and safe to run from two browser projects at once:
 * an existing term (found by name under its parent, so a renamed slug is fine) is reused. TEST ENVIRONMENT ONLY: refuses unless the site is local and WIW_E2E_TEST_MODE=1. Prints one line of JSON with the term ids.
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.NamingConventions.PrefixAllGlobals, WordPress.DB

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() || '1' !== getenv( 'WIW_E2E_TEST_MODE' ) ) {
	fwrite( STDERR, "refused: needs a local environment and WIW_E2E_TEST_MODE=1\n" );
	exit( 1 );
}

use WhoIsWho\Core\Directory\Edit\ProfileClassifier;
use WhoIsWho\Core\Directory\PlaceSeeder;
use WhoIsWho\Core\Directory\Taxonomies;

$wiw_remove = static function (): void {
	foreach ( array( Taxonomies::INDUSTRY, Taxonomies::ORG_TYPE, Taxonomies::PLACE ) as $wiw_tax ) {
		$wiw_terms = get_terms(
			array(
				'taxonomy'   => $wiw_tax,
				'hide_empty' => false,
				'search'     => 'E2E',
			)
		);
		foreach ( is_array( $wiw_terms ) ? array_reverse( $wiw_terms ) : array() as $wiw_term ) {
			if ( 0 === strpos( $wiw_term->name, 'E2E' ) ) {
				wp_delete_term( $wiw_term->term_id, $wiw_tax );
			}
		}
	}
};

if ( isset( $argv[1] ) && 'remove' === $argv[1] ) {
	$wiw_remove();
	echo wp_json_encode( array( 'removed' => true ) ) . "\n";
	exit( 0 );
}

$wiw_make = static function ( string $name, string $taxonomy, int $parent_id = 0, string $slug = '' ): int {
	$found = term_exists( $name, $taxonomy, $parent_id );
	if ( is_array( $found ) ) {
		return (int) $found['term_id'];
	}
	$args = array( 'parent' => $parent_id );
	if ( '' !== $slug ) {
		$args['slug'] = $slug;
	}
	$made = wp_insert_term( $name, $taxonomy, $args );
	if ( is_wp_error( $made ) ) {
		$found = term_exists( $name, $taxonomy, $parent_id );
		return is_array( $found ) ? (int) $found['term_id'] : 0;
	}
	return (int) $made['term_id'];
};
$wiw_unc = ProfileClassifier::unclassified_id();
if ( 0 === $wiw_unc ) {
	$wiw_unc = $wiw_make( 'Unclassified', Taxonomies::INDUSTRY, 0, ProfileClassifier::UNCLASSIFIED );
}
$wiw_sector  = $wiw_make( 'E2E Sector', Taxonomies::INDUSTRY, 0, 'e2e-sector' );
$wiw_oil     = $wiw_make( 'E2E Oil', Taxonomies::INDUSTRY, $wiw_sector, 'e2e-oil' );
$wiw_power   = $wiw_make( 'E2E Power', Taxonomies::INDUSTRY, $wiw_sector, 'e2e-power' );
$wiw_company = $wiw_make( 'E2E Company', Taxonomies::ORG_TYPE, 0, 'e2e-company' );
$wiw_nigeria = (int) ( new PlaceSeeder() )->find_country( 'NG' );
$wiw_city    = $wiw_make( 'E2E City', Taxonomies::PLACE, $wiw_nigeria, 'e2e-city' );
update_term_meta( $wiw_city, Taxonomies::META_KIND, Taxonomies::KIND_CITY );

echo wp_json_encode(
	array(
		'unclassified' => $wiw_unc,
		'sector'       => $wiw_sector,
		'oil'          => $wiw_oil,
		'power'        => $wiw_power,
		'company'      => $wiw_company,
		'nigeria'      => $wiw_nigeria,
		'city'         => $wiw_city,
	)
) . "\n";
