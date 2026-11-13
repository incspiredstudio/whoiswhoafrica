<?php
/**
 * The data-rights request form (REQ-059, REQ-058): the kind of request, name, email and details, with the bot check and the
 * privacy notice. Posts to /data-rights/ (the plugin validates, protects and stores it and sends the confirmation email); a
 * refusal comes back to the same page with the message and the field errors. A profile has a faster route, the correction and
 * removal forms, which the aside points to.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_kinds = array(
	'access'      => __( 'See what you hold about me (access)', 'whoiswho' ),
	'correction'  => __( 'Correct what you hold about me', 'whoiswho' ),
	'erasure'     => __( 'Erase what you hold about me', 'whoiswho' ),
	'objection'   => __( 'Object to how you use my information', 'whoiswho' ),
	'portability' => __( 'Send me a copy I can move (portability)', 'whoiswho' ),
);
?>
<section class="section section--tight" aria-labelledby="dr-form-h">
	<div class="wrap form-grid">
		<aside class="form-aside" aria-labelledby="dr-fast-h">
			<h2 id="dr-fast-h"><?php esc_html_e( 'Quickest for a profile', 'whoiswho' ); ?></h2>
			<p><?php esc_html_e( 'If your request is about a profile in the directory, the profile forms are the fastest route. Find the profile, then use its correction or removal form.', 'whoiswho' ); ?></p>
			<ul class="link-rows">
				<li>
					<a href="<?php echo esc_url( home_url( '/directory/' ) ); ?>"><strong><?php esc_html_e( 'Find a profile', 'whoiswho' ); ?></strong><span class="meta"><?php esc_html_e( 'Each profile has a Correct this and a Remove this link.', 'whoiswho' ); ?></span></a>
				</li>
				<li>
					<a href="<?php echo esc_url( home_url( '/complaints/' ) ); ?>"><strong><?php esc_html_e( 'Complaints and takedown', 'whoiswho' ); ?></strong><span class="meta"><?php esc_html_e( 'How we handle corrections and removals.', 'whoiswho' ); ?></span></a>
				</li>
			</ul>
		</aside>
		<form class="form" method="post" action="<?php echo esc_url( home_url( '/data-rights/' ) ); ?>" novalidate>
			<h2 id="dr-form-h"><?php esc_html_e( 'Make a data request', 'whoiswho' ); ?></h2>
			<?php wiw_form_banner(); ?>
			<?php
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'kind',
					'type'     => 'select',
					'label'    => __( 'What would you like to do?', 'whoiswho' ),
					'required' => true,
					'options'  => $wiw_kinds,
					'value'    => wiw_form_value( 'kind' ),
					'error'    => wiw_form_error( 'kind' ),
				)
			);
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
					'hint'     => __( 'We send a link here to confirm the request. We never publish it.', 'whoiswho' ),
					'value'    => wiw_form_value( 'email' ),
					'error'    => wiw_form_error( 'email' ),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'details',
					'type'     => 'textarea',
					'label'    => __( 'Details', 'whoiswho' ),
					'required' => true,
					'hint'     => __( 'Tell us what the request is about, for example the name of the profile.', 'whoiswho' ),
					'value'    => wiw_form_value( 'details' ),
					'error'    => wiw_form_error( 'details' ),
				)
			);
			get_template_part( 'template-parts/forms/bot-check', null, array( 'form' => 'data_rights' ) );
			get_template_part( 'template-parts/forms/privacy-notice' );
			?>
			<button class="btn" type="submit"><?php esc_html_e( 'Send request', 'whoiswho' ); ?></button>
		</form>
	</div>
</section>
