<?php
/**
 * Navigation model shared by the header, the drawer and the footer (REQ-259, REQ-229). One place decides the links, so the
 * Nominate link is hidden from all three while nominations are closed. Wording and order can be changed through filters;
 * the Nominations flag is read only through the plugin's wiw_nominations_open() (never an option read here).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_nominations_are_open' ) ) {
	/**
	 * Whether the Nominate link may show. False when the plugin helper is not available (safe default: closed).
	 */
	function wiw_nominations_are_open(): bool {
		$open = function_exists( 'wiw_nominations_open' ) && true === (bool) wiw_nominations_open();
		/**
		 * Filters whether the Nominate link shows (tests and previews; the flag itself lives in the plugin).
		 *
		 * @param bool $open Open.
		 */
		return (bool) apply_filters( 'wiw_nominations_are_open', $open );
	}
}

if ( ! function_exists( 'wiw_nav_link' ) ) {
	/**
	 * One navigation link.
	 *
	 * @param string $path  Path under the site, for example /people/.
	 * @param string $label Link text.
	 * @param string $note  Short description (mega menu only).
	 * @return array{url: string, label: string, note: string}
	 */
	function wiw_nav_link( string $path, string $label, string $note = '' ): array {
		return array(
			'url'   => home_url( $path ),
			'label' => $label,
			'note'  => $note,
		);
	}
}

if ( ! function_exists( 'wiw_nav_model' ) ) {
	/**
	 * The header, drawer and footer links.
	 *
	 * Keys: directory, awards, stories, events, about (groups with links), cta (Get listed), footer (columns).
	 *
	 * @return array<string, mixed>
	 */
	function wiw_nav_model(): array {
		$open = wiw_nominations_are_open();

		$awards = array(
			wiw_nav_link( '/awards/', __( 'Awards overview', 'whoiswho' ), __( 'How recognition works, from nomination to announcement', 'whoiswho' ) ),
		);
		if ( $open ) {
			$awards[] = wiw_nav_link( '/nominate/', __( 'Nominate', 'whoiswho' ), __( 'Put forward someone who deserves it', 'whoiswho' ) );
		}
		$awards[] = wiw_nav_link( '/awards/nominees/', __( 'Nominees', 'whoiswho' ), __( 'Everyone in the running, by category', 'whoiswho' ) );
		$awards[] = wiw_nav_link( '/awards/winners/', __( 'Winners', 'whoiswho' ), __( 'Every edition, filterable by year and category', 'whoiswho' ) );

		$recognition = array(
			wiw_nav_link( '/awards/', __( 'Awards', 'whoiswho' ) ),
		);
		if ( $open ) {
			$recognition[] = wiw_nav_link( '/nominate/', __( 'Nominate', 'whoiswho' ) );
		}
		$recognition[] = wiw_nav_link( '/awards/winners/', __( 'Winners', 'whoiswho' ) );
		$recognition[] = wiw_nav_link( '/awards/rules/', __( 'Awards rules', 'whoiswho' ) );
		$recognition[] = wiw_nav_link( '/stories/', __( 'Stories', 'whoiswho' ) );
		$recognition[] = wiw_nav_link( '/events/', __( 'Events', 'whoiswho' ) );

		$model = array(
			'directory' => array(
				'label'      => __( 'Directory', 'whoiswho' ),
				'links'      => array(
					wiw_nav_link( '/people/', __( 'People', 'whoiswho' ), __( 'Leaders and professionals on public record', 'whoiswho' ) ),
					wiw_nav_link( '/entrepreneurs/', __( 'Entrepreneurs', 'whoiswho' ), __( 'Founders and the ventures they built', 'whoiswho' ) ),
					wiw_nav_link( '/businesses/', __( 'Businesses', 'whoiswho' ), __( 'Companies, from startups to listed groups', 'whoiswho' ) ),
					wiw_nav_link( '/institutions/', __( 'Institutions', 'whoiswho' ), __( 'Universities, associations and public bodies', 'whoiswho' ) ),
				),
				'more_title' => __( 'By industry', 'whoiswho' ),
				'more'       => wiw_nav_industry_links(),
			),
			'awards'    => array(
				'label'      => __( 'Awards', 'whoiswho' ),
				'links'      => $awards,
				'more_title' => __( 'The rules', 'whoiswho' ),
				'more'       => array( wiw_nav_link( '/awards/rules/', __( 'Eligibility and judging', 'whoiswho' ) ) ),
			),
			'stories'   => array(
				'label' => __( 'Stories', 'whoiswho' ),
				'url'   => home_url( '/stories/' ),
			),
			'events'    => array(
				'label' => __( 'Events', 'whoiswho' ),
				'url'   => home_url( '/events/' ),
			),
			'about'     => array(
				'label'      => __( 'About', 'whoiswho' ),
				'links'      => array(
					wiw_nav_link( '/about/', __( 'About', 'whoiswho' ), __( 'What we are building, and why', 'whoiswho' ) ),
					wiw_nav_link( '/faq/', __( 'FAQ', 'whoiswho' ), __( 'Listings, corrections and removal', 'whoiswho' ) ),
					wiw_nav_link( '/contact/', __( 'Contact', 'whoiswho' ), __( 'Reach the team', 'whoiswho' ) ),
				),
				'more_title' => __( 'Work with us', 'whoiswho' ),
				'more'       => array( wiw_nav_link( '/get-listed/', __( 'Get listed', 'whoiswho' ) ) ),
			),
			'cta'       => wiw_nav_link( '/get-listed/', __( 'Get listed', 'whoiswho' ) ),
			'footer'    => array(
				__( 'Directory', 'whoiswho' )   => array(
					wiw_nav_link( '/people/', __( 'People', 'whoiswho' ) ),
					wiw_nav_link( '/entrepreneurs/', __( 'Entrepreneurs', 'whoiswho' ) ),
					wiw_nav_link( '/businesses/', __( 'Businesses', 'whoiswho' ) ),
					wiw_nav_link( '/institutions/', __( 'Institutions', 'whoiswho' ) ),
					wiw_nav_link( '/industries/', __( 'Industries', 'whoiswho' ) ),
				),
				__( 'Recognition', 'whoiswho' ) => $recognition,
				__( 'Trust', 'whoiswho' )       => array(
					wiw_nav_link( '/complaints/', __( 'Complaints and takedown', 'whoiswho' ) ),
					wiw_nav_link( '/data-rights/', __( 'Your data rights', 'whoiswho' ) ),
					wiw_nav_link( '/faq/', __( 'FAQ', 'whoiswho' ) ),
					wiw_nav_link( '/contact/', __( 'Contact', 'whoiswho' ) ),
				),
				__( 'Legal', 'whoiswho' )       => array(
					wiw_nav_link( '/privacy/', __( 'Privacy policy', 'whoiswho' ) ),
					wiw_nav_link( '/terms/', __( 'Terms', 'whoiswho' ) ),
					wiw_nav_link( '/cookies/', __( 'Cookie policy', 'whoiswho' ) ),
				),
			),
		);

		/**
		 * Filters the navigation model (wording, order, extra links). The Nominate link is added only while nominations are open.
		 *
		 * @param array<string, mixed> $model Model.
		 */
		return apply_filters( 'wiw_nav_model', $model );
	}
}//end if

if ( ! function_exists( 'wiw_nav_industry_links' ) ) {
	/**
	 * Up to five industries for the Directory menu, then All industries.
	 *
	 * @return array<int, array{url: string, label: string, note: string}>
	 */
	function wiw_nav_industry_links(): array {
		$links = array();
		if ( taxonomy_exists( 'wiw_industry' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'wiw_industry',
					'number'     => 5,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'hide_empty' => true,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( $term instanceof WP_Term ) {
					$links[] = wiw_nav_link( '/industries/' . $term->slug . '/', $term->name );
				}
			}
		}
		$links[] = wiw_nav_link( '/industries/', __( 'All industries', 'whoiswho' ) );
		return $links;
	}
}//end if

if ( ! function_exists( 'wiw_is_current_path' ) ) {
	/**
	 * Whether the request is at or below a path, for aria-current.
	 *
	 * @param string $url Link URL.
	 */
	function wiw_is_current_path( string $url ): bool {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$now  = (string) wp_parse_url( home_url( add_query_arg( array() ) ), PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return '' !== $path && $path !== $home && 0 === strpos( trailingslashit( $now ), trailingslashit( $path ) );
	}
}
