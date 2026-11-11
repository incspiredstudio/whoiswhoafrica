<?php
/**
 * Bot check: the honeypot (a labelled field people leave empty, kept out of the tab order and hidden from assistive technology)
 * and the signed form token from the plugin. Args: form (the form type the token is issued for).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_form  = isset( $args['form'] ) ? (string) $args['form'] : 'generic';
$wiw_token = class_exists( '\WhoIsWho\Core\Platform\Forms\FormToken' ) ? \WhoIsWho\Core\Platform\Forms\FormToken::for_site()->issue( $wiw_form ) : '';
?>
<div class="bot-check" aria-hidden="true">
	<label for="wiw_website"><?php esc_html_e( 'Leave this field empty', 'whoiswho' ); ?></label>
	<input type="text" id="wiw_website" name="wiw_website" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="wiw_form_token" value="<?php echo esc_attr( $wiw_token ); ?>">
