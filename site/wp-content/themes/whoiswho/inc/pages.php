<?php
/**
 * Editable pages (REQ-260, REQ-120): the About page uses the Editorial template, which has no page band because the content carries
 * its own hero. A page with no content yet falls back to the theme pattern for its slug, so the page is never blank; the draft note
 * inside it is an ordinary block that staff delete in the editor.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

/**
 * Page slugs that use the Editorial template without an editor choosing it, mapped to the pattern shown while the page is empty.
 *
 * @return array<string, string>
 */
function wiw_editorial_pages(): array {
	return (array) apply_filters( 'wiw_editorial_pages', array( 'about' => 'whoiswho/about-page' ) );
}

/**
 * Use the Editorial template for the About page.
 *
 * @param string $template Template path.
 */
function wiw_editorial_template( string $template ): string {
	$pages = array_keys( wiw_editorial_pages() );
	if ( array() !== $pages && is_page( $pages ) ) {
		$found = locate_template( 'templates/page-editorial.php' );
		if ( '' !== $found ) {
			return $found;
		}
	}
	return $template;
}
add_filter( 'template_include', 'wiw_editorial_template' );

/**
 * Block markup of the pattern a page falls back to while it has no content. Empty when the slug has none.
 *
 * @param string $slug Page slug.
 */
function wiw_default_page_pattern( string $slug ): string {
	$map = wiw_editorial_pages();
	if ( ! isset( $map[ $slug ] ) ) {
		return '';
	}
	$registry = WP_Block_Patterns_Registry::get_instance();
	if ( ! $registry->is_registered( $map[ $slug ] ) ) {
		return '';
	}
	$pattern = $registry->get_registered( $map[ $slug ] );
	return is_array( $pattern ) ? (string) ( $pattern['content'] ?? '' ) : '';
}
