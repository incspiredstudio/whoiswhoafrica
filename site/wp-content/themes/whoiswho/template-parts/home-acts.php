<?php
/**
 * Home three acts (REQ-001, REQ-002): Influence, Achievement and Legacy as three full-height colour scenes, each with a brand mark and
 * a link to people, awards or stories. The words are the brand tagline's three words and come from the model; the scenes read in full
 * as ordinary stacked sections with scripts off and with reduced motion.
 * Args: home (the home model).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_home = isset( $args['home'] ) && is_array( $args['home'] ) ? $args['home'] : array();
$wiw_acts = isset( $wiw_home['acts'] ) && is_array( $wiw_home['acts'] ) ? $wiw_home['acts'] : array();
if ( array() === $wiw_acts ) {
	return;
}
?>
<section class="h-acts" aria-label="<?php echo esc_attr( (string) ( $wiw_home['tagline'] ?? '' ) ); ?>">
	<?php foreach ( $wiw_acts as $wiw_i => $wiw_act ) : ?>
		<article class="h-act h-act--<?php echo esc_attr( (string) $wiw_act['tone'] ); ?>" data-reveal>
			<span class="h-mark h-act-mark h-mark--<?php echo esc_attr( (string) $wiw_act['mark'] ); ?>" aria-hidden="true"></span>
			<div class="wrap h-act-in">
				<p class="h-act-n"><?php echo esc_html( sprintf( '%02d / %02d', $wiw_i + 1, count( $wiw_acts ) ) ); ?></p>
				<h2 class="h-act-word"><span class="h-line"><span><?php echo esc_html( (string) $wiw_act['word'] ); ?></span></span></h2>
				<div class="h-act-foot">
					<p><?php echo esc_html( (string) $wiw_act['text'] ); ?></p>
					<a class="h-link" href="<?php echo esc_url( (string) $wiw_act['url'] ); ?>"><?php echo esc_html( (string) $wiw_act['link'] ); ?><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></a>
				</div>
			</div>
		</article>
	<?php endforeach; ?>
</section>
