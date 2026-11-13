<?php
/**
 * Get listed (REQ-177, REQ-120, REQ-058): an enquiry form with some explanation, not a shop. Side text says what the team can do;
 * the form takes a name, organisation, interest, an email or phone, a message and a consent tick. There is no price, package, tier,
 * cart or checkout wording anywhere: money is agreed offline. The plugin handles the POST at /subscribe.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
wiw_page_band(
	array(
		'eyebrow' => __( 'Get listed', 'whoiswho' ),
		'title'   => __( 'Tell us what you need', 'whoiswho' ),
		'lede'    => __( 'Send an enquiry and our team will contact you. Every listing has the same format.', 'whoiswho' ),
	)
);
$wiw_can       = array(
	__( 'Create or improve a listing for a person, business or institution.', 'whoiswho' ),
	__( 'Publish a story. Sponsored stories are always labelled as Sponsored.', 'whoiswho' ),
	__( 'Media and editorial work.', 'whoiswho' ),
	__( 'Sponsor the awards. Sponsors have no say over who is nominated or who wins.', 'whoiswho' ),
);
$wiw_interests = array(
	'listing_a_profile'       => __( 'Listing a profile', 'whoiswho' ),
	'sponsored_story'         => __( 'A sponsored story', 'whoiswho' ),
	'media_or_editorial_work' => __( 'Media or editorial work', 'whoiswho' ),
	'awards_sponsorship'      => __( 'Awards sponsorship', 'whoiswho' ),
	'not_sure'                => __( 'Not sure yet', 'whoiswho' ),
);
?>
<section class="section section--tight">
	<div class="wrap form-grid">
		<aside class="form-aside" aria-labelledby="gl-can-h">
			<h2 id="gl-can-h"><?php esc_html_e( 'What the team can do', 'whoiswho' ); ?></h2>
			<ul class="ticks">
				<?php foreach ( $wiw_can as $wiw_line ) : ?>
					<li><?php echo wiw_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><span><?php echo esc_html( $wiw_line ); ?></span></li>
				<?php endforeach; ?>
			</ul>
			<p class="meta"><?php esc_html_e( 'Terms are agreed with our team offline. Nothing is charged on this site.', 'whoiswho' ); ?></p>
			<p class="meta"><?php esc_html_e( 'Only have a question? Use the contact page.', 'whoiswho' ); ?> <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact us', 'whoiswho' ); ?></a></p>
		</aside>
		<form class="form" method="post" action="<?php echo esc_url( home_url( '/subscribe/' ) ); ?>" novalidate>
			<h2 class="sr"><?php esc_html_e( 'Send an enquiry', 'whoiswho' ); ?></h2>
			<?php wiw_form_banner(); ?>
			<?php
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'name',
					'label'    => __( 'Your name', 'whoiswho' ),
					'required' => true,
					'value'    => wiw_form_value( 'name' ),
					'error'    => wiw_form_error( 'name' ),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'  => 'organisation',
					'label' => __( 'Organisation', 'whoiswho' ),
					'hint'  => __( 'Optional.', 'whoiswho' ),
					'value' => wiw_form_value( 'organisation' ),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'interest',
					'type'     => 'select',
					'label'    => __( 'Interested in', 'whoiswho' ),
					'required' => true,
					'options'  => $wiw_interests,
					'value'    => wiw_form_value( 'interest' ),
					'error'    => wiw_form_error( 'interest' ),
				)
			);
			$wiw_contact_error = wiw_form_error( 'contact' );
			?>
			<fieldset<?php echo '' !== $wiw_contact_error ? ' aria-describedby="gl-contact-err"' : ''; ?>>
				<legend><?php esc_html_e( 'How can we reach you?', 'whoiswho' ); ?></legend>
				<?php
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'  => 'email',
						'type'  => 'email',
						'label' => __( 'Email', 'whoiswho' ),
						'hint'  => __( 'Email or phone is needed.', 'whoiswho' ),
						'value' => wiw_form_value( 'email' ),
						'error' => wiw_form_error( 'email' ),
					)
				);
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'  => 'phone',
						'type'  => 'tel',
						'label' => __( 'Phone', 'whoiswho' ),
						'value' => wiw_form_value( 'phone' ),
						'error' => wiw_form_error( 'phone' ),
					)
				);
				?>
				<?php if ( '' !== $wiw_contact_error ) : ?>
					<p class="err" id="gl-contact-err" role="alert"><?php esc_html_e( 'Please give an email address or a phone number.', 'whoiswho' ); ?></p>
				<?php endif; ?>
			</fieldset>
			<?php
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'  => 'message',
					'type'  => 'textarea',
					'label' => __( 'Anything else?', 'whoiswho' ),
					'value' => wiw_form_value( 'message' ),
					'error' => wiw_form_error( 'message' ),
				)
			);
			get_template_part(
				'template-parts/forms/consent-tick',
				null,
				array(
					'name'  => 'consent',
					'label' => __( 'I agree to be contacted about this enquiry, as set out in the', 'whoiswho' ),
					'error' => wiw_form_error( 'consent' ),
				)
			);
			get_template_part( 'template-parts/forms/bot-check', null, array( 'form' => 'get_listed' ) );
			get_template_part( 'template-parts/forms/privacy-notice' );
			?>
			<button class="btn" type="submit"><?php esc_html_e( 'Send enquiry', 'whoiswho' ); ?></button>
		</form>
	</div>
</section>
<?php
get_footer();
