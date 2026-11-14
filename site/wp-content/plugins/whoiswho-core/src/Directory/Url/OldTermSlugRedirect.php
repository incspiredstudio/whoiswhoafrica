<?php
/**
 * Old list addresses keep working after a term's slug changes (REQ-009, REQ-006): a front-end address whose industry=, country=,
 * city= or org_type= names a slug that no term has any more, but that a term kept in its wiw_old_slugs meta (see
 * Admin\TermFields), answers with a 301 to the same address with the term's current slug. The industries pages
 * (/industries/<slug>/, a sector or an industry, and their later pages) redirect the same way.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Url;

use WhoIsWho\Core\Directory\Admin\TermFields;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Redirect from an old term slug.
 */
final class OldTermSlugRedirect {

	/**
	 * Query parameter => the taxonomy its slug belongs to.
	 */
	public const PARAMS = array(
		'industry' => Taxonomies::INDUSTRY,
		'country'  => Taxonomies::PLACE,
		'city'     => Taxonomies::PLACE,
		'org_type' => Taxonomies::ORG_TYPE,
	);

	/**
	 * Public paths that carry an industry slug (a sector or an industry): the industries pages, with their later pages.
	 */
	private const INDUSTRY_PATH = '#^/industries/([^/]+)(/page/[0-9]+)?/?$#';

	/**
	 * Register the hook.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle' ), 2 );
	}

	/**
	 * The template_redirect handler.
	 */
	public function handle(): void {
		if ( is_admin() ) {
			return;
		}
		$target = $this->target( Request::from_globals(), (string) add_query_arg( array() ) );
		if ( null !== $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}

	/**
	 * Where an address should go, or null when none of its slugs is an old one.
	 *
	 * @param Request $request Request (the query parameters).
	 * @param string  $url     The address asked for.
	 */
	public function target( Request $request, string $url ): ?string {
		$moved   = $this->moved_path( $url );
		$url     = $moved ?? $url;
		$changes = array();
		foreach ( self::PARAMS as $param => $taxonomy ) {
			$slug = sanitize_title( $request->string( $param ) );
			if ( '' === $slug || get_term_by( 'slug', $slug, $taxonomy ) instanceof \WP_Term ) {
				continue;
			}
			$current = self::current_slug( $slug, $taxonomy );
			if ( null !== $current ) {
				$changes[ $param ] = $current;
			}
		}
		if ( array() === $changes ) {
			return $moved;
		}
		return add_query_arg( $changes, $url );
	}

	/**
	 * The same address with the current slug when its path is an industries page under an old slug (query kept), else null.
	 *
	 * @param string $url The address asked for.
	 */
	private function moved_path( string $url ): ?string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$home = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		if ( '' !== $home && 0 === strpos( $path, $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		if ( 1 !== preg_match( self::INDUSTRY_PATH, $path, $found ) ) {
			return null;
		}
		$slug = sanitize_title( urldecode( $found[1] ) );
		if ( '' === $slug || get_term_by( 'slug', $slug, Taxonomies::INDUSTRY ) instanceof \WP_Term ) {
			return null;
		}
		$current = self::current_slug( $slug, Taxonomies::INDUSTRY );
		if ( null === $current ) {
			return null;
		}
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		return home_url( '/industries/' . $current . ( $found[2] ?? '' ) . '/' ) . ( '' !== $query ? '?' . $query : '' );
	}

	/**
	 * The current slug of the term that used to have a slug.
	 *
	 * @param string $old      Old slug.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function current_slug( string $old, string $taxonomy ): ?string {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 1,
				'meta_key'   => TermFields::META_OLD_SLUGS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- only when a slug matches no term.
				'meta_value' => $old, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
			)
		);
		return is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof \WP_Term ? $terms[0]->slug : null;
	}
}
