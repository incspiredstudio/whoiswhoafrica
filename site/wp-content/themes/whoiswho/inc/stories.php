<?php
/**
 * Stories list (REQ-029, REQ-249, REQ-026, REQ-263, REQ-257): the route /stories (with /stories/page/N), the template choice, the
 * story-card helpers and the 16:9 cover crop. The theme reads stories only through PublicStory, which never returns a draft,
 * scheduled, password-protected or removed story. Filters (type, industry) and the page live in the address.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

const WIW_STORIES_VAR   = 'wiw_stories';
const WIW_STORIES_RULES = 'wiw_theme_stories_rules';

if ( ! function_exists( 'wiw_stories_url' ) ) {
	/**
	 * The address of the stories list with filters and a page.
	 *
	 * @param array<string, mixed> $params type, industry and page.
	 */
	function wiw_stories_url( array $params = array() ): string {
		$page  = max( 1, (int) ( $params['page'] ?? 1 ) );
		$query = array_filter(
			array(
				'type'     => (string) ( $params['type'] ?? '' ),
				'industry' => (string) ( $params['industry'] ?? '' ),
			),
			static fn ( string $value ): bool => '' !== $value
		);
		$url   = home_url( '/stories/' . ( $page > 1 ? 'page/' . $page . '/' : '' ) );
		return array() === $query ? $url : add_query_arg( array_map( 'rawurlencode', $query ), $url );
	}
}

if ( ! function_exists( 'wiw_stories_params' ) ) {
	/**
	 * Type, industry and page of the current request, from the query variables.
	 *
	 * @return array{type: string, industry: string, page: int}
	 */
	function wiw_stories_params(): array {
		return array(
			'type'     => sanitize_key( (string) get_query_var( 'type', '' ) ),
			'industry' => sanitize_title( (string) get_query_var( 'industry', '' ) ),
			'page'     => max( 1, (int) get_query_var( 'paged', 1 ) ),
		);
	}
}

if ( ! function_exists( 'wiw_story_types' ) ) {
	/**
	 * Story types as slug => label, in the order of the tabs.
	 *
	 * @return array<string, string>
	 */
	function wiw_story_types(): array {
		return array(
			'interview'    => __( 'Interview', 'whoiswho' ),
			'feature'      => __( 'Feature', 'whoiswho' ),
			'spotlight'    => __( 'Spotlight', 'whoiswho' ),
			'opinion'      => __( 'Opinion', 'whoiswho' ),
			'announcement' => __( 'Announcement', 'whoiswho' ),
			'news'         => __( 'News', 'whoiswho' ),
		);
	}
}

if ( ! function_exists( 'wiw_story_industry_options' ) ) {
	/**
	 * Industries as slug => name, for the Industry filter.
	 *
	 * @return array<string, string>
	 */
	function wiw_story_industry_options(): array {
		if ( ! taxonomy_exists( 'wiw_industry' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'wiw_industry',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$out[ (string) $term->slug ] = (string) $term->name;
		}
		return $out;
	}
}//end if

if ( ! function_exists( 'wiw_story_date' ) ) {
	/**
	 * Readable date of a story card ("12 Nov 2026").
	 *
	 * @param array<string, mixed> $story Story card from PublicStory.
	 */
	function wiw_story_date( array $story ): string {
		$time = strtotime( (string) ( $story['date'] ?? '' ) );
		return false === $time ? '' : wp_date( 'j M Y', $time );
	}
}

if ( ! function_exists( 'wiw_story_cover' ) ) {
	/**
	 * Cover image of a story card at a 16:9 crop, or an empty string when the story has none (the card then shows a plain block).
	 *
	 * @param array<string, mixed> $story Story card from PublicStory.
	 * @param string               $sizes The sizes attribute.
	 * @param bool                 $eager Load at once (the lead tile), else lazily.
	 */
	function wiw_story_cover( array $story, string $sizes, bool $eager = false ): string {
		if ( empty( $story['cover'] ) || empty( $story['id'] ) ) {
			return '';
		}
		$html = get_the_post_thumbnail(
			(int) $story['id'],
			'wiw-story',
			array(
				'class'         => 'scard-img',
				'alt'           => '',
				'sizes'         => $sizes,
				'loading'       => $eager ? 'eager' : 'lazy',
				'decoding'      => 'async',
				'fetchpriority' => $eager ? 'high' : 'auto',
			)
		);
		return $html;
	}
}//end if

add_action(
	'after_setup_theme',
	static function (): void {
		// 16:9 crop for story covers, with a larger and a smaller copy so the browser can choose (REQ-042).
		add_image_size( 'wiw-story', 960, 540, true );
		add_image_size( 'wiw-story-sm', 480, 270, true );
		add_image_size( 'wiw-story-lg', 1440, 810, true );
	}
);

add_filter(
	'query_vars',
	static function ( array $vars ): array {
		return array_merge( $vars, array( WIW_STORIES_VAR, 'type' ) );
	}
);

add_action(
	'init',
	static function (): void {
		add_rewrite_rule( '^stories/page/([0-9]+)/?$', 'index.php?' . WIW_STORIES_VAR . '=1&paged=$matches[1]', 'top' );
		add_rewrite_rule( '^stories/?$', 'index.php?' . WIW_STORIES_VAR . '=1', 'top' );
		if ( '1' !== get_option( WIW_STORIES_RULES ) ) {
			flush_rewrite_rules( false );
			update_option( WIW_STORIES_RULES, '1', false );
		}
	},
	7
);

if ( ! function_exists( 'wiw_is_stories_list' ) ) {
	/**
	 * Whether the request is the stories list.
	 */
	function wiw_is_stories_list(): bool {
		return '' !== (string) get_query_var( WIW_STORIES_VAR, '' );
	}
}

add_action(
	'wp',
	static function (): void {
		// The list has no posts of its own, so WordPress marked the request 404 just before this hook: undo that.
		if ( wiw_is_stories_list() ) {
			global $wp_query;
			$wp_query->is_404 = false;
			status_header( 200 );
		}
	}
);

add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		return wiw_is_stories_list() ? __( 'Stories', 'whoiswho' ) . ' | ' . wiw_site_name() : $title;
	}
);

add_filter(
	'template_include',
	static function ( string $template ): string {
		if ( ! wiw_is_stories_list() ) {
			return $template;
		}
		$found = locate_template( 'templates/stories.php' );
		return '' !== $found ? $found : $template;
	},
	11
);
