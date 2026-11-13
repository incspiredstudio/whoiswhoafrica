<?php
/**
 * The Claim this profile button in a profile's right rail (REQ-156, REQ-252, REQ-226). It prints only when the PublicProfile read
 * model says the profile can be claimed: reviewed and not yet claimed. A claimed profile and a profile still pending review get
 * nothing (a claim on a pending profile is refused by REQ-226, so the button is hidden rather than offered). The part never
 * prints a Claimed word, badge or class. Args: profile.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = isset( $args['profile'] ) && $args['profile'] instanceof PublicProfile ? $args['profile'] : null;
if ( null === $wiw_profile || ! $wiw_profile->claimable ) {
	return;
}
?>
<a class="btn alt sm" href="<?php echo esc_url( home_url( '/claim/' . $wiw_profile->slug . '/' ) ); ?>"><?php echo wiw_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><?php esc_html_e( 'Claim this profile', 'whoiswho' ); ?></a>
