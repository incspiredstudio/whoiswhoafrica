<?php
/**
 * Not found: a search box and links to the directory. No technical detail.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="wrap error-page section">
	<p class="error-code" aria-hidden="true">404</p>
	<h1><?php esc_html_e( 'We could not find that page', 'whoiswho' ); ?></h1>
	<p class="muted"><?php esc_html_e( 'The address may be wrong, or the page may have moved. Try a search, or browse the directory.', 'whoiswho' ); ?></p>
	<?php get_search_form(); ?>
	<?php wiw_error_links(); ?>
</div>
<?php
get_footer();
