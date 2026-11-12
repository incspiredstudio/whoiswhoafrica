<?php
/**
 * Single-use link page: the theme supplies the markup for the plugin's /link/{token} route through the wiw_link_page filter.
 * A GET only shows a confirm button; the POST consumes the link. The theme never decides the state, it renders what the plugin sends (REQ-121, REQ-233).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

/**
 * Render the link page inside the site header and footer.
 *
 * @param string                                  $html  The plugin's bare default markup (ignored).
 * @param array{state?: string, message?: string} $state State and message from the plugin.
 */
function wiw_link_page( string $html, array $state = array() ): string {
	unset( $html );
	$kind    = isset( $state['state'] ) ? (string) $state['state'] : 'expired';
	$message = isset( $state['message'] ) ? (string) $state['message'] : '';
	$token   = (string) get_query_var( 'wiw_link' );

	ob_start();
	get_header();
	?>
	<div class="wrap wrap--text section link-page">
		<?php if ( 'confirm' === $kind ) : ?>
			<h1><?php esc_html_e( 'Please confirm', 'whoiswho' ); ?></h1>
			<p><?php esc_html_e( 'Press the button to continue. Opening this page does nothing until you do.', 'whoiswho' ); ?></p>
			<form method="post" action="<?php echo esc_url( home_url( '/link/' . rawurlencode( $token ) ) ); ?>">
				<button type="submit" class="btn"><?php esc_html_e( 'Confirm', 'whoiswho' ); ?></button>
			</form>
		<?php elseif ( 'done' === $kind ) : ?>
			<h1 role="status"><?php esc_html_e( 'Done', 'whoiswho' ); ?></h1>
			<p><?php echo esc_html( $message ); ?></p>
		<?php elseif ( 'limited' === $kind ) : ?>
			<h1><?php esc_html_e( 'Please try again later', 'whoiswho' ); ?></h1>
			<p><?php echo esc_html( $message ); ?></p>
		<?php else : ?>
			<h1><?php esc_html_e( 'This link has expired or was already used', 'whoiswho' ); ?></h1>
			<p><?php esc_html_e( 'If you still need to do this, start again from the page you came from.', 'whoiswho' ); ?></p>
		<?php endif; ?>
		<p><a class="btn alt" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the home page', 'whoiswho' ); ?></a></p>
	</div>
	<?php
	get_footer();
	return (string) ob_get_clean();
}
add_filter( 'wiw_link_page', 'wiw_link_page', 10, 2 );
