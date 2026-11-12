<?php
/**
 * Legal pages (Privacy, Terms, Cookie policy, Complaints and takedown, Your data rights): one template, a draft note until Legal supplies wording, and the Cookie preferences control on the cookie page (REQ-100, REQ-120, REQ-248).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

/**
 * Page slugs that use the legal template without an editor choosing it.
 *
 * @return array<int, string>
 */
function wiw_legal_slugs(): array {
	return (array) apply_filters( 'wiw_legal_slugs', array( 'privacy', 'terms', 'cookies', 'complaints', 'data-rights' ) );
}

/**
 * Use the legal template for the legal pages.
 *
 * @param string $template Template path.
 */
function wiw_legal_template( string $template ): string {
	if ( is_page( wiw_legal_slugs() ) ) {
		$found = locate_template( 'templates/page-legal.php' );
		if ( '' !== $found ) {
			return $found;
		}
	}
	return $template;
}
add_filter( 'template_include', 'wiw_legal_template' );

/**
 * Whether Legal has signed off the wording of this page. False until the filter says so.
 *
 * @param int $page_id Page ID.
 */
function wiw_legal_is_final( int $page_id ): bool {
	return (bool) apply_filters( 'wiw_legal_is_final', false, $page_id );
}
