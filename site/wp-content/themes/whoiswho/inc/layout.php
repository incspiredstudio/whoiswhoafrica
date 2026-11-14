<?php
/**
 * Layout helpers shared by the templates: brand name with a safe fallback, and the page band part (REQ-044, REQ-040, REQ-043).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_site_name' ) ) {
	/**
	 * The brand name from the child theme's configuration, falling back to the site title when the parent runs alone.
	 */
	function wiw_site_name(): string {
		return function_exists( 'wiw_brand' ) ? wiw_brand( 'name' ) : get_bloginfo( 'name' );
	}
}

/**
 * Document title parts: "Page | Brand", the brand alone on the front page.
 *
 * @param array<string, string> $parts Title parts.
 * @return array<string, string>
 */
function wiw_document_title_parts( array $parts ): array {
	$parts['site'] = wiw_site_name();
	if ( is_front_page() ) {
		$parts['title'] = wiw_site_name();
		unset( $parts['tagline'], $parts['site'] );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'wiw_document_title_parts' );

/**
 * Print the page band (eyebrow, title, lede, optional tone). The only h1 on inner pages.
 *
 * @param array{eyebrow?: string, title?: string, lede?: string, tone?: string, wide?: bool} $band Band content.
 */
function wiw_page_band( array $band ): void {
	get_template_part( 'template-parts/page-band', null, $band );
}
