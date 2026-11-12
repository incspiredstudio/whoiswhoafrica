<?php
/**
 * Directory lists (REQ-001, REQ-007, REQ-218, REQ-208): the routes /directory, /people, /entrepreneurs, /businesses and
 * /institutions, the template choice, and the helpers the list template uses. Lists are read only through ProfileList, so a
 * profile that is pending review, hidden or removed is never named. Filters, sort and page live in the address.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\ProfileList;
use WhoIsWho\Core\Directory\Taxonomies;

const WIW_LIST_VAR      = 'wiw_list';
const WIW_LIST_RULES_OP = 'wiw_theme_list_rules';

if ( ! function_exists( 'wiw_directory_lists' ) ) {
	/**
	 * The five lists: key => array( path, tab label, page title, lede ).
	 *
	 * @return array<string, array{path: string, tab: string, title: string, lede: string}>
	 */
	function wiw_directory_lists(): array {
		return array(
			'all'           => array(
				'path'  => 'directory',
				'tab'   => __( 'All', 'whoiswho' ),
				'title' => __( 'Directory', 'whoiswho' ),
				'lede'  => __( 'Reviewed people, entrepreneurs, businesses and institutions across Africa, each fact shown with its source.', 'whoiswho' ),
			),
			'people'        => array(
				'path'  => 'people',
				'tab'   => __( 'People', 'whoiswho' ),
				'title' => __( 'People', 'whoiswho' ),
				'lede'  => __( 'Professionals and public figures, with professional facts only.', 'whoiswho' ),
			),
			'entrepreneurs' => array(
				'path'  => 'entrepreneurs',
				'tab'   => __( 'Entrepreneurs', 'whoiswho' ),
				'title' => __( 'Entrepreneurs', 'whoiswho' ),
				'lede'  => __( 'Founders and the ventures they have built.', 'whoiswho' ),
			),
			'businesses'    => array(
				'path'  => 'businesses',
				'tab'   => __( 'Businesses', 'whoiswho' ),
				'title' => __( 'Businesses', 'whoiswho' ),
				'lede'  => __( 'Companies and what they make, sell and do.', 'whoiswho' ),
			),
			'institutions'  => array(
				'path'  => 'institutions',
				'tab'   => __( 'Institutions', 'whoiswho' ),
				'title' => __( 'Institutions', 'whoiswho' ),
				'lede'  => __( 'Universities, associations, public bodies and foundations.', 'whoiswho' ),
			),
		);
	}
}//end if

if ( ! function_exists( 'wiw_directory_url' ) ) {
	/**
	 * The address of a list, with filters and a page.
	 *
	 * @param string               $list_name List key.
	 * @param array<string, mixed> $params    Filters, sort and page.
	 */
	function wiw_directory_url( string $list_name, array $params = array() ): string {
		$lists = wiw_directory_lists();
		$path  = $lists[ $list_name ]['path'] ?? 'directory';
		$page  = max( 1, (int) ( $params['page'] ?? 1 ) );
		unset( $params['page'] );
		$query = array_filter(
			array_map( 'strval', $params ),
			static fn ( string $value ): bool => '' !== $value
		);
		$url   = home_url( '/' . $path . '/' . ( $page > 1 ? 'page/' . $page . '/' : '' ) );
		return array() === $query ? $url : add_query_arg( array_map( 'rawurlencode', $query ), $url );
	}
}

if ( ! function_exists( 'wiw_directory_current_list' ) ) {
	/**
	 * The list key of the current request, or an empty string when it is not a list.
	 */
	function wiw_directory_current_list(): string {
		$path = (string) get_query_var( WIW_LIST_VAR, '' );
		foreach ( wiw_directory_lists() as $key => $list ) {
			if ( $list['path'] === $path || $key === $path ) {
				return $key;
			}
		}
		return '';
	}
}

if ( ! function_exists( 'wiw_directory_params' ) ) {
	/**
	 * Filters, sort and page of the current request, taken from the query variables.
	 *
	 * @param string $list_name List key.
	 * @return array<string, string|int>
	 */
	function wiw_directory_params( string $list_name ): array {
		$params = array();
		foreach ( ProfileList::filters_for( $list_name ) as $name ) {
			$params[ $name ] = sanitize_title( (string) get_query_var( $name, '' ) );
		}
		// The per-list narrowing text (REQ-008, REQ-254): it narrows only this list, never the site search. Handed to ProfileList as q.
		$params['q']    = mb_substr( trim( sanitize_text_field( (string) get_query_var( 'q', '' ) ) ), 0, 80 );
		$sort           = sanitize_key( (string) get_query_var( 'sort', '' ) );
		$params['sort'] = ProfileList::SORT_UPDATED === $sort ? $sort : '';
		$params['page'] = max( 1, (int) get_query_var( 'paged', 1 ) );
		return $params;
	}
}

if ( ! function_exists( 'wiw_directory_filter_options' ) ) {
	/**
	 * Options of one filter as slug => name, from the controlled lists.
	 *
	 * @param string $filter industry, country, city, profession or org_type.
	 * @return array<string, string>
	 */
	function wiw_directory_filter_options( string $filter ): array {
		$map      = array(
			'industry'   => Taxonomies::INDUSTRY,
			'country'    => Taxonomies::PLACE,
			'city'       => Taxonomies::PLACE,
			'profession' => Taxonomies::PROFESSION,
			'org_type'   => Taxonomies::ORG_TYPE,
		);
		$taxonomy = $map[ $filter ] ?? '';
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( Taxonomies::PLACE === $taxonomy ) {
				$kind  = (string) get_term_meta( $term->term_id, Taxonomies::META_KIND, true );
				$match = 'city' === $filter ? Taxonomies::KIND_CITY === $kind : Taxonomies::KIND_CITY !== $kind;
				if ( ! $match ) {
					continue;
				}
			}
			$out[ (string) $term->slug ] = (string) $term->name;
		}
		return $out;
	}
}//end if

if ( ! function_exists( 'wiw_directory_filter_labels' ) ) {
	/**
	 * Control label and "all" text of each filter.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	function wiw_directory_filter_labels(): array {
		return array(
			'industry'   => array( __( 'Industry', 'whoiswho' ), __( 'All industries', 'whoiswho' ) ),
			'country'    => array( __( 'Country', 'whoiswho' ), __( 'All countries', 'whoiswho' ) ),
			'city'       => array( __( 'City', 'whoiswho' ), __( 'All cities', 'whoiswho' ) ),
			'profession' => array( __( 'Profession', 'whoiswho' ), __( 'All professions', 'whoiswho' ) ),
			'org_type'   => array( __( 'Organisation type', 'whoiswho' ), __( 'All organisation types', 'whoiswho' ) ),
		);
	}
}

add_filter(
	'query_vars',
	static function ( array $vars ): array {
		return array_merge( $vars, array( WIW_LIST_VAR, 'country', 'profession', 'org_type', 'sort', 'q' ) );
	}
);

add_action(
	'init',
	static function (): void {
		$paths = implode( '|', array_column( wiw_directory_lists(), 'path' ) );
		add_rewrite_rule( '^(' . $paths . ')/page/([0-9]+)/?$', 'index.php?' . WIW_LIST_VAR . '=$matches[1]&paged=$matches[2]', 'top' );
		add_rewrite_rule( '^(' . $paths . ')/?$', 'index.php?' . WIW_LIST_VAR . '=$matches[1]', 'top' );
		if ( '1' !== get_option( WIW_LIST_RULES_OP ) ) {
			flush_rewrite_rules( false );
			update_option( WIW_LIST_RULES_OP, '1', false );
		}
	},
	7
);

add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		$key = wiw_directory_current_list();
		return '' === $key ? $title : wiw_directory_lists()[ $key ]['title'] . ' | ' . wiw_site_name();
	}
);

add_action(
	'wp',
	static function (): void {
		// A list has no posts of its own, so WordPress marked the request 404 just before this hook: undo that.
		if ( '' !== wiw_directory_current_list() ) {
			global $wp_query;
			$wp_query->is_404 = false;
			status_header( 200 );
		}
	}
);

add_filter(
	'template_include',
	static function ( string $template ): string {
		if ( '' === wiw_directory_current_list() ) {
			return $template;
		}
		$found = locate_template( 'templates/directory.php' );
		return '' !== $found ? $found : $template;
	},
	11
);
