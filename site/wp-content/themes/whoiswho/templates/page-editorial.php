<?php
/**
 * Template Name: Editorial page
 *
 * About and similar pages whose content carries its own hero (the About page pattern). No page band is added. An About page with
 * no content yet shows the theme's About pattern, with its removable draft note, until staff save their own wording (REQ-260).
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
	?>
	<article <?php post_class( 'page-editorial' ); ?>>
		<?php
		if ( '' === trim( wp_strip_all_tags( (string) get_the_content(), true ) ) && '' !== wiw_default_page_pattern( get_post_field( 'post_name', get_the_ID() ) ) ) {
			// Pattern markup is built from escaped theme strings and block comments; it is rendered like saved content.
			echo do_blocks( wiw_default_page_pattern( get_post_field( 'post_name', get_the_ID() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			the_content();
		}
		?>
	</article>
	<?php
}
get_footer();
