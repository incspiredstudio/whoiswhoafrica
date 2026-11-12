<?php
/**
 * Title: Page hero
 * Slug: whoiswho/page-hero
 * Categories: whoiswho
 * Description: Eyebrow, headline and lede at the top of an editor page (About, FAQ, legal pages).
 * Keywords: hero, header, about
 *
 * @package WhoIsWho
 */

?>
<!-- wp:group {"tagName":"header","className":"ab-hero"} -->
<header class="wp-block-group ab-hero"><!-- wp:group {"className":"wrap ab-hero-in"} -->
<div class="wp-block-group wrap ab-hero-in"><!-- wp:group {"className":"ab-hero-text"} -->
<div class="wp-block-group ab-hero-text"><!-- wp:paragraph {"className":"np-kicker"} -->
<p class="np-kicker"><?php echo esc_html_x( 'Eyebrow', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"ab-title"} -->
<h1 class="wp-block-heading ab-title"><?php echo esc_html_x( 'A headline that says what this page is', 'pattern text', 'whoiswho' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"ab-lede"} -->
<p class="ab-lede"><?php echo esc_html_x( 'One or two sentences that explain the page.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></header>
<!-- /wp:group -->
