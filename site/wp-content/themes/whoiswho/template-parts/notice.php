<?php
/**
 * Notice markup: pending review, Sponsored, draft note, staff preview banner. Markup only; whether to show one is decided in the plugin.
 * Args: kind (pending|sponsored|draft|preview|info), text.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_kind = isset( $args['kind'] ) && in_array( $args['kind'], array( 'pending', 'sponsored', 'draft', 'preview', 'info' ), true ) ? (string) $args['kind'] : 'info';
$wiw_text = isset( $args['text'] ) ? (string) $args['text'] : '';
if ( '' === $wiw_text ) {
	return;
}
?>
<p class="notice notice--<?php echo esc_attr( $wiw_kind ); ?>" role="note"><?php echo esc_html( $wiw_text ); ?></p>
