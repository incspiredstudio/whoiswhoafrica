<?php
/**
 * Editor page: a light band with the title, as the prototype's FAQ and legal pages, then the content in a reading column that lines
 * up with the band. The FAQ page gets the prototype's eyebrow and lede unless the page has its own excerpt.
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
	$wiw_is_faq = 'faq' === get_post_field( 'post_name', get_the_ID() );
	$wiw_lede   = has_excerpt() ? get_the_excerpt() : '';
	if ( '' === $wiw_lede && $wiw_is_faq ) {
		$wiw_lede = __( 'Short answers about listing, accuracy, awards and your data.', 'whoiswho' );
	}
	wiw_page_band(
		array(
			'eyebrow' => $wiw_is_faq ? __( 'Questions', 'whoiswho' ) : '',
			'title'   => get_the_title(),
			'lede'    => $wiw_lede,
			'tone'    => 'light',
		)
	);
	?>
	<article <?php post_class( 'wrap section page-body' ); ?>>
		<?php the_content(); ?>
	</article>
	<?php
}//end while
get_footer();
