<?php
/**
 * Events grouped by month, each month under its own heading (REQ-264). Args: events (list of PublicEvent arrays, already ordered).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_events = isset( $args['events'] ) && is_array( $args['events'] ) ? $args['events'] : array();
foreach ( wiw_event_months( $wiw_events ) as $wiw_month_key => $wiw_month_events ) :
	$wiw_first = $wiw_month_events[0];
	$wiw_day   = wiw_event_day( $wiw_first );
	?>
	<section class="ag-month" aria-label="<?php echo esc_attr( $wiw_day['month'] . ' ' . $wiw_day['year'] ); ?>">
		<h3 class="ag-month-h"><span><?php echo esc_html( $wiw_day['month'] ); ?></span> <?php echo esc_html( $wiw_day['year'] ); ?></h3>
		<ul class="ag-list">
			<?php
			foreach ( $wiw_month_events as $wiw_month_event ) {
				get_template_part( 'template-parts/event-row', null, array( 'event' => $wiw_month_event ) );
			}
			?>
		</ul>
	</section>
<?php endforeach; ?>
