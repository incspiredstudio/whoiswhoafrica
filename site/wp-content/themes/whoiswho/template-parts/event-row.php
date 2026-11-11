<?php
/**
 * Event agenda row (REQ-264): orange date block (day over month), display-type title, place and organiser, status tag, an optional
 * small image on wide screens and an arrow. Past rows are muted. A compact row (Home) has no image. Events have their own look so
 * they are never mistaken for stories: no author, reading time, cover image or comments.
 * Args: event (PublicEvent array), compact (bool). Prints one list item.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_event = isset( $args['event'] ) && is_array( $args['event'] ) ? $args['event'] : array();
if ( array() === $wiw_event || empty( $wiw_event['url'] ) ) {
	return;
}
$wiw_compact = ! empty( $args['compact'] );
$wiw_day     = wiw_event_day( $wiw_event );
$wiw_status  = wiw_event_status( $wiw_event );
$wiw_meta    = array_filter( array( wiw_event_where( $wiw_event ), (string) ( $wiw_event['organiser'] ?? '' ) ) );
$wiw_thumb   = ! $wiw_compact && ! empty( $wiw_event['cover'] ) ? (string) $wiw_event['cover'] : '';
?>
<li class="ag-row<?php echo 'past' === $wiw_status['key'] ? ' is-past' : ''; ?><?php echo $wiw_compact ? ' ag-row--compact' : ''; ?>">
	<a class="ag-link" href="<?php echo esc_url( (string) $wiw_event['url'] ); ?>">
		<span class="ag-date" aria-hidden="true"><b><?php echo esc_html( $wiw_day['day'] ); ?></b><?php echo esc_html( $wiw_day['month_short'] ); ?></span>
		<span class="ag-main">
			<span class="ag-title"><?php echo esc_html( (string) ( $wiw_event['title'] ?? '' ) ); ?></span>
			<span class="ag-meta"><?php echo esc_html( implode( ' · ', $wiw_meta ) ); ?></span>
			<span class="sr"><?php echo esc_html( wiw_event_when( $wiw_event ) ); ?></span>
		</span>
		<span class="ag-tag ag-tag--<?php echo esc_attr( $wiw_status['key'] ); ?>"><?php echo esc_html( $wiw_status['label'] ); ?></span>
		<?php if ( '' !== $wiw_thumb ) : ?>
			<span class="ag-thumb" aria-hidden="true"><img class="ag-img" src="<?php echo esc_url( $wiw_thumb ); ?>" alt="" width="240" height="160" loading="lazy" decoding="async"></span>
		<?php endif; ?>
		<?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?>
	</a>
</li>
