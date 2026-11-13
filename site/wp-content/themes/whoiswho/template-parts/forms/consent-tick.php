<?php
/**
 * Consent tick for a form that records consent (REQ-058, REQ-081): an unticked checkbox with a link to the privacy notice, and a
 * hidden field carrying the policy version the visitor saw. The plugin stamps the time when it receives the form, so the moment of
 * consent is the server's, not the browser's. Args: name (field name, default "consent"), label (text before the link), purpose
 * (a short code for what is consented to, sent as "consent_purpose"), error (words for a refused tick).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_name    = isset( $args['name'] ) && '' !== (string) $args['name'] ? sanitize_key( (string) $args['name'] ) : 'consent';
$wiw_label   = isset( $args['label'] ) && '' !== (string) $args['label'] ? (string) $args['label'] : __( 'I agree that you may use these details to handle this request, as set out in the', 'whoiswho' );
$wiw_purpose = isset( $args['purpose'] ) ? sanitize_key( (string) $args['purpose'] ) : '';
$wiw_link    = sprintf( '<a href="%s">%s</a>', esc_url( home_url( '/privacy/' ) ), esc_html__( 'privacy notice', 'whoiswho' ) );
get_template_part(
	'template-parts/forms/field',
	null,
	array(
		'name'     => $wiw_name,
		'type'     => 'checkbox',
		'required' => true,
		'label'    => esc_html( $wiw_label ) . ' ' . $wiw_link,
		'error'    => isset( $args['error'] ) ? (string) $args['error'] : '',
	)
);
?>
<input type="hidden" name="policy_version" value="<?php echo esc_attr( wiw_policy_version() ); ?>">
<?php if ( '' !== $wiw_purpose ) : ?>
	<input type="hidden" name="consent_purpose" value="<?php echo esc_attr( $wiw_purpose ); ?>">
<?php endif; ?>
