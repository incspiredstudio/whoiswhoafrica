<?php
/**
 * Related event card (REQ-265) at the end of a story that links an event, and the Related stories plain list on an event page.
 * Args: event (PublicEvent array) for the card; stories (list of PublicStory items with title, url and type) for the list.
 * Either may be given; with neither, nothing prints.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_event   = isset( $args['event'] ) && is_array( $args['event'] ) ? $args['event'] : array();
$wiw_stories = isset( $args['stories'] ) && is_array( $args['stories'] ) ? $args['stories'] : array();

if ( array() !== $wiw_event && ! empty( $wiw_event['url'] ) ) :
	$wiw_day  = wiw_event_day( $wiw_event );
	$wiw_past = empty( $wiw_event['upcoming'] );
	?>
<aside class="section ev-related" aria-labelledby="evRel">
	<div class="wrap wrap--text">
		<h2 id="evRel" class="np-kicker"><?php esc_html_e( 'Related event', 'whoiswho' ); ?></h2>
		<a class="ev-rel-card" href="<?php echo esc_url( (string) $wiw_event['url'] ); ?>">
			<span class="ev-date" aria-hidden="true"><b><?php echo esc_html( $wiw_day['day'] ); ?></b><?php echo esc_html( $wiw_day['month_short'] ); ?></span>
			<span class="ev-what">
				<strong><?php echo esc_html( (string) ( $wiw_event['title'] ?? '' ) ); ?></strong>
				<span><?php echo esc_html( implode( ' · ', array_filter( array( wiw_event_when( $wiw_event ), wiw_event_where( $wiw_event ) ) ) ) ); ?></span>
				<span>
				<?php
				echo $wiw_past
					? esc_html__( 'This event has ended', 'whoiswho' )
					/* translators: %s: organiser name. */
					: esc_html( sprintf( __( 'Organised by %s', 'whoiswho' ), (string) ( $wiw_event['organiser'] ?? '' ) ) );
				?>
				</span>
			</span>
			<?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?>
		</a>
	</div>
</aside>
<?php endif; ?>
<?php if ( array() !== $wiw_stories ) : ?>
<h2 class="ev-h2"><?php esc_html_e( 'Related stories', 'whoiswho' ); ?></h2>
<ul class="ev-rel-list">
	<?php foreach ( $wiw_stories as $wiw_story ) : ?>
		<li><a href="<?php echo esc_url( (string) ( $wiw_story['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $wiw_story['title'] ?? '' ) ); ?></a><span class="muted small"> <?php echo esc_html( (string) ( $wiw_story['type'] ?? '' ) ); ?></span></li>
	<?php endforeach; ?>
</ul>
<?php endif; ?>
