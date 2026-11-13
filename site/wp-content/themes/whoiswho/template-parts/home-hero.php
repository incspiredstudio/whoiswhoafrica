<?php
/**
 * Home hero (REQ-001, REQ-002, REQ-052): four muted, looping videos side by side behind the brand title, a one-line statement of
 * what the brand is and for whom, and a scroll cue (no buttons: the ways in are further down the page, issue 798). The videos are decoration: they carry
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
$wiw_videos = isset( $wiw_home['videos'] ) && is_array( $wiw_home['videos'] ) ? $wiw_home['videos'] : array();
?>
<section class="h-hero" aria-labelledby="home-title">
	<div class="h-vids" aria-hidden="true">
		<?php foreach ( $wiw_videos as $wiw_i => $wiw_v ) : ?>
			<?php
			$wiw_w    = (int) ( $wiw_v['width'] ?? 540 );
			$wiw_h    = (int) ( $wiw_v['height'] ?? 960 );
			$wiw_sets = isset( $wiw_v['sets'] ) && is_array( $wiw_v['sets'] ) ? $wiw_v['sets'] : array();
			// The first still is the largest contentful paint: eager and high priority; the others are small and low priority.
			// Every clip is preload="none" with no autoplay: nothing is fetched until home.js starts it (first at load, each next on its turn).
			$wiw_mime = array(
				'avif' => 'image/avif',
				'webp' => 'image/webp',
			);
			?>
			<div class="h-vid">
				<?php if ( ! empty( $wiw_v['poster'] ) ) : ?>
					<picture>
						<?php foreach ( $wiw_mime as $wiw_ext => $wiw_type ) : ?>
							<?php if ( ! empty( $wiw_sets[ $wiw_ext ] ) ) : ?>
								<source type="<?php echo esc_attr( $wiw_type ); ?>" srcset="<?php echo esc_attr( (string) $wiw_sets[ $wiw_ext ] ); ?>" sizes="(min-width: 64rem) 54vw, 50vw">
							<?php endif; ?>
						<?php endforeach; ?>
						<img class="h-vid-poster" src="<?php echo esc_url( (string) $wiw_v['poster'] ); ?>"<?php echo ! empty( $wiw_sets['jpg'] ) ? ' srcset="' . esc_attr( (string) $wiw_sets['jpg'] ) . '" sizes="(min-width: 64rem) 54vw, 50vw"' : ''; ?> width="<?php echo esc_attr( (string) $wiw_w ); ?>" height="<?php echo esc_attr( (string) $wiw_h ); ?>" alt="" decoding="async" loading="eager"<?php echo 0 === $wiw_i ? ' fetchpriority="high"' : ' fetchpriority="low"'; ?>>
					</picture>
				<?php endif; ?>
				<?php if ( ! empty( $wiw_v['video'] ) ) : ?>
					<video muted loop playsinline preload="none" tabindex="-1" width="<?php echo esc_attr( (string) $wiw_w ); ?>" height="<?php echo esc_attr( (string) $wiw_h ); ?>" data-turn="<?php echo esc_attr( (string) $wiw_i ); ?>">
						<?php if ( ! empty( $wiw_v['webm'] ) ) : ?>
							<source src="<?php echo esc_url( (string) $wiw_v['webm'] ); ?>" type="video/webm">
						<?php endif; ?>
						<source src="<?php echo esc_url( (string) $wiw_v['video'] ); ?>" type="video/mp4">
					</video>
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
			<a class="h-cue" href="#home-after-hero"><span class="h-cue-label"><?php esc_html_e( 'Scroll', 'whoiswho' ); ?><span class="sr"> <?php esc_html_e( 'to explore', 'whoiswho' ); ?></span></span><span class="h-cue-line" aria-hidden="true"></span></a>
		</div>
	</div>
</section>
<span id="home-after-hero" tabindex="-1"></span>
