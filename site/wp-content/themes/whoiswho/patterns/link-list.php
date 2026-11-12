<?php
/**
 * Title: Link list (three ways in)
 * Slug: whoiswho/link-list
 * Categories: whoiswho
 * Description: Numbered list of titled links with a short description each (About: "Three ways into the record").
 * Keywords: links, list, navigation
 *
 * @package WhoIsWho
 */

?>
<!-- wp:group {"tagName":"section","className":"section ab-do"} -->
<section class="wp-block-group section ab-do"><!-- wp:group {"className":"wrap"} -->
<div class="wp-block-group wrap"><!-- wp:group {"className":"ab-do-head"} -->
<div class="wp-block-group ab-do-head"><!-- wp:paragraph {"className":"np-kicker"} -->
<p class="np-kicker"><?php echo esc_html_x( 'What you will find here', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Three ways in', 'pattern text', 'whoiswho' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:list {"ordered":true,"className":"ab-do-list"} -->
<ol class="wp-block-list ab-do-list"><!-- wp:list-item -->
<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html_x( 'First link title', 'pattern text', 'whoiswho' ); ?></a> <?php echo esc_html_x( 'A short description.', 'pattern text', 'whoiswho' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html_x( 'Second link title', 'pattern text', 'whoiswho' ); ?></a> <?php echo esc_html_x( 'A short description.', 'pattern text', 'whoiswho' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html_x( 'Third link title', 'pattern text', 'whoiswho' ); ?></a> <?php echo esc_html_x( 'A short description.', 'pattern text', 'whoiswho' ); ?></li>
<!-- /wp:list-item --></ol>
<!-- /wp:list --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
