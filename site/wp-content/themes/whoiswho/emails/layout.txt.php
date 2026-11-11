<?php
/**
 * Plain text email layout (REQ-075): the same message as the HTML twin, with no markup. Variables: $args (see wiw_render_email).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Variables set by wiw_render_email().
 *
 * @var array<string, mixed> $args
 */

$wiw_txt_lines = array();
if ( '' !== (string) $args['heading'] ) {
	$wiw_txt_lines[] = (string) $args['heading'];
	$wiw_txt_lines[] = '';
}
$wiw_txt_lines[] = trim( (string) $args['body_text'] );
if ( is_array( $args['cta'] ) && ! empty( $args['cta']['url'] ) ) {
	$wiw_txt_lines[] = '';
	$wiw_txt_lines[] = (string) ( $args['cta']['label'] ?? '' ) . ': ' . (string) $args['cta']['url'];
}
$wiw_txt_lines[] = '';
$wiw_txt_lines[] = '--';
$wiw_txt_lines[] = wiw_brand( 'legal_name' ) . ' - ' . wiw_brand( 'domain' );
if ( 'marketing' === $args['class'] && '' !== (string) $args['unsubscribe_url'] ) {
	$wiw_txt_lines[] = __( 'You receive this because you asked for it. Unsubscribe:', 'whoiswho' ) . ' ' . (string) $args['unsubscribe_url'];
}
echo wp_strip_all_tags( implode( "\n", $wiw_txt_lines ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- plain text mail, tags stripped.
