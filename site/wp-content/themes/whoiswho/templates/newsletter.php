<?php
/**
 * Newsletter sign-up page at /newsletter (REQ-061, REQ-058): an email address and a consent tick, the bot check, and a plain
 * promise about what is sent and how to stop. The plugin handles the POST and sends a confirmation email; nothing is sent
 * until the link in it is used. When it refuses, the message and field errors show here.
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
		'eyebrow' => __( 'Newsletter', 'whoiswho' ),
		'title'   => __( 'New stories by email', 'whoiswho' ),
		'tone'    => 'dark',
		'lede'    => __( 'A short email when we publish new stories. Nothing else, and you can stop at any time.', 'whoiswho' ),
	)
);
?>
<section class="section section--tight">
	<div class="wrap form-grid">
		<aside class="form-aside" aria-labelledby="nl-aside-h">
			<h2 id="nl-aside-h"><?php esc_html_e( 'What to expect', 'whoiswho' ); ?></h2>
			<ul class="link-rows">
				<li><span><strong><?php esc_html_e( 'Confirm first', 'whoiswho' ); ?></strong><span class="meta"><?php esc_html_e( 'We email you a link. You are subscribed only after you use it.', 'whoiswho' ); ?></span></span></li>
				<li><span><strong><?php esc_html_e( 'One click to leave', 'whoiswho' ); ?></strong><span class="meta"><?php esc_html_e( 'Every email has an unsubscribe link.', 'whoiswho' ); ?></span></span></li>
			</ul>
		</aside>
		<form class="form" method="post" action="<?php echo esc_url( home_url( '/newsletter/' ) ); ?>" novalidate>
			<h2 class="sr"><?php esc_html_e( 'Sign up', 'whoiswho' ); ?></h2>
			<?php wiw_form_banner(); ?>
			<?php
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'email',
					'type'     => 'email',
					'label'    => __( 'Email', 'whoiswho' ),
					'required' => true,
					'value'    => wiw_form_value( 'email' ),
					'error'    => wiw_form_error( 'email' ),
				)
			);
			get_template_part(
				'template-parts/forms/consent-tick',
				null,
				array(
					'label'   => __( 'I agree to receive the newsletter by email, as set out in the', 'whoiswho' ),
					'purpose' => 'newsletter',
				)
			);
			get_template_part( 'template-parts/forms/bot-check', null, array( 'form' => 'newsletter' ) );
			get_template_part( 'template-parts/forms/privacy-notice' );
			?>
			<button class="btn" type="submit"><?php esc_html_e( 'Subscribe', 'whoiswho' ); ?></button>
		</form>
	</div>
</section>
<?php
get_footer();
