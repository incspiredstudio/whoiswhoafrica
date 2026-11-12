<?php
/**
 * Privacy notice at the point of collection (REQ-058): one line and a link to the privacy notice, printed by every form that
 * collects personal data. Without text a default line is used, so the link is never missing. Args: text (optional), url (optional).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_text = isset( $args['text'] ) ? trim( (string) $args['text'] ) : '';
$wiw_text = '' !== $wiw_text ? $wiw_text : __( 'We use the details you give here only to handle this request.', 'whoiswho' );
$wiw_url  = isset( $args['url'] ) && '' !== (string) $args['url'] ? (string) $args['url'] : home_url( '/privacy/' );
?>
<p class="small muted privacy-notice"><?php echo esc_html( $wiw_text ); ?> <a href="<?php echo esc_url( $wiw_url ); ?>"><?php esc_html_e( 'Privacy notice', 'whoiswho' ); ?></a></p>
