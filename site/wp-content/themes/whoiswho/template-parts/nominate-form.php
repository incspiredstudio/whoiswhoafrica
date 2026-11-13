<?php
/**
 * The six-step nomination form (REQ-030, REQ-196, REQ-233, REQ-122): your details, verify email, nominee, category and evidence,
 * conflict declaration, review and submit. The markup is plain and complete; assets/js/nominate.js runs the steps, because the
 * nominator must verify an email link before the nomination is accepted and the draft token that ties the two together is
 * returned by the plugin as JSON. Without script the wizard stays hidden and a notice offers the contact page. The plugin answers
 * POST /nominate/start/ (steps 1 and 2) and POST /nominate/ (steps 3 to 6); it validates, scans the free text for personal
 * details, warns about duplicates and rate-limits. Nothing here reads the nominations table: categories come from the categories
 * service through wiw_awards_categories().
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Forms\FormToken;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_tokens = class_exists( FormToken::class ) ? FormToken::for_site() : null;
$wiw_groups = function_exists( 'wiw_awards_categories' ) ? wiw_awards_categories() : array();
$wiw_cats   = array();
foreach ( $wiw_groups as $wiw_group ) {
	foreach ( (array) $wiw_group['categories'] as $wiw_cat ) {
		$wiw_cats[ (string) (int) $wiw_cat['id'] ] = (string) $wiw_cat['title'];
	}
}
$wiw_steps = array(
	__( 'Your details', 'whoiswho' ),
	__( 'Verify email', 'whoiswho' ),
	__( 'Nominee', 'whoiswho' ),
	__( 'Category and evidence', 'whoiswho' ),
	__( 'Conflict declaration', 'whoiswho' ),
	__( 'Review and submit', 'whoiswho' ),
);
$wiw_words = array(
	'required'      => __( 'This is needed.', 'whoiswho' ),
	'invalid'       => __( 'Please check this.', 'whoiswho' ),
	'too_short'     => __( 'This is too short. Please say a little more.', 'whoiswho' ),
	'too_long'      => __( 'This is too long.', 'whoiswho' ),
	'invalid_email' => __( 'Please check the email address.', 'whoiswho' ),
	'invalid_name'  => __( 'Please check the name.', 'whoiswho' ),
	'fix'           => __( 'Please check the highlighted fields.', 'whoiswho' ),
	'network'       => __( 'We could not reach the site. Please check your connection and try again.', 'whoiswho' ),
	'closed'        => __( 'Nominations are closed.', 'whoiswho' ),
	'expired'       => __( 'This form has timed out. Please reload the page and try again.', 'whoiswho' ),
	'draft'         => __( 'This nomination has expired. Please start again.', 'whoiswho' ),
	'unverified'    => __( 'We have not seen your email confirmed yet. Please open the link we sent you, then continue.', 'whoiswho' ),
	'sent'          => __( 'We have sent a link to your email. Open it, then come back to this page and continue.', 'whoiswho' ),
	'person'        => __( 'A person', 'whoiswho' ),
	'organisation'  => __( 'An organisation', 'whoiswho' ),
	'none'          => __( 'None', 'whoiswho' ),
	'again'         => __( 'Continue anyway', 'whoiswho' ),
);
?>
<noscript>
	<p class="warnbox"><?php esc_html_e( 'The nomination form needs JavaScript, because we confirm your email before the nomination is sent. Please turn it on, or use the contact page and we will help.', 'whoiswho' ); ?> <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact us', 'whoiswho' ); ?></a></p>
</noscript>
<div class="nm-wizard" data-nominate hidden
	data-start-url="<?php echo esc_url( home_url( '/nominate/start/' ) ); ?>"
	data-submit-url="<?php echo esc_url( home_url( '/nominate/' ) ); ?>"
	data-done-url="<?php echo esc_url( home_url( '/done/' ) ); ?>"
	data-token-start="<?php echo esc_attr( null !== $wiw_tokens ? $wiw_tokens->issue( 'nomination_start' ) : '' ); ?>"
	data-token-submit="<?php echo esc_attr( null !== $wiw_tokens ? $wiw_tokens->issue( 'nomination' ) : '' ); ?>"
	data-words="<?php echo esc_attr( (string) wp_json_encode( $wiw_words ) ); ?>"
	data-categories="<?php echo esc_attr( (string) wp_json_encode( $wiw_cats ) ); ?>">
	<ol class="nm-progress" aria-label="<?php esc_attr_e( 'Nomination steps', 'whoiswho' ); ?>">
		<?php foreach ( $wiw_steps as $wiw_i => $wiw_label ) : ?>
			<li data-progress="<?php echo esc_attr( (string) ( $wiw_i + 1 ) ); ?>"><span class="nm-progress-n"><?php echo esc_html( (string) ( $wiw_i + 1 ) ); ?></span> <span class="nm-progress-t"><?php echo esc_html( $wiw_label ); ?></span></li>
		<?php endforeach; ?>
	</ol>
	<div class="nm-alert" data-alert tabindex="-1" role="alert" hidden></div>
	<div class="nm-limit errbox" data-limit role="alert" hidden>
		<p><strong><?php esc_html_e( 'Too many nominations from this device.', 'whoiswho' ); ?></strong> <?php esc_html_e( 'Please try again later.', 'whoiswho' ); ?></p>
	</div>
	<form class="form nm-form" method="post" action="<?php echo esc_url( home_url( '/nominate/' ) ); ?>" novalidate data-form>
		<div class="bot-check" aria-hidden="true">
			<label for="wiw_website"><?php esc_html_e( 'Leave this field empty', 'whoiswho' ); ?></label>
			<input type="text" id="wiw_website" name="wiw_website" value="" tabindex="-1" autocomplete="off">
		</div>

		<section class="nm-step" data-step="1" aria-labelledby="nm-h1" hidden>
			<h2 id="nm-h1" tabindex="-1"><?php esc_html_e( 'Your details', 'whoiswho' ); ?></h2>
			<p class="small muted"><?php esc_html_e( 'We use these only to confirm your email and to reach you about this nomination. We never show them to the nominee or the public.', 'whoiswho' ); ?></p>
			<?php
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'nominator_name',
					'label'    => __( 'Your name', 'whoiswho' ),
					'required' => true,
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'nominator_email',
					'type'     => 'email',
					'label'    => __( 'Your email', 'whoiswho' ),
					'required' => true,
				)
			);
			get_template_part( 'template-parts/forms/consent-tick', null, array( 'name' => 'consent' ) );
			?>
			<div class="row"><button class="btn" type="button" data-next><?php esc_html_e( 'Send me a verification link', 'whoiswho' ); ?></button></div>
		</section>

		<section class="nm-step" data-step="2" aria-labelledby="nm-h2" hidden>
			<h2 id="nm-h2" tabindex="-1"><?php esc_html_e( 'Verify your email', 'whoiswho' ); ?></h2>
			<p data-sent><?php esc_html_e( 'We have sent a link to your email. Open it, then come back to this page and continue. The link works once and then expires.', 'whoiswho' ); ?></p>
			<div class="row">
				<button class="btn alt" type="button" data-back><?php esc_html_e( 'Change my details', 'whoiswho' ); ?></button>
				<button class="btn alt" type="button" data-resend><?php esc_html_e( 'Send a new link', 'whoiswho' ); ?></button>
				<button class="btn" type="button" data-next><?php esc_html_e( 'I have verified my email', 'whoiswho' ); ?></button>
			</div>
		</section>

		<section class="nm-step" data-step="3" aria-labelledby="nm-h3" hidden>
			<h2 id="nm-h3" tabindex="-1"><?php esc_html_e( 'Who are you nominating?', 'whoiswho' ); ?></h2>
			<?php
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'nominee_type',
					'type'     => 'select',
					'label'    => __( 'The nominee is', 'whoiswho' ),
					'required' => true,
					'options'  => array(
						'person'       => __( 'A person', 'whoiswho' ),
						'organisation' => __( 'An organisation', 'whoiswho' ),
					),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'nominee_name',
					'label'    => __( 'Nominee name', 'whoiswho' ),
					'required' => true,
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'  => 'nominee_email',
					'type'  => 'email',
					'label' => __( 'Nominee email (optional)', 'whoiswho' ),
					'hint'  => __( 'Used only after screening, to invite them to respond.', 'whoiswho' ),
				)
			);
			?>
			<div class="row"><button class="btn alt" type="button" data-back><?php esc_html_e( 'Back', 'whoiswho' ); ?></button><button class="btn" type="button" data-next><?php esc_html_e( 'Continue', 'whoiswho' ); ?></button></div>
		</section>

		<section class="nm-step" data-step="4" aria-labelledby="nm-h4" hidden>
			<h2 id="nm-h4" tabindex="-1"><?php esc_html_e( 'Category and evidence', 'whoiswho' ); ?></h2>
			<div class="field">
				<label for="f-category_id"><?php esc_html_e( 'Category', 'whoiswho' ); ?> <span class="visually-hidden"><?php esc_html_e( '(required)', 'whoiswho' ); ?></span></label>
				<select id="f-category_id" name="category_id" required aria-required="true">
					<option value=""><?php esc_html_e( 'Choose one', 'whoiswho' ); ?></option>
					<?php foreach ( $wiw_groups as $wiw_group ) : ?>
						<optgroup label="<?php echo esc_attr( (string) $wiw_group['label'] ); ?>">
							<?php foreach ( (array) $wiw_group['categories'] as $wiw_cat ) : ?>
								<option value="<?php echo esc_attr( (string) (int) $wiw_cat['id'] ); ?>"><?php echo esc_html( (string) $wiw_cat['title'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
			</div>
			<?php
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'evidence',
					'type'     => 'textarea',
					'label'    => __( 'Why this nominee', 'whoiswho' ),
					'required' => true,
					'hint'     => __( 'Two or three sentences: what they did, when, and the result. Please leave out phone numbers, email addresses, home addresses and sensitive personal details. We remove them.', 'whoiswho' ),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'  => 'evidence_link',
					'type'  => 'url',
					'label' => __( 'A link that checks it (optional)', 'whoiswho' ),
					'hint'  => __( 'A public report, award or news item.', 'whoiswho' ),
				)
			);
			?>
			<div class="row"><button class="btn alt" type="button" data-back><?php esc_html_e( 'Back', 'whoiswho' ); ?></button><button class="btn" type="button" data-next><?php esc_html_e( 'Continue', 'whoiswho' ); ?></button></div>
		</section>

		<section class="nm-step" data-step="5" aria-labelledby="nm-h5" hidden>
			<h2 id="nm-h5" tabindex="-1"><?php esc_html_e( 'Conflict declaration', 'whoiswho' ); ?></h2>
			<p class="small muted"><?php esc_html_e( 'Judges see only the evidence, not who sent it. We ask so we can handle every nomination fairly.', 'whoiswho' ); ?></p>
			<?php
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'relationship',
					'type'     => 'select',
					'label'    => __( 'Do you have a link to the nominee?', 'whoiswho' ),
					'required' => true,
					'options'  => array(
						'none' => __( 'No link: I am not related to them and do not work for them or with them', 'whoiswho' ),
						'yes'  => __( 'Yes: I am the nominee, I work for them or with them, or we are related', 'whoiswho' ),
					),
				)
			);
			?>
			<div data-relationship-note hidden>
				<?php
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'     => 'relationship_note',
						'type'     => 'textarea',
						'label'    => __( 'Tell us about the link', 'whoiswho' ),
						'required' => true,
					)
				);
				?>
			</div>
			<div class="row"><button class="btn alt" type="button" data-back><?php esc_html_e( 'Back', 'whoiswho' ); ?></button><button class="btn" type="button" data-next><?php esc_html_e( 'Continue', 'whoiswho' ); ?></button></div>
		</section>

		<section class="nm-step" data-step="6" aria-labelledby="nm-h6" hidden>
			<h2 id="nm-h6" tabindex="-1"><?php esc_html_e( 'Review and submit', 'whoiswho' ); ?></h2>
			<dl class="nm-review" data-review></dl>
			<div class="warnbox" data-duplicate role="alert" hidden>
				<p><strong><?php esc_html_e( 'This nominee may already be nominated.', 'whoiswho' ); ?></strong> <?php esc_html_e( 'You can still send yours: our team merges duplicates. We never show who nominated them.', 'whoiswho' ); ?></p>
			</div>
			<?php get_template_part( 'template-parts/forms/privacy-notice' ); ?>
			<div class="row"><button class="btn alt" type="button" data-back><?php esc_html_e( 'Back', 'whoiswho' ); ?></button><button class="btn" type="submit" data-submit><?php esc_html_e( 'Submit nomination', 'whoiswho' ); ?></button></div>
		</section>
	</form>
</div>
