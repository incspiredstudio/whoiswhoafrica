<?php
/**
 * Events list at /events (REQ-262, REQ-264, REQ-265): an orange header band with a Next up card (absent when none), the shared
 * filter bar (City and Industry, count, Clear), upcoming events grouped by month soonest first, then past events newest first.
 * Reads events only through PublicEvent. The post type is wiw_event, so WordPress picks this file for its archive.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Content\PublicEvent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_filters  = wiw_events_filters();
$wiw_all      = PublicEvent::list( array() )['items'];
$wiw_result   = PublicEvent::list( $wiw_filters );
$wiw_upcoming = array_values( array_filter( $wiw_result['items'], static fn ( array $e ): bool => true === $e['upcoming'] ) );
$wiw_past     = array_values( array_filter( $wiw_result['items'], static fn ( array $e ): bool => true !== $e['upcoming'] ) );
$wiw_next     = $wiw_upcoming[0] ?? null;
$wiw_cities   = array();
foreach ( $wiw_all as $wiw_item ) {
	if ( '' !== (string) $wiw_item['city'] ) {
		$wiw_cities[ (string) $wiw_item['city'] ] = (string) $wiw_item['city'];
	}
}
ksort( $wiw_cities );
$wiw_url   = get_post_type_archive_link( 'wiw_event' );
$wiw_url   = false !== $wiw_url ? $wiw_url : home_url( '/events/' );
$wiw_total = (int) $wiw_result['total'];
$wiw_on    = '' !== $wiw_filters['city'] || '' !== $wiw_filters['industry'];

get_header();
?>
<section class="ev-hero">
	<div class="wrap ev-hero-in">
		<div class="ev-hero-text">
			<p class="ev-kick"><?php esc_html_e( 'Calendar', 'whoiswho' ); ?></p>
			<h1><?php esc_html_e( 'Events', 'whoiswho' ); ?></h1>
			<p class="ev-lede"><?php esc_html_e( 'Conferences, forums and open days across the continent. Each event links to its organiser, who handles registration and any fee.', 'whoiswho' ); ?></p>
		</div>
		<?php if ( null !== $wiw_next ) : ?>
			<?php $wiw_next_day = wiw_event_day( $wiw_next ); ?>
			<a class="ev-next" href="<?php echo esc_url( (string) $wiw_next['url'] ); ?>">
				<span class="ev-next-k"><?php esc_html_e( 'Next up', 'whoiswho' ); ?></span>
				<span class="ev-next-d"><b><?php echo esc_html( $wiw_next_day['day'] ); ?></b><?php echo esc_html( $wiw_next_day['month'] ); ?></span>
				<span class="ev-next-t"><?php echo esc_html( (string) $wiw_next['title'] ); ?></span>
				<span class="ev-next-m"><?php echo esc_html( wiw_event_where( $wiw_next ) ); ?></span>
				<span class="ev-next-go"><?php esc_html_e( 'View event', 'whoiswho' ); ?><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></span>
			</a>
		<?php endif; ?>
	</div>
</section>
<section class="ev-body">
	<div class="wrap">
		<?php
		get_template_part(
			'template-parts/filter-bar',
			null,
			array(
				'action'   => $wiw_url,
				'label'    => __( 'Filter events', 'whoiswho' ),
				'controls' => array(
					array(
						'name'    => 'city',
						'label'   => __( 'City', 'whoiswho' ),
						'blank'   => __( 'All cities', 'whoiswho' ),
						'options' => $wiw_cities,
						'value'   => $wiw_filters['city'],
					),
					array(
						'name'    => 'industry',
						'label'   => __( 'Industry', 'whoiswho' ),
						'blank'   => __( 'All industries', 'whoiswho' ),
						'options' => wiw_events_industry_options( $wiw_all ),
						'value'   => $wiw_filters['industry'],
					),
				),
				/* translators: %d: number of events. */
				'count'    => sprintf( _n( '%d event', '%d events', $wiw_total, 'whoiswho' ), $wiw_total ),
				'clear'    => $wiw_on,
			)
		);
		?>
		<?php if ( array() !== $wiw_upcoming ) : ?>
			<h2 class="ev-h"><?php esc_html_e( 'Upcoming', 'whoiswho' ); ?></h2>
			<?php get_template_part( 'template-parts/event-month-group', null, array( 'events' => $wiw_upcoming ) ); ?>
		<?php else : ?>
			<?php
			get_template_part(
				'template-parts/empty-state',
				null,
				array(
					'title'        => $wiw_on ? __( 'No upcoming events match', 'whoiswho' ) : __( 'No upcoming events yet', 'whoiswho' ),
					'text'         => $wiw_on ? __( 'Try another city or industry.', 'whoiswho' ) : __( 'New events are added here as organisers announce them.', 'whoiswho' ),
					'action_label' => $wiw_on ? __( 'Clear filters', 'whoiswho' ) : '',
					'action_url'   => $wiw_on ? $wiw_url : '',
				)
			);
			?>
		<?php endif; ?>
		<?php if ( array() !== $wiw_past ) : ?>
			<h2 class="ev-h ev-h--past"><?php esc_html_e( 'Past events', 'whoiswho' ); ?></h2>
			<div class="ag-past">
				<?php get_template_part( 'template-parts/event-month-group', null, array( 'events' => $wiw_past ) ); ?>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
