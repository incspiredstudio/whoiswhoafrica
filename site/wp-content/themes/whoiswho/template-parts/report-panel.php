<?php
/**
 * The "Something wrong?" panel in a profile's right rail and the Report dialog (REQ-090, REQ-157, REQ-233). The Report link goes
 * to the report page, so it works without scripts; with scripts it opens the dialog, which holds the same form and posts to the
 * same address. The dialog is a native modal: it traps focus, Escape closes it and focus returns to the button. Args: profile.
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
$wiw_report = home_url( '/report/' . $wiw_profile->slug . '/' );
?>
<div class="panel panel--ruled report-panel">
	<h2><?php esc_html_e( 'Something wrong?', 'whoiswho' ); ?></h2>
	<p class="small muted"><?php esc_html_e( 'No account is needed. A person reads every message.', 'whoiswho' ); ?></p>
	<div class="rail-actions">
		<a class="btn alt sm" href="<?php echo esc_url( home_url( '/correct/' . $wiw_profile->slug . '/' ) ); ?>"><?php esc_html_e( 'Suggest a correction', 'whoiswho' ); ?></a>
		<a class="btn alt sm" href="<?php echo esc_url( $wiw_report ); ?>" data-report-open="report-dialog" aria-haspopup="dialog"><?php esc_html_e( 'Report this profile', 'whoiswho' ); ?></a>
		<a class="btn alt sm bad" href="<?php echo esc_url( home_url( '/remove/' . $wiw_profile->slug . '/' ) ); ?>"><?php esc_html_e( 'Request removal', 'whoiswho' ); ?></a>
	</div>
	<p class="small muted"><a href="<?php echo esc_url( home_url( '/complaints/' ) ); ?>"><?php esc_html_e( 'Complaints and takedown', 'whoiswho' ); ?></a></p>
</div>
<dialog class="dlg" id="report-dialog" aria-labelledby="report-dialog-h">
	<div class="dlg-head">
		<h2 id="report-dialog-h"><?php esc_html_e( 'Report this profile', 'whoiswho' ); ?></h2>
		<button type="button" class="icon-btn" data-report-close aria-label="<?php esc_attr_e( 'Close', 'whoiswho' ); ?>"><?php echo wiw_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></button>
	</div>
	<p class="small muted"><?php esc_html_e( 'Tell us what is wrong. It goes to our staff queue.', 'whoiswho' ); ?></p>
	<?php
	get_template_part(
		'template-parts/forms/request-form',
		null,
		array(
			'kind'    => 'report',
			'profile' => $wiw_profile,
			'prefix'  => 'dlg',
		)
	);
	?>
</dialog>
