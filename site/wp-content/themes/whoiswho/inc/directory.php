<?php
/**
 * Directory lists (REQ-001, REQ-007, REQ-218, REQ-208): the routes /directory, /people, /businesses and /institutions, the
 * template choice, and the helpers the list template uses. People holds individuals and entrepreneurs (D-072); the old
 * /entrepreneurs/ address and a ?type=entrepreneur list address answer 301 to People. Lists are read only through ProfileList, so a
 * profile that is pending review, hidden or removed is never named. Filters, sort and page live in the address.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\ProfileList;
use WhoIsWho\Core\Directory\Taxonomies;

const WIW_LIST_VAR       = 'wiw_list';
const WIW_LIST_RULES_OP  = 'wiw_theme_list_rules';
const WIW_LIST_RULES_VER = '2';

if ( ! function_exists( 'wiw_directory_lists' ) ) {
	/**
	 * The four lists: key => array( path, tab label, page title, lede ).
	 *
	 * @return array<string, array{path: string, tab: string, title: string, lede: string}>
	 */
	function wiw_directory_lists(): array {
		return array(
			'all'          => array(
				'path'  => 'directory',
				'tab'   => __( 'All', 'whoiswho' ),
				'title' => __( 'The directory', 'whoiswho' ),
				'lede'  => __( 'Reviewed people, businesses and institutions across Africa, each fact shown with its source.', 'whoiswho' ),
			),
			'people'       => array(
				'path'  => 'people',
				'tab'   => __( 'People', 'whoiswho' ),
				'title' => __( 'People', 'whoiswho' ),
				'lede'  => __( 'Leaders, founders and changemakers across Africa, with professional facts only.', 'whoiswho' ),
			),
			'businesses'   => array(
				'path'  => 'businesses',
				'tab'   => __( 'Businesses', 'whoiswho' ),
				'title' => __( 'Businesses', 'whoiswho' ),
				'lede'  => __( 'Companies and what they make, sell and do.', 'whoiswho' ),
			),
			'institutions' => array(
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
		$params['view'] = 'list' === sanitize_key( (string) get_query_var( 'view', '' ) ) ? 'list' : '';
		$params['page'] = max( 1, (int) get_query_var( 'paged', 1 ) );
		return $params;
	}
}//end if

if ( ! function_exists( 'wiw_directory_filter_options' ) ) {
	/**
	 * Options of one filter as slug => name, from the controlled lists. Industries are grouped by sector (REQ-009): each sector is
	 * a group (key "group:<sector slug>") whose first option is the sector itself (it lists every industry under it), followed by
	 * its industries. Unclassified is not public navigation and is left out.
	 *
	 * @param string $filter industry, country, city, profession or org_type.
	 * @return array<string, string|array{label: string, options: array<string, string>}>
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
		if ( Taxonomies::INDUSTRY === $taxonomy ) {
			return wiw_directory_industry_groups( is_array( $terms ) ? $terms : array() );
		}
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

if ( ! function_exists( 'wiw_directory_industry_groups' ) ) {
	/**
	 * The industry tree as select groups (REQ-009): sector => its own option ("All <sector>") then its industries, sectors and
	 * industries each in name order. Unclassified and industries without a known sector are left out.
	 *
	 * @param array<int, \WP_Term> $terms Industry terms (sectors and industries).
	 * @return array<string, array{label: string, options: array<string, string>}>
	 */
	function wiw_directory_industry_groups( array $terms ): array {
		$skip    = class_exists( Classification::class ) ? Classification::unclassified_slug() : 'unclassified';
		$sectors = array();
		$leaves  = array();
		foreach ( $terms as $term ) {
			if ( $skip === $term->slug ) {
				continue;
			}
			if ( 0 === (int) $term->parent ) {
				$sectors[ (int) $term->term_id ] = $term;
			} else {
				$leaves[ (int) $term->parent ][ (string) $term->slug ] = (string) $term->name;
			}
		}
		uasort( $sectors, static fn ( \WP_Term $a, \WP_Term $b ): int => strcasecmp( $a->name, $b->name ) );
		$out = array();
		foreach ( $sectors as $id => $sector ) {
			$options = $leaves[ $id ] ?? array();
			asort( $options, SORT_NATURAL | SORT_FLAG_CASE );
			$out[ 'group:' . $sector->slug ] = array(
				'label'   => (string) $sector->name,
				/* translators: %s: sector name. */
				'options' => array( (string) $sector->slug => sprintf( __( 'All %s', 'whoiswho' ), (string) $sector->name ) ) + $options,
			);
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

if ( ! function_exists( 'wiw_directory_legacy_target' ) ) {
	/**
	 * Where an old Entrepreneurs address should go (REQ-001, D-072): /entrepreneurs/ (and its pages) and a directory list address
	 * with ?type=entrepreneur go to the People equivalent, keeping the other query arguments. Any other address stays.
	 *
	 * @param string                   $request_path Path of the request, with or without the site's own folder.
	 * @param array<int|string, mixed> $query        Query arguments of the request (numeric names are dropped).
	 * @return string Address to redirect to, or an empty string for no redirect.
	 */
	function wiw_directory_legacy_target( string $request_path, array $query ): string {
		$path = trim( $request_path, '/' );
		$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home && ( $path === $home || str_starts_with( $path, $home . '/' ) ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		$type = isset( $query['type'] ) && is_string( $query['type'] ) ? sanitize_key( $query['type'] ) : '';
		$page = 1;
		if ( 1 === preg_match( '#^entrepreneurs(?:/page/([0-9]+))?$#', $path, $match ) ) {
			$page = isset( $match[1] ) ? max( 1, (int) $match[1] ) : 1;
		} elseif ( ! in_array( $type, array( 'entrepreneur', 'entrepreneurs' ), true ) || 1 !== preg_match( '#^(' . implode( '|', array_column( wiw_directory_lists(), 'path' ) ) . ')(?:/page/[0-9]+)?$#', $path ) ) {
			return '';
		}
		unset( $query['type'], $query['page'], $query['paged'] );
		$keep = array();
		foreach ( $query as $name => $value ) {
			if ( is_string( $name ) && is_scalar( $value ) ) {
				$keep[ sanitize_key( $name ) ] = sanitize_text_field( (string) $value );
			}
		}
		return wiw_directory_url( 'people', array_merge( $keep, array( 'page' => $page ) ) );
	}
}//end if

add_action(
	'template_redirect',
	static function (): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( ! in_array( strtoupper( $method ), array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		$uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only split into path and query; each value is sanitised in wiw_directory_legacy_target.
		$query = array();
		wp_parse_str( (string) wp_parse_url( $uri, PHP_URL_QUERY ), $query );
		$to = wiw_directory_legacy_target( (string) wp_parse_url( $uri, PHP_URL_PATH ), $query );
		if ( '' !== $to ) {
			wp_safe_redirect( $to, 301 );
			exit;
		}
	},
	0
);

add_filter(
	'query_vars',
	static function ( array $vars ): array {
		return array_merge( $vars, array( WIW_LIST_VAR, 'country', 'profession', 'org_type', 'sort', 'q', 'view' ) );
	}
);

add_action(
	'init',
	static function (): void {
		$paths = implode( '|', array_column( wiw_directory_lists(), 'path' ) );
		add_rewrite_rule( '^(' . $paths . ')/page/([0-9]+)/?$', 'index.php?' . WIW_LIST_VAR . '=$matches[1]&paged=$matches[2]', 'top' );
		add_rewrite_rule( '^(' . $paths . ')/?$', 'index.php?' . WIW_LIST_VAR . '=$matches[1]', 'top' );
		// The version changes when the set of lists changes (2: Entrepreneurs folded into People, D-072), so the rules are rebuilt once.
		if ( WIW_LIST_RULES_VER !== get_option( WIW_LIST_RULES_OP ) ) {
			flush_rewrite_rules( false );
			update_option( WIW_LIST_RULES_OP, WIW_LIST_RULES_VER, false );
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
