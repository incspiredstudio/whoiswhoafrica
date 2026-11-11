<?php
/**
 * Generic single entry. Story and event pages get their own templates in M2.
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
	<article <?php post_class( 'wrap wrap--text section' ); ?>>
		<?php the_content(); ?>
	</article>
	<?php
}
get_footer();
