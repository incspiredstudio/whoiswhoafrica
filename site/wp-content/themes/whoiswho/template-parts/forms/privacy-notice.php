<?php
/**
 * Privacy notice slot at the point of collection. The wording comes from the privacy issues; this part prints what it is given.
 * Args: text, url.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_text = isset( $args['text'] ) ? (string) $args['text'] : '';
$wiw_url  = isset( $args['url'] ) ? (string) $args['url'] : home_url( '/privacy/' );
if ( '' === $wiw_text ) {
	return;
}
?>
<p class="small muted privacy-notice"><?php echo esc_html( $wiw_text ); ?> <a href="<?php echo esc_url( $wiw_url ); ?>"><?php esc_html_e( 'Privacy notice', 'whoiswho' ); ?></a></p>
