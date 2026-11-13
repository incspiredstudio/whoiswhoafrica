<?php
/**
 * Home "Stories from the record" (REQ-029, REQ-263, REQ-257): a horizontal, swipeable track of the latest stories read through
 * PublicStory only, so a draft, scheduled, password-protected or removed story never shows. A story with a live placement for the Home
 * surface leads the track as a labelled slot and never reorders the others. The block is absent when there are no stories.
 * Args: home (the home model).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_home    = isset( $args['home'] ) && is_array( $args['home'] ) ? $args['home'] : array();
$wiw_stories = isset( $wiw_home['stories'] ) && is_array( $wiw_home['stories'] ) ? $wiw_home['stories'] : array();
if ( array() === $wiw_stories ) {
	return;
}
$wiw_types = wiw_story_types();
?>
<section class="h-stories" id="home-stories" aria-labelledby="home-stories-title">
	<div class="wrap h-sec-head">
		<h2 class="h-kicker" id="home-stories-title"><?php esc_html_e( 'Stories from the record', 'whoiswho' ); ?></h2>
		<a class="h-link" href="<?php echo esc_url( home_url( '/stories/' ) ); ?>"><?php esc_html_e( 'All stories', 'whoiswho' ); ?><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></a>
	</div>
	<div class="h-track-wrap" tabindex="0" role="group" aria-label="<?php esc_attr_e( 'Latest stories', 'whoiswho' ); ?>">
		<ul class="h-track">
			<?php
			foreach ( $wiw_stories as $wiw_s ) :
				if ( ! is_array( $wiw_s ) || empty( $wiw_s['url'] ) ) {
					continue;
				}
				$wiw_sponsor = isset( $wiw_s['sponsor'] ) && is_array( $wiw_s['sponsor'] ) && ! empty( $wiw_s['sponsor']['active'] ) ? $wiw_s['sponsor'] : null;
				$wiw_type    = $wiw_types[ (string) ( $wiw_s['type'] ?? '' ) ] ?? '';
				$wiw_cover   = wiw_story_cover( $wiw_s, '(min-width: 64rem) 32vw, 82vw' );
				?>
				<li class="h-story<?php echo null !== $wiw_sponsor ? ' is-sponsored' : ''; ?>">
					<div class="h-story-media<?php echo '' === $wiw_cover ? ' is-empty' : ''; ?>"><?php echo $wiw_cover; // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup. ?></div>
					<div class="h-story-text">
						<p class="h-story-meta">
							<?php if ( null !== $wiw_sponsor ) : ?>
								<span class="badge badge--sponsored"><?php echo esc_html( (string) ( $wiw_sponsor['label'] ?? __( 'Sponsored', 'whoiswho' ) ) ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $wiw_type ) : ?>
								<b><?php echo esc_html( $wiw_type ); ?></b>
							<?php endif; ?>
							<span><?php echo esc_html( wiw_story_date( $wiw_s ) ); ?></span>
						</p>
						<h3><a class="stretched" href="<?php echo esc_url( (string) $wiw_s['url'] ); ?>"><?php echo esc_html( (string) ( $wiw_s['title'] ?? '' ) ); ?></a></h3>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
