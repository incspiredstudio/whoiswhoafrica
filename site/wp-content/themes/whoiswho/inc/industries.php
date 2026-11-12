<?php
/**
 * Industries (REQ-007, REQ-009): the routes /industries and /industries/{slug}, the template choice and the page title. The
 * theme only routes and renders: counts and profile lists come from ProfileList (reviewed profiles only), stories from
 * PublicStory. An unknown industry stays a 404.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

const WIW_INDUSTRIES_VAR = 'wiw_industries';
const WIW_INDUSTRY_VAR   = 'wiw_industry_slug';
const WIW_INDUSTRY_RULES = 'wiw_theme_industry_rules';

if ( ! function_exists( 'wiw_industry_term' ) ) {
	/**
	 * The industry term of the current request, or null for the index and for an unknown slug.
	 */
	function wiw_industry_term(): ?WP_Term {
		$slug = sanitize_title( (string) get_query_var( WIW_INDUSTRY_VAR, '' ) );
		if ( '' === $slug || ! taxonomy_exists( 'wiw_industry' ) ) {
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
