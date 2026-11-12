<?php
/**
 * Contact page (REQ-039, REQ-081, REQ-058): a short form (name, email, message) with the bot check and privacy notice, a promise
 * that a person reads every message, and a side panel of the common alternatives. The plugin handles the POST; when it refuses
 * one, the message and field errors show here. No email address or phone number is published.
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
		'eyebrow' => __( 'Contact', 'whoiswho' ),
		'title'   => __( 'Get in touch', 'whoiswho' ),
		'lede'    => __( 'A person reads every message. The quickest way to change a profile is the correction form on that profile.', 'whoiswho' ),
	)
);
$wiw_alternatives = array(
	array( __( 'Correct or remove a profile', 'whoiswho' ), __( 'Find the profile, then use its correction or removal form.', 'whoiswho' ), home_url( '/directory/' ) ),
	array( __( 'Get listed', 'whoiswho' ), __( 'Ask us to create or improve a listing.', 'whoiswho' ), home_url( '/subscribe/' ) ),
	array( __( 'Complaints and takedown', 'whoiswho' ), __( 'How we handle complaints and removal requests.', 'whoiswho' ), home_url( '/complaints/' ) ),
	array( __( 'Your data rights', 'whoiswho' ), __( 'See, correct or erase what we hold about you.', 'whoiswho' ), home_url( '/data-rights/' ) ),
);
?>
<section class="section section--tight">
	<div class="wrap form-grid">
		<aside class="form-aside" aria-labelledby="contact-alt-h">
			<h2 id="contact-alt-h"><?php esc_html_e( 'Usually quicker', 'whoiswho' ); ?></h2>
			<ul class="link-rows">
				<?php foreach ( $wiw_alternatives as $wiw_alt ) : ?>
					<li>
						<a href="<?php echo esc_url( $wiw_alt[2] ); ?>"><strong><?php echo esc_html( $wiw_alt[0] ); ?></strong><span class="meta"><?php echo esc_html( $wiw_alt[1] ); ?></span></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</aside>
		<form class="form" method="post" action="<?php echo esc_url( home_url( '/contact/' ) ); ?>" novalidate>
			<h2 class="sr"><?php esc_html_e( 'Send a message', 'whoiswho' ); ?></h2>
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
					'label'    => __( 'Email', 'whoiswho' ),
					'required' => true,
					'hint'     => __( 'We reply here. We never publish it.', 'whoiswho' ),
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
			<button class="btn" type="submit"><?php esc_html_e( 'Send message', 'whoiswho' ); ?></button>
		</form>
	</div>
</section>
<?php
get_footer();
