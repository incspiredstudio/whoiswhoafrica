<?php
/**
 * Home Events block (REQ-264): the next three upcoming events as compact agenda rows, with an "All events" link. The whole block is
 * absent when no event is upcoming. Events are never Sponsored. Read through PublicEvent only. Args: home (the home model).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_home   = isset( $args['home'] ) && is_array( $args['home'] ) ? $args['home'] : array();
$wiw_events = isset( $wiw_home['events'] ) && is_array( $wiw_home['events'] ) ? $wiw_home['events'] : array();
if ( array() === $wiw_events ) {
	return;
}
?>
<section class="h-events" aria-labelledby="home-events-title">
	<div class="wrap">
		<div class="h-sec-head">
			<h2 class="h-kicker" id="home-events-title"><?php esc_html_e( 'Events', 'whoiswho' ); ?></h2>
			<a class="h-link" href="<?php echo esc_url( home_url( '/events/' ) ); ?>"><?php esc_html_e( 'All events', 'whoiswho' ); ?><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></a>
		</div>
		<ul class="ag-list ag-list--home">
			<?php foreach ( $wiw_events as $wiw_event ) : ?>
				<?php
				get_template_part(
					'template-parts/event-row',
					null,
					array(
						'event'   => $wiw_event,
						'compact' => true,
					)
				);
				?>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
