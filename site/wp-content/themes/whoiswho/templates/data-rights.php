<?php
/**
 * Your data rights page (REQ-059, REQ-058): the page band, the draft note until Legal signs off, the wording of the Your data
 * rights page from the editor, then the request form. It is a form page rather than a plain page so the refused-submission
 * page keeps its status and the same page answers the POST (a form field called name would otherwise be read as a post slug).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_page = get_page_by_path( 'data-rights' );
get_header();
wiw_page_band(
	array(
		'eyebrow' => __( 'Trust', 'whoiswho' ),
		'title'   => __( 'Your data rights', 'whoiswho' ),
		'tone'    => 'blue',
	)
);
?>
<article class="wrap wrap--text section prose">
	<?php
	if ( ! $wiw_page instanceof WP_Post || ! wiw_legal_is_final( $wiw_page->ID ) ) {
		get_template_part(
			'template-parts/notice',
			null,
			array(
				'kind' => 'draft',
				'text' => __( 'Draft copy for review. Legal will supply the final wording.', 'whoiswho' ),
			)
		);
	}
	if ( $wiw_page instanceof WP_Post && 'publish' === $wiw_page->post_status ) {
		echo wp_kses_post( apply_filters( 'the_content', $wiw_page->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the core content filter.
	}
	?>
</article>
<?php
get_template_part( 'template-parts/forms/data-rights-form' );
get_footer();
