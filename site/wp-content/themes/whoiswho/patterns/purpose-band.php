<?php
/**
 * Title: Purpose band
 * Slug: whoiswho/purpose-band
 * Categories: whoiswho
 * Description: A large statement of purpose between sections (About).
 * Keywords: purpose, statement
 *
 * @package WhoIsWho
 */

?>
<!-- wp:group {"tagName":"section","className":"ab-purpose"} -->
<section class="wp-block-group ab-purpose"><!-- wp:group {"className":"wrap"} -->
<div class="wp-block-group wrap"><!-- wp:paragraph {"className":"np-kicker"} -->
<p class="np-kicker"><?php echo esc_html_x( 'Why we exist', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"ab-purpose-text"} -->
<p class="ab-purpose-text"><?php echo esc_html_x( 'The purpose statement goes here, in one or two sentences.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
