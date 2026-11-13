<?php
/**
 * Invitation page at /invite/{token} (REQ-013, REQ-175, REQ-233, REQ-141): the profile form with a draft held against the link,
 * the picture upload with feedback on type and size, field messages when free text holds personal details, the confirmation after
 * sending, and the one "Link expired or already used" page with a way to ask the team for a new link. Drawn from the state the
 * plugin sends; this template decides nothing. No price, package or payment field exists. Args: state, errors, values, token.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_a      = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'state'  => 'expired',
		'errors' => array(),
		'values' => array(),
		'token'  => '',
	)
);
$wiw_state  = (string) $wiw_a['state'];
$wiw_errors = (array) $wiw_a['errors'];
$wiw_values = (array) $wiw_a['values'];
$wiw_val    = static fn ( string $key ): string => isset( $wiw_values[ $key ] ) ? (string) $wiw_values[ $key ] : '';

if ( 'expired' === $wiw_state ) {
	wiw_page_band(
		array(
			'title' => __( 'Link expired or already used', 'whoiswho' ),
			'lede'  => __( 'This invitation link has expired or has already been used. Each link works once.', 'whoiswho' ),
		)
	);
	?>
	<section class="section section--tight">
		<div class="wrap wrap--text">
			<p><a class="btn" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Ask the team for a new link', 'whoiswho' ); ?></a></p>
		</div>
	</section>
	<?php
	return;
}

if ( 'limited' === $wiw_state ) {
	wiw_page_band(
		array(
			'title' => __( 'Please try again later', 'whoiswho' ),
			'lede'  => __( 'There have been too many attempts from here. Please wait a while and try again.', 'whoiswho' ),
		)
	);
	return;
}

if ( 'done' === $wiw_state ) {
	get_template_part(
		'template-parts/done',
		null,
		array(
			'title' => __( 'Thank you', 'whoiswho' ),
			'text'  => __( 'Your profile has been sent for review. Nothing is published until a reviewer approves it.', 'whoiswho' ),
		)
	);
	return;
}

wiw_page_band(
	array(
		'eyebrow' => __( 'Your invitation', 'whoiswho' ),
		'title'   => __( 'Your profile', 'whoiswho' ),
		'lede'    => __( 'Tell us about your organisation or yourself. Nothing is published until our team has checked it, and you can save a draft and come back to this link until it expires.', 'whoiswho' ),
	)
);
$wiw_file_error = wiw_invitation_error_text( $wiw_errors, 'image' );
?>
<section class="section section--tight">
	<div class="wrap wrap--text">
		<form class="form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( home_url( '/invite/' . rawurlencode( (string) $wiw_a['token'] ) ) ); ?>" novalidate>
			<?php if ( 'saved' === $wiw_state ) : ?>
				<p class="notice notice--info" role="status"><?php esc_html_e( 'Your draft is saved. You can come back to this link until it expires.', 'whoiswho' ); ?></p>
			<?php endif; ?>
			<?php if ( array() !== $wiw_errors ) : ?>
				<div class="errbox" role="alert"><p><?php esc_html_e( 'Please check the highlighted fields.', 'whoiswho' ); ?></p></div>
			<?php endif; ?>
			<?php
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'name',
					'label'    => __( 'Name', 'whoiswho' ),
					'required' => true,
					'hint'     => __( 'The name of your organisation, or your own name if the profile is about you.', 'whoiswho' ),
					'value'    => $wiw_val( 'name' ),
					'error'    => wiw_invitation_error_text( $wiw_errors, 'name' ),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'type',
					'type'     => 'select',
					'label'    => __( 'This profile is for', 'whoiswho' ),
					'required' => true,
					'options'  => array(
						'organisation' => __( 'An organisation', 'whoiswho' ),
						'individual'   => __( 'An individual', 'whoiswho' ),
					),
					'value'    => $wiw_val( 'type' ),
					'error'    => wiw_invitation_error_text( $wiw_errors, 'type' ),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'  => 'summary',
					'type'  => 'textarea',
					'label' => __( 'Short description', 'whoiswho' ),
					'hint'  => __( 'Up to 1,500 characters. Do not include phone numbers, email addresses, home addresses or ages.', 'whoiswho' ),
					'value' => $wiw_val( 'summary' ),
					'error' => wiw_invitation_error_text( $wiw_errors, 'summary' ),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'  => 'website',
					'type'  => 'url',
					'label' => __( 'Website', 'whoiswho' ),
					'hint'  => __( 'Optional. Start with https://', 'whoiswho' ),
					'value' => $wiw_val( 'website' ),
					'error' => wiw_invitation_error_text( $wiw_errors, 'website' ),
				)
			);
			?>
			<div class="field">
				<label for="f-image"><?php esc_html_e( 'Picture', 'whoiswho' ); ?></label>
				<input type="file" id="f-image" name="image" accept="image/jpeg,image/png,image/webp" aria-describedby="f-image-hint<?php echo '' !== $wiw_file_error ? ' f-image-err' : ''; ?>"<?php echo '' !== $wiw_file_error ? ' aria-invalid="true"' : ''; ?>>
				<span class="hint" id="f-image-hint"><?php esc_html_e( 'Optional. JPEG, PNG or WebP, up to 2 MB. Choose the file again if the form comes back with a message.', 'whoiswho' ); ?></span>
				<?php if ( '' !== $wiw_file_error ) : ?>
					<p class="err" id="f-image-err" role="alert"><?php echo esc_html( $wiw_file_error ); ?></p>
				<?php endif; ?>
			</div>
			<?php get_template_part( 'template-parts/forms/privacy-notice' ); ?>
			<?php get_template_part( 'template-parts/forms/bot-check', null, array( 'form' => 'invitation' ) ); ?>
			<div class="row">
				<button class="btn alt" type="submit" name="submit" value="draft"><?php esc_html_e( 'Save draft', 'whoiswho' ); ?></button>
				<button class="btn" type="submit" name="submit" value="send"><?php esc_html_e( 'Send for review', 'whoiswho' ); ?></button>
			</div>
		</form>
	</div>
</section>
