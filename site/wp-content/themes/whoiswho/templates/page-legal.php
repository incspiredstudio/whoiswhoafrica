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
	wiw_page_band( array( 'title' => get_the_title() ) );
	?>
	<article <?php post_class( 'wrap wrap--text section prose' ); ?>>
		<?php
		if ( ! wiw_legal_is_final( get_the_ID() ) ) {
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
