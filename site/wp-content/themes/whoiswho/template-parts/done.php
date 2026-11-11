<?php
/**
 * Done page: title, a sentence, a reference and a way back. Announced as a status. Args: title, text, reference, back_url, back_label.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_d = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'title'      => '',
		'text'       => '',
		'reference'  => '',
		'back_url'   => home_url( '/' ),
		'back_label' => __( 'Back to the home page', 'whoiswho' ),
	)
);
?>
<div class="wrap wrap--text section done" role="status">
	<h1><?php echo esc_html( (string) $wiw_d['title'] ); ?></h1>
	<?php if ( '' !== $wiw_d['text'] ) : ?>
		<p><?php echo esc_html( (string) $wiw_d['text'] ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $wiw_d['reference'] ) : ?>
		<p class="mono"><?php esc_html_e( 'Your reference:', 'whoiswho' ); ?> <strong><?php echo esc_html( (string) $wiw_d['reference'] ); ?></strong></p>
	<?php endif; ?>
	<p><a class="btn alt" href="<?php echo esc_url( (string) $wiw_d['back_url'] ); ?>"><?php echo esc_html( (string) $wiw_d['back_label'] ); ?></a></p>
</div>
