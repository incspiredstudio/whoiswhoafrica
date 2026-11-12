<?php
/**
 * Title: Pull statement and prose
 * Slug: whoiswho/pull-statement
 * Categories: whoiswho
 * Description: A pull quote beside two or three paragraphs (About).
 * Keywords: pull quote, prose
 *
 * @package WhoIsWho
 */

?>
<!-- wp:group {"tagName":"section","className":"section ab-why"} -->
<section class="wp-block-group section ab-why"><!-- wp:group {"className":"wrap ab-why-in"} -->
<div class="wp-block-group wrap ab-why-in"><!-- wp:group -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"np-kicker aw-kick"} -->
<p class="np-kicker aw-kick"><?php echo esc_html_x( 'The gap', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"ab-pull"} -->
<p class="ab-pull"><?php echo esc_html_x( 'A pull quote that states the problem in one sentence.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"ab-prose"} -->
<div class="wp-block-group ab-prose"><!-- wp:paragraph -->
<p><?php echo esc_html_x( 'A first paragraph of explanation.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'A second paragraph of explanation.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
