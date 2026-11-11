<?php
/**
 * Title: Legal section
 * Slug: whoiswho/legal-section
 * Categories: whoiswho
 * Description: A heading and paragraphs for a legal page, with an optional draft note that is removed when the wording is final.
 * Keywords: legal, privacy, terms, draft
 *
 * @package WhoIsWho
 */

?>
<!-- wp:paragraph {"className":"notice notice--draft"} -->
<p class="notice notice--draft"><?php echo esc_html_x( 'Draft note: delete this block when Legal has signed off the wording.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Section heading', 'pattern text', 'whoiswho' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Section text.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->
