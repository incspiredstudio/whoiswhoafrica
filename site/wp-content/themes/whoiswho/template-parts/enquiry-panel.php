<?php
/**
 * The Contact panel in a profile's right rail (REQ-018, REQ-081, REQ-122): the enquiry button, or a plain statement when the
 * profile's enquiries are switched off. Contact details are never shown. Args: profile (PublicProfile).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = isset( $args['profile'] ) && $args['profile'] instanceof PublicProfile ? $args['profile'] : null;
if ( null === $wiw_profile ) {
	return;
}
?>
<div class="panel panel--ruled enquiry-panel">
	<h2><?php esc_html_e( 'Contact', 'whoiswho' ); ?></h2>
	<p class="small muted"><?php esc_html_e( 'Contact details are not shown. We pass enquiries on.', 'whoiswho' ); ?></p>
	<?php if ( $wiw_profile->enquiries_open ) : ?>
		<a class="btn wide" href="<?php echo esc_url( home_url( '/enquire/' . $wiw_profile->slug . '/' ) ); ?>"><?php esc_html_e( 'Send an enquiry', 'whoiswho' ); ?></a>
	<?php else : ?>
		<p class="small muted"><?php esc_html_e( 'Enquiries are switched off for this profile.', 'whoiswho' ); ?></p>
	<?php endif; ?>
</div>
