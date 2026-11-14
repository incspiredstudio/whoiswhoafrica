<?php
/**
 * Bot check: the honeypot (a labelled field people leave empty, kept out of the tab order and hidden from assistive technology)
 * and the signed form token from the plugin. When the Administrator has saved both Cloudflare Turnstile keys (Setup screen), the
 * Turnstile widget is added too and its script is loaded in the footer (REQ-081). Args: form (the form type the token is issued for).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_form      = isset( $args['form'] ) ? (string) $args['form'] : 'generic';
$wiw_token     = class_exists( '\WhoIsWho\Core\Platform\Forms\FormToken' ) ? \WhoIsWho\Core\Platform\Forms\FormToken::for_site()->issue( $wiw_form ) : '';
$wiw_turnstile = class_exists( '\WhoIsWho\Core\Compliance\Forms\TurnstileSettings' ) && \WhoIsWho\Core\Compliance\Forms\TurnstileSettings::active()
	? \WhoIsWho\Core\Compliance\Forms\TurnstileSettings::site_key()
	: '';
?>
<div class="bot-check" aria-hidden="true">
	<label for="wiw_website"><?php esc_html_e( 'Leave this field empty', 'whoiswho' ); ?></label>
	<input type="text" id="wiw_website" name="wiw_website" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="wiw_form_token" value="<?php echo esc_attr( $wiw_token ); ?>">
<?php
if ( '' !== $wiw_turnstile ) :
	// The provider serves one current version at this address, so no version is added.
	wp_enqueue_script( 'wiw-turnstile', \WhoIsWho\Core\Compliance\Forms\TurnstileSettings::SCRIPT_URL, array(), null, array( 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	?>
<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $wiw_turnstile ); ?>" data-appearance="interaction-only"></div>
<?php endif; ?>
