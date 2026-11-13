<?php
/**
 * Title: FAQ group
 * Slug: whoiswho/faq-groups
 * Categories: whoiswho
 * Description: A group heading with two questions for the FAQ page. Add questions inside it and drag groups and questions to reorder them.
 * Keywords: faq, questions, answers
 *
 * @package WhoIsWho
 */

?>
<!-- wp:whoiswho/faq-group {"heading":"<?php echo esc_attr_x( 'Group heading', 'pattern text', 'whoiswho' ); ?>"} -->
<!-- wp:whoiswho/faq-question {"title":"<?php echo esc_attr_x( 'A question people ask', 'pattern text', 'whoiswho' ); ?>"} -->
<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Answer in plain words. Link to a page by choosing it in the link box.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:whoiswho/faq-question -->

<!-- wp:whoiswho/faq-question {"title":"<?php echo esc_attr_x( 'Another question', 'pattern text', 'whoiswho' ); ?>"} -->
<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Another answer.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:whoiswho/faq-question -->
<!-- /wp:whoiswho/faq-group -->
