<?php
/**
 * Empty state for a list with no results (REQ-029): a title, a line of help and an optional action link.
 * Args: title, text, action_label, action_url.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_title = isset( $args['title'] ) ? (string) $args['title'] : '';
$wiw_text  = isset( $args['text'] ) ? (string) $args['text'] : '';
$wiw_label = isset( $args['action_label'] ) ? (string) $args['action_label'] : '';
$wiw_url   = isset( $args['action_url'] ) ? (string) $args['action_url'] : '';
if ( '' === $wiw_title ) {
	return;
}
?>
<div class="empty">
	<h2><?php echo esc_html( $wiw_title ); ?></h2>
	<?php if ( '' !== $wiw_text ) : ?>
		<p class="muted"><?php echo esc_html( $wiw_text ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $wiw_label && '' !== $wiw_url ) : ?>
		<a class="btn" href="<?php echo esc_url( $wiw_url ); ?>"><?php echo esc_html( $wiw_label ); ?></a>
	<?php endif; ?>
</div>
