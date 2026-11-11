<?php
/**
 * Event page at /events/{slug} (REQ-262, REQ-264, REQ-265): a poster (orange, charcoal when past), the details card beside the
 * text (above it on phones), the Related stories list when published stories link the event, and More events as agenda rows.
 * Reads events only through PublicEvent; a draft or private event answers 404. Structured data comes from the guard plugin,
 * no markup for it here. No registration or payment is collected: the Register link goes to the organiser.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Content\PublicEvent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_event = PublicEvent::find( (int) get_queried_object_id() );
if ( null === $wiw_event ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	get_template_part( '404' );
	return;
}
$wiw_more = array_values(
	array_filter(
		PublicEvent::next_upcoming( 4 ),
		static fn ( array $e ): bool => (int) $e['id'] !== (int) $wiw_event['id']
	)
);
$wiw_more = array_slice( $wiw_more, 0, 3 );
$wiw_list = get_post_type_archive_link( 'wiw_event' );

get_header();
get_template_part(
	'template-parts/event-poster',
	null,
	array(
		'event'      => $wiw_event,
		'events_url' => false !== $wiw_list ? $wiw_list : home_url( '/events/' ),
	)
);
?>
<section class="ev-layout">
	<div class="wrap ev-layout-in">
		<div class="ev-side">
			<?php get_template_part( 'template-parts/event-details', null, array( 'event' => $wiw_event ) ); ?>
		</div>
		<div class="ev-text prose">
			<?php if ( '' !== (string) $wiw_event['summary'] ) : ?>
				<p class="ev-sum"><?php echo esc_html( (string) $wiw_event['summary'] ); ?></p>
			<?php endif; ?>
			<?php echo wp_kses_post( (string) $wiw_event['body'] ); ?>
			<?php
			get_template_part(
				'template-parts/event-related',
				null,
				array( 'stories' => (array) $wiw_event['stories'] )
			);
			?>
		</div>
	</div>
</section>
<?php if ( array() !== $wiw_more ) : ?>
	<section class="section ev-more">
		<div class="wrap">
			<h2 class="ev-h2"><?php esc_html_e( 'More events', 'whoiswho' ); ?></h2>
			<ul class="ag-list">
				<?php
				foreach ( $wiw_more as $wiw_more_event ) {
					get_template_part( 'template-parts/event-row', null, array( 'event' => $wiw_more_event ) );
				}
				?>
			</ul>
			<p><a class="btn alt sm" href="<?php echo esc_url( false !== $wiw_list ? $wiw_list : home_url( '/events/' ) ); ?>"><?php esc_html_e( 'All events', 'whoiswho' ); ?></a></p>
		</div>
	</section>
<?php endif; ?>
<?php
get_footer();
