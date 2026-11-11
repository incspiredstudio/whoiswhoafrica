<?php
/**
 * Editor page: band with the title, then the content.
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
