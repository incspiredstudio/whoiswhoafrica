<?php
/**
 * The Report dialog of a profile (REQ-090, REQ-157, REQ-233). The "Something wrong?" actions are one card, in profile-rail.php, so they
 * are not drawn twice. The Report link there goes to the report page, so it works without scripts; with scripts it opens this dialog,
 * which holds the same form and posts to the same address. The dialog is a native modal: it traps focus, Escape closes it and focus returns to the button. Args: profile.
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
