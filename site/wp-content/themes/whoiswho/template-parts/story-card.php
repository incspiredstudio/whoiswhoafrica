<?php
/**
 * Story card (REQ-029, REQ-249, REQ-263): a 16:9 cover, the type, date and reading time, the headline, a one-line lede and the author.
 * A story with a live placement carries the dashed Sponsored badge and the sponsor's name; the badge is never styled like a status.
 * The whole card is one link, put on the headline.
 * Args: story (a PublicStory card), variant (lead, tile or card), eager (bool, load the cover at once).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_story   = isset( $args['story'] ) && is_array( $args['story'] ) ? $args['story'] : array();
$wiw_variant = isset( $args['variant'] ) && in_array( $args['variant'], array( 'lead', 'tile', 'card' ), true ) ? (string) $args['variant'] : 'card';
if ( array() === $wiw_story || empty( $wiw_story['url'] ) ) {
	return;
}
$wiw_types   = wiw_story_types();
$wiw_type    = $wiw_types[ (string) ( $wiw_story['type'] ?? '' ) ] ?? '';
$wiw_sponsor = isset( $wiw_story['sponsor'] ) && is_array( $wiw_story['sponsor'] ) && ! empty( $wiw_story['sponsor']['active'] ) ? $wiw_story['sponsor'] : null;
$wiw_sizes   = 'lead' === $wiw_variant ? '(min-width: 64rem) 58vw, 100vw' : '(min-width: 64rem) 30vw, (min-width: 40rem) 46vw, 100vw';
$wiw_cover   = wiw_story_cover( $wiw_story, $wiw_sizes, ! empty( $args['eager'] ) );
$wiw_date    = wiw_story_date( $wiw_story );
$wiw_minutes = (int) ( $wiw_story['reading_minutes'] ?? 0 );
?>
<article class="scard scard--<?php echo esc_attr( $wiw_variant ); ?><?php echo null !== $wiw_sponsor ? ' is-sponsored' : ''; ?>">
	<div class="scard-media<?php echo '' === $wiw_cover ? ' is-empty' : ''; ?>">
		<?php echo $wiw_cover; // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup. ?>
	</div>
	<div class="scard-body">
		<p class="scard-meta">
			<?php if ( null !== $wiw_sponsor ) : ?>
				<span class="badge badge--sponsored"><?php echo esc_html( (string) ( $wiw_sponsor['label'] ?? __( 'Sponsored', 'whoiswho' ) ) ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $wiw_type ) : ?>
				<span class="scard-type"><?php echo esc_html( $wiw_type ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $wiw_date ) : ?>
				<time datetime="<?php echo esc_attr( gmdate( 'Y-m-d', (int) strtotime( (string) $wiw_story['date'] ) ) ); ?>"><?php echo esc_html( $wiw_date ); ?></time>
			<?php endif; ?>
			<?php if ( $wiw_minutes > 0 ) : ?>
				<span class="scard-read">
					<?php
					/* translators: %d: minutes to read. */
					echo esc_html( sprintf( __( '%d min read', 'whoiswho' ), $wiw_minutes ) );
					?>
				</span>
			<?php endif; ?>
		</p>
		<h2 class="scard-title"><a href="<?php echo esc_url( (string) $wiw_story['url'] ); ?>"><?php echo esc_html( (string) ( $wiw_story['title'] ?? '' ) ); ?></a></h2>
		<?php if ( '' !== (string) ( $wiw_story['lede'] ?? '' ) ) : ?>
			<p class="scard-lede"><?php echo esc_html( (string) $wiw_story['lede'] ); ?></p>
		<?php endif; ?>
		<p class="scard-by">
			<?php if ( null !== $wiw_sponsor ) : ?>
				<?php
				/* translators: %s: sponsor name. */
				echo esc_html( sprintf( __( 'Paid for by %s', 'whoiswho' ), (string) ( $wiw_sponsor['name'] ?? '' ) ) );
				?>
				<span aria-hidden="true"> · </span>
			<?php endif; ?>
			<?php if ( '' !== (string) ( $wiw_story['author'] ?? '' ) ) : ?>
				<?php
				/* translators: %s: author name. */
				echo esc_html( sprintf( __( 'By %s', 'whoiswho' ), (string) $wiw_story['author'] ) );
				?>
			<?php endif; ?>
		</p>
	</div>
</article>
