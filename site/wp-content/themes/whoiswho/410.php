<?php
/**
 * Gone: the page has been removed and will not return. No reason is shown (REQ-209).
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
	<p class="error-code" aria-hidden="true">410</p>
	<h1><?php esc_html_e( 'This page has been removed', 'whoiswho' ); ?></h1>
	<p class="muted"><?php esc_html_e( 'This page is no longer available and will not return. Search engines are asked not to index it.', 'whoiswho' ); ?></p>
	<?php wiw_error_links( true ); ?>
</div>
<?php
get_footer();
