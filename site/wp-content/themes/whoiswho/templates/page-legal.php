<?php
/**
 * Template Name: Legal page
 *
 * Privacy, Terms and Cookie policy: page band, the draft note until Legal signs off, the editor body, and on the cookie page the Cookie preferences control.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
while ( have_posts() ) {
	the_post();
	wiw_page_band(
		array(
			'eyebrow' => __( 'Legal', 'whoiswho' ),
			'title'   => get_the_title(),
			'lede'    => has_excerpt() ? get_the_excerpt() : '',
			'tone'    => 'light',
		)
	);
	?>
	<article <?php post_class( 'wrap section prose page-body' ); ?>>
		<?php
		// The drafts carry their own visible note as the first block; show the template's note only when the body has none.
		if ( ! wiw_legal_is_final( get_the_ID() ) && ! str_contains( (string) get_the_content(), 'notice--draft' ) ) {
			get_template_part(
				'template-parts/notice',
				null,
				array(
					'kind' => 'draft',
					'text' => __( 'Draft copy for review. Legal will supply the final wording.', 'whoiswho' ),
				)
			);
		}
		the_content();
		if ( is_page( 'cookies' ) ) {
			?>
			<p><button type="button" class="btn alt" data-cookie-prefs><?php esc_html_e( 'Cookie preferences', 'whoiswho' ); ?></button></p>
			<?php
		}
		?>
	</article>
	<?php
}//end while
get_footer();
