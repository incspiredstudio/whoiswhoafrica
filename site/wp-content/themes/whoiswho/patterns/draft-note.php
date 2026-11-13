<?php
/**
 * Title: Draft copy note
 * Slug: whoiswho/draft-note
 * Categories: whoiswho
 * Description: The "Draft copy for review" note. A block, not template code: delete it in the editor when the wording is signed off.
 * Keywords: draft, review, note
 *
 * @package WhoIsWho
 */

?>
<!-- wp:paragraph {"className":"notice notice--draft"} -->
<p class="notice notice--draft"><?php echo esc_html_x( 'Draft copy for review. Delete this note when the wording is signed off.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->
