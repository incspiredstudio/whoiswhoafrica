<?php
/**
 * Page band: eyebrow, title (the page's h1), lede, optional tone. Args: eyebrow, title, lede, tone, wide. A list page (directory,
 * industries) passes wide so the title lines up with the card grid below it instead of the narrow reading column.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_band = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'eyebrow' => '',
		'title'   => '',
		'lede'    => '',
		'tone'    => '',
		'wide'    => false,
	)
);
?>
<section class="section section--tight page-band<?php echo '' !== $wiw_band['tone'] ? ' page-band--' . esc_attr( sanitize_html_class( (string) $wiw_band['tone'] ) ) : ''; ?>">
	<div class="wrap<?php echo $wiw_band['wide'] ? '' : ' wrap--text'; ?>">
		<?php if ( '' !== $wiw_band['eyebrow'] ) : ?>
			<p class="eyebrow"><?php echo esc_html( (string) $wiw_band['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h1><?php echo esc_html( (string) $wiw_band['title'] ); ?></h1>
		<?php if ( '' !== $wiw_band['lede'] ) : ?>
			<p class="lede"><?php echo esc_html( (string) $wiw_band['lede'] ); ?></p>
		<?php endif; ?>
	</div>
</section>
