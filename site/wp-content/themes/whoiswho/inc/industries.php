<?php
/**
 * Industries (REQ-007, REQ-009): the routes /industries and /industries/{slug}, the template choice and the page title. The
 * theme only routes and renders: counts and profile lists come from ProfileList (reviewed profiles only), stories from
 * PublicStory. An unknown industry stays a 404, and so does Unclassified (noindexed too), which is never public navigation.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

const WIW_INDUSTRIES_VAR = 'wiw_industries';
const WIW_INDUSTRY_VAR   = 'wiw_industry_slug';
const WIW_INDUSTRY_RULES = 'wiw_theme_industry_rules';

if ( ! function_exists( 'wiw_is_unclassified_industry' ) ) {
	/**
	 * Whether an industry slug is Unclassified, which is never public navigation, a public list or a page (REQ-009).
	 *
	 * @param string $slug Industry slug.
	 */
	function wiw_is_unclassified_industry( string $slug ): bool {
		$unclassified = class_exists( \WhoIsWho\Core\Directory\Classification::class ) ? \WhoIsWho\Core\Directory\Classification::unclassified_slug() : 'unclassified';
		return '' !== $slug && $unclassified === $slug;
	}
}

if ( ! function_exists( 'wiw_industry_term' ) ) {
	/**
	 * The industry term of the current request, or null for the index, for an unknown slug and for Unclassified (a 404).
	 */
	function wiw_industry_term(): ?WP_Term {
		$slug = sanitize_title( (string) get_query_var( WIW_INDUSTRY_VAR, '' ) );
		if ( '' === $slug || ! taxonomy_exists( 'wiw_industry' ) || wiw_is_unclassified_industry( $slug ) ) {
			return null;
		}
		$term = get_term_by( 'slug', $slug, 'wiw_industry' );
		return $term instanceof WP_Term ? $term : null;
	}
}

if ( ! function_exists( 'wiw_industries_view' ) ) {
	/**
	 * Which industries view the request is: index, page or empty string.
	 */
	function wiw_industries_view(): string {
		if ( '' !== (string) get_query_var( WIW_INDUSTRIES_VAR, '' ) ) {
			return 'index';
		}
		return null !== wiw_industry_term() ? 'page' : '';
	}
}

add_filter(
	'query_vars',
	static function ( array $vars ): array {
		return array_merge( $vars, array( WIW_INDUSTRIES_VAR, WIW_INDUSTRY_VAR ) );
	}
);

add_action(
	'init',
	static function (): void {
		add_rewrite_rule( '^industries/?$', 'index.php?' . WIW_INDUSTRIES_VAR . '=1', 'top' );
		add_rewrite_rule( '^industries/([^/]+)/page/([0-9]+)/?$', 'index.php?' . WIW_INDUSTRY_VAR . '=$matches[1]&paged=$matches[2]', 'top' );
		add_rewrite_rule( '^industries/([^/]+)/?$', 'index.php?' . WIW_INDUSTRY_VAR . '=$matches[1]', 'top' );
		if ( '1' !== get_option( WIW_INDUSTRY_RULES ) ) {
			flush_rewrite_rules( false );
			update_option( WIW_INDUSTRY_RULES, '1', false );
		}
	},
	7
);

add_action(
	'wp',
	static function (): void {
		// These pages have no posts of their own, so WordPress marked the request 404 just before this hook: undo that.
		global $wp_query;
		if ( '' !== wiw_industries_view() ) {
			$wp_query->is_404 = false;
			status_header( 200 );
		} elseif ( '' !== (string) get_query_var( WIW_INDUSTRY_VAR, '' ) ) {
			// An industry that does not exist.
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
);

if ( ! function_exists( 'wiw_industry_robots' ) ) {
	/**
	 * Robots directives: noindex for /industries/unclassified/ if anything renders it (it answers 404). Hooked to wp_robots.
	 *
	 * @param array<string, bool|string> $robots Directives.
	 * @return array<string, bool|string>
	 */
	function wiw_industry_robots( array $robots ): array {
		if ( wiw_is_unclassified_industry( sanitize_title( (string) get_query_var( WIW_INDUSTRY_VAR, '' ) ) ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
}
add_filter( 'wp_robots', 'wiw_industry_robots' );

add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		$view = wiw_industries_view();
		if ( '' === $view ) {
			return $title;
		}
		$term = wiw_industry_term();
		return ( null !== $term ? $term->name : __( 'Industries', 'whoiswho' ) ) . ' | ' . wiw_site_name();
	}
);

add_filter(
	'template_include',
	static function ( string $template ): string {
		if ( '' === wiw_industries_view() ) {
			return $template;
		}
		$found = locate_template( 'templates/industries.php' );
		return '' !== $found ? $found : $template;
	},
	11
);

if ( ! function_exists( 'wiw_industry_sectors' ) ) {
	/**
	 * The industries index rows grouped by sector (REQ-009): sectors in name order, each with its count and its industries in
	 * name order. Industries whose sector is not in the rows are left out (unclassified is never in them).
	 *
	 * @param array<int, array{term_id: int, slug: string, name: string, count: int, parent: int}> $rows From ProfileList::industry_counts().
	 * @return array<int, array{slug: string, name: string, count: int, industries: array<int, array{term_id: int, slug: string, name: string, count: int, parent: int}>}>
	 */
	function wiw_industry_sectors( array $rows ): array {
		$sectors = array();
		foreach ( $rows as $row ) {
			if ( 0 === $row['parent'] ) {
				$sectors[ $row['term_id'] ] = array(
					'slug'       => $row['slug'],
					'name'       => $row['name'],
					'count'      => $row['count'],
					'industries' => array(),
				);
			}
		}
		foreach ( $rows as $row ) {
			if ( 0 !== $row['parent'] && isset( $sectors[ $row['parent'] ] ) ) {
				$sectors[ $row['parent'] ]['industries'][] = $row;
			}
		}
		foreach ( $sectors as $id => $sector ) {
			usort( $sectors[ $id ]['industries'], static fn ( array $a, array $b ): int => strcasecmp( $a['name'], $b['name'] ) );
		}
		usort( $sectors, static fn ( array $a, array $b ): int => strcasecmp( $a['name'], $b['name'] ) );
		return $sectors;
	}
}//end if

if ( ! function_exists( 'wiw_sector_industries' ) ) {
	/**
	 * The industries of one sector that have public profiles, in name order (REQ-009, REQ-007): the chips on a sector page.
	 *
	 * @param array<int, array{term_id: int, slug: string, name: string, count: int, parent: int}> $rows      From ProfileList::industry_counts().
	 * @param int                                                                                  $sector_id The sector's term id.
	 * @return list<array{slug: string, name: string, count: int, url: string}>
	 */
	function wiw_sector_industries( array $rows, int $sector_id ): array {
		$out = array();
		foreach ( $rows as $row ) {
			if ( $sector_id > 0 && $sector_id === $row['parent'] && $row['count'] > 0 ) {
				$out[] = array(
					'slug'  => $row['slug'],
					'name'  => $row['name'],
					'count' => $row['count'],
					'url'   => home_url( '/industries/' . $row['slug'] . '/' ),
				);
			}
		}
		usort( $out, static fn ( array $a, array $b ): int => strcasecmp( $a['name'], $b['name'] ) );
		return $out;
	}
}//end if
