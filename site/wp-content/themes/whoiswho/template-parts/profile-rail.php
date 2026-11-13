<?php
/**
 * Right rail of a profile (REQ-142, REQ-156, REQ-006): a contact slot, and the "Something wrong?" actions: Claim (hidden when the
 * profile is claimed or pending review), Suggest a correction, Request removal and Report. The share preview panel is a design
 * illustration and is never rendered on the live page. Reads the profile only through PublicProfile. Args: profile (PublicProfile).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = isset( $args['profile'] ) ? $args['profile'] : null;
if ( ! $wiw_profile instanceof PublicProfile ) {
	return;
}
$wiw_slug = rawurlencode( $wiw_profile->slug );

/*
 * The contact block is filled by the enquiry feature (a form trigger, or a notice when enquiries are switched off). Until a
 * handler prints something, the slot stays absent. The profile never shows an email address or phone number.
 */
ob_start();
do_action( 'wiw_profile_contact', $wiw_profile );
$wiw_contact   = trim( (string) ob_get_clean() );
$wiw_can_claim = ! $wiw_profile->claimed && null === $wiw_profile->label;
?>
<?php if ( '' !== $wiw_contact ) : ?>
	<section class="rail-card rail-contact" aria-labelledby="rail-contact-h">
		<h2 id="rail-contact-h"><?php esc_html_e( 'Contact', 'whoiswho' ); ?></h2>
		<?php echo $wiw_contact; // phpcs:ignore WordPress.Security.EscapeOutput -- printed by the enquiry feature through its own escaping. ?>
	</section>
<?php endif; ?>
<section class="rail-card rail-wrong" aria-labelledby="rail-wrong-h">
	<h2 id="rail-wrong-h"><?php esc_html_e( 'Something wrong?', 'whoiswho' ); ?></h2>
	<div class="rail-actions">
		<?php if ( $wiw_can_claim ) : ?>
			<a class="btn" data-action="claim" href="<?php echo esc_url( home_url( '/claim/' . $wiw_slug . '/' ) ); ?>"><?php esc_html_e( 'Claim this profile', 'whoiswho' ); ?></a>
		<?php endif; ?>
		<a class="btn alt" data-action="correct" href="<?php echo esc_url( home_url( '/correct/' . $wiw_slug . '/' ) ); ?>"><?php esc_html_e( 'Suggest a correction', 'whoiswho' ); ?></a>
		<a class="btn alt" data-action="remove" href="<?php echo esc_url( home_url( '/remove/' . $wiw_slug . '/' ) ); ?>"><?php esc_html_e( 'Request removal', 'whoiswho' ); ?></a>
		<a class="btn alt" data-action="report" href="<?php echo esc_url( home_url( '/report/' . $wiw_slug . '/' ) ); ?>"><?php esc_html_e( 'Report this profile', 'whoiswho' ); ?></a>
	</div>
</section>
