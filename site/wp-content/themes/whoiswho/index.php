<?php
/**
 * Fallback template: a list of whatever the query returns, through the shared parts.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
wiw_page_band( array( 'title' => is_home() ? wiw_site_name() : wp_strip_all_tags( get_the_archive_title() ) ) );
?>
<div class="wrap wrap--text section">
	<?php
	while ( have_posts() ) {
		the_post();
		?>
		<article <?php post_class(); ?>>
			<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
			<?php the_excerpt(); ?>
		</article>
		<?php
	}
	the_posts_pagination();
	?>
</div>
<?php
get_footer();
