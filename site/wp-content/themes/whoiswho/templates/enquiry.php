<?php
/**
 * Enquiry page for one profile at /enquire/{slug} (REQ-018, REQ-081, REQ-122): a short form (name, email, message) with the bot
 * check and privacy notice, or a plain notice when the profile's enquiries are switched off. The plugin relays the enquiry to
 * staff; the owner's email and phone are never shown. When it refuses a submission, the message (including the try-later message)
 * and the field errors show here, and there is no success state.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = wiw_enquiry_profile();
get_header();
if ( null === $wiw_profile ) {
	get_template_part(
		'template-parts/notice',
		null,
		array(
			'kind' => 'info',
			'text' => __( 'This profile is not available.', 'whoiswho' ),
		)
	);
	get_footer();
	return;
}
$wiw_back = home_url( '/profile/' . $wiw_profile->slug . '/' );
wiw_page_band(
	array(
		'eyebrow' => __( 'Enquiry', 'whoiswho' ),
		/* translators: %s: profile name */
		'title'   => sprintf( __( 'Enquire about %s', 'whoiswho' ), $wiw_profile->name ),
		'tone'    => 'dark',
		'lede'    => __( 'We pass your message on. The email address and phone number of the person or organisation are never shown.', 'whoiswho' ),
	)
);
?>
<section class="section section--tight">
	<div class="wrap form-grid">
		<aside class="form-aside" aria-labelledby="enq-aside-h">
			<h2 id="enq-aside-h"><?php esc_html_e( 'How it works', 'whoiswho' ); ?></h2>
			<ul class="link-rows">
				<li><span><strong><?php esc_html_e( 'A person reads it', 'whoiswho' ); ?></strong><span class="meta"><?php esc_html_e( 'Our team reads each enquiry and passes it on or replies by email.', 'whoiswho' ); ?></span></span></li>
				<li><span><strong><?php esc_html_e( 'Contact details stay private', 'whoiswho' ); ?></strong><span class="meta"><?php esc_html_e( 'Your email is shown to our team only.', 'whoiswho' ); ?></span></span></li>
			</ul>
			<p><a href="<?php echo esc_url( $wiw_back ); ?>"><?php esc_html_e( 'Back to the profile', 'whoiswho' ); ?></a></p>
		</aside>
		<?php if ( ! $wiw_profile->enquiries_open ) : ?>
			<div class="form">
				<h2 class="sr"><?php esc_html_e( 'Enquiries are switched off', 'whoiswho' ); ?></h2>
				<?php wiw_form_banner(); ?>
				<p class="notice notice--info" role="note"><?php esc_html_e( 'Enquiries are switched off for this profile. Please use the contact page instead.', 'whoiswho' ); ?></p>
				<p><a class="btn" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Go to the contact page', 'whoiswho' ); ?></a></p>
			</div>
		<?php else : ?>
			<form class="form" method="post" action="<?php echo esc_url( home_url( '/enquire/' . $wiw_profile->slug . '/' ) ); ?>" novalidate>
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
						'name'     => 'email',
						'type'     => 'email',
						'label'    => __( 'Your email', 'whoiswho' ),
						'required' => true,
						'hint'     => __( 'Only used to relay the reply. Never published.', 'whoiswho' ),
						'value'    => wiw_form_value( 'email' ),
						'error'    => wiw_form_error( 'email' ),
					)
				);
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'     => 'message',
						'type'     => 'textarea',
						'label'    => __( 'Message', 'whoiswho' ),
						'required' => true,
						'value'    => wiw_form_value( 'message' ),
						'error'    => wiw_form_error( 'message' ),
					)
				);
				get_template_part( 'template-parts/forms/bot-check', null, array( 'form' => 'enquiry' ) );
				get_template_part( 'template-parts/forms/privacy-notice' );
				?>
				<button class="btn" type="submit"><?php esc_html_e( 'Send enquiry', 'whoiswho' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
