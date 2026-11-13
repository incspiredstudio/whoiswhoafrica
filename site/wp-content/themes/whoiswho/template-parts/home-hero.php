<?php
/**
 * Home hero (REQ-001, REQ-002, REQ-052): four muted, looping videos side by side behind the brand title, a one-line statement of
 * what the brand is and for whom, a way to get listed, nominate or browse, and a scroll cue. The videos are decoration: they carry
 * no information, are hidden from assistive technology, never delay the first view (preload="none" and no autoplay until home.js
 * confirms motion is allowed) and fall back to their still poster with scripts off, data saver or reduced motion.
 * Args: home (the home model).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_home   = isset( $args['home'] ) && is_array( $args['home'] ) ? $args['home'] : array();
$wiw_title  = isset( $wiw_home['title'] ) && is_array( $wiw_home['title'] ) ? $wiw_home['title'] : array( wiw_site_name(), '' );
$wiw_ctas   = isset( $wiw_home['ctas'] ) && is_array( $wiw_home['ctas'] ) ? $wiw_home['ctas'] : array();
$wiw_videos = isset( $wiw_home['videos'] ) && is_array( $wiw_home['videos'] ) ? $wiw_home['videos'] : array();
?>
<section class="h-hero" aria-labelledby="home-title">
	<div class="h-vids" aria-hidden="true">
		<?php foreach ( $wiw_videos as $wiw_i => $wiw_v ) : ?>
			<div class="h-vid"<?php echo ! empty( $wiw_v['poster'] ) ? ' style="--poster:url(' . esc_url( (string) $wiw_v['poster'] ) . ')"' : ''; ?>>
				<?php if ( ! empty( $wiw_v['video'] ) ) : ?>
					<video muted loop playsinline preload="none" tabindex="-1" data-src="<?php echo esc_url( (string) $wiw_v['video'] ); ?>"<?php echo ! empty( $wiw_v['poster'] ) ? ' poster="' . esc_url( (string) $wiw_v['poster'] ) . '"' : ''; ?>></video>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="wrap h-hero-in">
		<h1 class="h-title" id="home-title">
			<span class="h-line"><span><?php echo esc_html( (string) ( $wiw_title[0] ?? '' ) ); ?></span></span>
			<?php if ( '' !== (string) ( $wiw_title[1] ?? '' ) ) : ?>
				<span class="h-line h-line--r"><span><?php echo esc_html( (string) $wiw_title[1] ); ?></span></span>
			<?php endif; ?>
		</h1>
		<div class="h-hero-foot">
			<p class="h-lede"><?php echo esc_html( (string) ( $wiw_home['lede'] ?? '' ) ); ?></p>
			<div class="h-hero-ctas">
				<?php if ( ! empty( $wiw_ctas['primary']['url'] ) ) : ?>
					<a class="btn h-btn" href="<?php echo esc_url( (string) $wiw_ctas['primary']['url'] ); ?>"><?php echo esc_html( (string) $wiw_ctas['primary']['label'] ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $wiw_ctas['secondary']['url'] ) ) : ?>
					<a class="h-link" href="<?php echo esc_url( (string) $wiw_ctas['secondary']['url'] ); ?>"><?php echo esc_html( (string) $wiw_ctas['secondary']['label'] ); ?><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></a>
				<?php endif; ?>
			</div>
			<a class="h-cue" href="#home-after-hero"><span class="h-cue-label"><?php esc_html_e( 'Scroll', 'whoiswho' ); ?><span class="sr"> <?php esc_html_e( 'to explore', 'whoiswho' ); ?></span></span><span class="h-cue-line" aria-hidden="true"></span></a>
		</div>
	</div>
</section>
<span id="home-after-hero" tabindex="-1"></span>
