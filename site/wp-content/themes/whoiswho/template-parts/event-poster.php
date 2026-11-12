<?php
/**
 * Event page poster (REQ-264): a full-width orange block, or charcoal when the event is past, with the date at poster size and the
 * title beside it. The image shows duotone on wide screens only and is decorative. The title is the page's h1.
 * Args: event (PublicEvent array), events_url (link to the list).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_event = isset( $args['event'] ) && is_array( $args['event'] ) ? $args['event'] : array();
if ( array() === $wiw_event ) {
	return;
}
$wiw_day    = wiw_event_day( $wiw_event );
$wiw_status = wiw_event_status( $wiw_event );
$wiw_past   = 'past' === $wiw_status['key'];
$wiw_list   = isset( $args['events_url'] ) ? (string) $args['events_url'] : home_url( '/events/' );
$wiw_meta   = array_filter(
	array(
		wiw_event_where( $wiw_event ),
		'' !== (string) ( $wiw_event['organiser'] ?? '' ) ? sprintf( /* translators: %s: organiser name. */ __( 'Organised by %s', 'whoiswho' ), (string) $wiw_event['organiser'] ) : '',
	)
);
?>
<header class="ev-poster<?php echo $wiw_past ? ' is-past' : ''; ?>">
	<?php if ( ! empty( $wiw_event['cover'] ) ) : ?>
		<div class="ev-poster-img" aria-hidden="true"><img src="<?php echo esc_url( (string) $wiw_event['cover'] ); ?>" alt="" width="900" height="700" decoding="async"></div>
	<?php endif; ?>
	<div class="wrap ev-poster-in">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'whoiswho' ); ?>">
			<a href="<?php echo esc_url( $wiw_list ); ?>"><?php esc_html_e( 'Events', 'whoiswho' ); ?></a>
			<span aria-hidden="true"> / </span>
			<span aria-current="page"><?php echo esc_html( (string) ( $wiw_event['title'] ?? '' ) ); ?></span>
		</nav>
		<div class="ev-poster-row">
			<div class="ev-poster-date"><span class="sr"><?php echo esc_html( $wiw_day['label'] ); ?></span><b aria-hidden="true"><?php echo esc_html( $wiw_day['day'] ); ?></b><span aria-hidden="true"><?php echo esc_html( trim( $wiw_day['month'] . ' ' . $wiw_day['year'] ) ); ?></span></div>
			<div class="ev-poster-main">
				<span class="ag-tag ag-tag--<?php echo esc_attr( $wiw_status['key'] ); ?>"><?php echo $wiw_past ? esc_html__( 'Past event', 'whoiswho' ) : esc_html( $wiw_status['label'] ); ?></span>
				<h1><?php echo esc_html( (string) ( $wiw_event['title'] ?? '' ) ); ?></h1>
				<p class="ev-poster-meta"><?php echo esc_html( implode( ' · ', $wiw_meta ) ); ?></p>
			</div>
		</div>
	</div>
</header>
