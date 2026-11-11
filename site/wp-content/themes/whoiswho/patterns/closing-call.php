<?php
/**
 * Title: Closing call
 * Slug: whoiswho/closing-call
 * Categories: whoiswho
 * Description: A closing heading with up to two buttons (About, home, FAQ).
 * Keywords: call to action, buttons
 *
 * @package WhoIsWho
 */

?>
<!-- wp:group {"tagName":"section","className":"ab-cta"} -->
<section class="wp-block-group ab-cta"><!-- wp:group {"className":"wrap ab-cta-in"} -->
<div class="wp-block-group wrap ab-cta-in"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'A closing question or invitation', 'pattern text', 'whoiswho' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html_x( 'First button', 'pattern text', 'whoiswho' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php echo esc_html_x( 'Second button', 'pattern text', 'whoiswho' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
