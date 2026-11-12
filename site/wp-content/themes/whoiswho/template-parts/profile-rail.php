<?php
/**
 * Right rail of a profile (REQ-142, REQ-156): the contact block (an enquiry link, never an email or phone, or a notice when
 * enquiries are switched off) and the "Something wrong?" actions: Claim (only for a reviewed, unclaimed profile), Suggest a
 * correction, Request removal and Report. All work without an account. There is no share preview here (REQ-006).
 * Args: profile (PublicProfile).
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
/**
 * Whether the profile accepts enquiries. A profile owner can ask staff to switch enquiries off.
 *
 * @param bool          $open    Default true.
 * @param PublicProfile $profile Profile.
 */
$wiw_enquiries = (bool) apply_filters( 'wiw_profile_enquiries_open', true, $wiw_profile );
$wiw_slug      = rawurlencode( $wiw_profile->slug );
?>
<section class="rail-block" aria-labelledby="rail-contact-h">
	<h2 class="rail-h" id="rail-contact-h"><?php esc_html_e( 'Contact', 'whoiswho' ); ?></h2>
	<?php if ( $wiw_enquiries ) : ?>
		<p class="rail-note"><?php esc_html_e( 'Messages go to our team, who pass them on. No contact details are published.', 'whoiswho' ); ?></p>
		<a class="btn wide" href="<?php echo esc_url( home_url( '/contact/?profile=' . $wiw_slug ) ); ?>"><?php esc_html_e( 'Send an enquiry', 'whoiswho' ); ?></a>
	<?php else : ?>
		<p class="rail-note"><?php esc_html_e( 'This profile is not accepting enquiries.', 'whoiswho' ); ?></p>
	<?php endif; ?>
</section>
<section class="rail-block" aria-labelledby="rail-wrong-h">
	<h2 class="rail-h" id="rail-wrong-h"><?php esc_html_e( 'Something wrong?', 'whoiswho' ); ?></h2>
	<div class="rail-actions">
		<?php if ( $wiw_profile->claimable ) : ?>
			<a class="btn alt sm" href="<?php echo esc_url( home_url( '/claim/' . $wiw_slug . '/' ) ); ?>"><?php esc_html_e( 'Claim this profile', 'whoiswho' ); ?></a>
		<?php endif; ?>
		<a class="btn alt sm" href="<?php echo esc_url( home_url( '/correct/' . $wiw_slug . '/' ) ); ?>"><?php esc_html_e( 'Suggest a correction', 'whoiswho' ); ?></a>
		<a class="btn alt sm" href="<?php echo esc_url( home_url( '/remove/' . $wiw_slug . '/' ) ); ?>"><?php esc_html_e( 'Request removal', 'whoiswho' ); ?></a>
		<a class="btn alt sm" href="<?php echo esc_url( home_url( '/report/' . $wiw_slug . '/' ) ); ?>"><?php esc_html_e( 'Report this profile', 'whoiswho' ); ?></a>
	</div>
</section>
