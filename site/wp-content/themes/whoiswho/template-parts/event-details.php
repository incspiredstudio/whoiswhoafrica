<?php
/**
 * Event details card (REQ-264, D-005): When, Where, Organiser, Cost and a Register link to the organiser's own site, opened in a
 * new tab. A past event shows "This event has ended" and no registration button. The site collects no registration and no payment.
 * Args: event (PublicEvent array).
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
$wiw_past = empty( $wiw_event['upcoming'] );
$wiw_reg  = ! $wiw_past && ! empty( $wiw_event['registration_url'] ) ? (string) $wiw_event['registration_url'] : '';
$wiw_rows = array(
	__( 'When', 'whoiswho' )      => wiw_event_when( $wiw_event ),
	__( 'Where', 'whoiswho' )     => wiw_event_where( $wiw_event ),
	__( 'Organiser', 'whoiswho' ) => (string) ( $wiw_event['organiser'] ?? '' ),
	__( 'Cost', 'whoiswho' )      => (string) ( $wiw_event['price_note'] ?? '' ),
);
?>
<aside class="ev-card" aria-label="<?php esc_attr_e( 'Event details', 'whoiswho' ); ?>">
	<h2><?php echo $wiw_past ? esc_html__( 'This event has ended', 'whoiswho' ) : esc_html__( 'Event details', 'whoiswho' ); ?></h2>
	<dl class="ev-dl">
		<?php foreach ( $wiw_rows as $wiw_term => $wiw_value ) : ?>
			<?php if ( '' !== $wiw_value ) : ?>
				<div><dt><?php echo esc_html( (string) $wiw_term ); ?></dt><dd><?php echo esc_html( $wiw_value ); ?></dd></div>
			<?php endif; ?>
		<?php endforeach; ?>
	</dl>
	<?php if ( '' !== $wiw_reg ) : ?>
		<a class="btn ev-reg" href="<?php echo esc_url( $wiw_reg ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Register on the organiser’s site', 'whoiswho' ); ?><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><span class="sr"> <?php esc_html_e( '(opens in a new tab)', 'whoiswho' ); ?></span></a>
	<?php endif; ?>
	<p class="small muted">
	<?php
	/* translators: %s: site name. */
	echo esc_html( sprintf( __( 'Registration and any fee are handled by the organiser. %s takes no payment and does not sell tickets.', 'whoiswho' ), wiw_site_name() ) );
	?>
	</p>
</aside>
