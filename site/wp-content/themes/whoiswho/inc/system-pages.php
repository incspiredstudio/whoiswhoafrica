<?php
/**
 * System pages: 404 (not found), 410 (gone) and 500 (server error). No technical detail on any of them (REQ-121, REQ-209).
 * The theme never decides whether a record is public: the plugin answers 404 or 410, and the theme renders it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

/**
 * Serve the 410 template when the plugin has marked the request as gone (filter wiw_is_gone) or the response is already 410.
 *
 * @param string $template Template path.
 */
function wiw_gone_template( string $template ): string {
	$gone = (bool) apply_filters( 'wiw_is_gone', 410 === http_response_code() );
	if ( $gone ) {
		status_header( 410 );
		nocache_headers();
		$found = locate_template( '410.php' );
		return '' !== $found ? $found : $template;
	}
	return $template;
}
add_filter( 'template_include', 'wiw_gone_template', 20 );

/**
 * Keep error pages out of search results.
 */
function wiw_noindex_system_pages(): void {
	if ( is_404() || 410 === http_response_code() ) {
		echo '<meta name="robots" content="noindex, follow">' . "\n";
	}
}
add_action( 'wp_head', 'wiw_noindex_system_pages', 3 );

/**
 * Generic message for fatal errors: no file names, no messages, no stack.
 *
 * @return string
 */
function wiw_server_error_message(): string {
	return esc_html__( 'Something went wrong on our side. Please try again in a moment.', 'whoiswho' );
}
add_filter( 'wp_php_error_message', 'wiw_server_error_message' );

/**
 * Print the links shared by the error pages.
 *
 * @param bool $with_complaints Include the complaints policy link (410).
 */
function wiw_error_links( bool $with_complaints = false ): void {
	?>
	<div class="row error-links">
		<a class="btn" href="<?php echo esc_url( home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Search the directory', 'whoiswho' ); ?></a>
		<a class="btn alt" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go to the home page', 'whoiswho' ); ?></a>
		<?php if ( $with_complaints ) : ?>
			<a class="btn alt" href="<?php echo esc_url( home_url( '/complaints/' ) ); ?>"><?php esc_html_e( 'Questions about removals', 'whoiswho' ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}
