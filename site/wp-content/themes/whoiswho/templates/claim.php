<?php
/**
 * Claim page at /claim/{slug} (REQ-251, REQ-252, REQ-058, REQ-081, REQ-156): the claim form, or Already claimed, or the
 * review-first message for a profile that has not been reviewed. The profile comes from the PublicProfile read model, so a slug
 * that is not public never reaches this template (it is a 404). The plugin handles the POST; when it refuses one, the message and
 * the field errors show here and the form stays. No script is needed to send it. Claimed is never shown as a badge anywhere.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = wiw_claim_profile();
if ( null === $wiw_profile ) {
	// Should not be reached: the router answers 404 first.
	status_header( 404 );
	get_header();
	get_template_part( 'template-parts/empty-state', null, array( 'title' => __( 'We could not find that profile', 'whoiswho' ) ) );
	get_footer();
	return;
}

$wiw_state   = wiw_claim_state( $wiw_profile );
$wiw_result  = wiw_form_result();
$wiw_refused = isset( $wiw_result['status'] ) ? (string) $wiw_result['status'] : '';
if ( 'already_claimed' === $wiw_refused ) {
	$wiw_state = 'claimed';
}
$wiw_correct = home_url( '/correct/' . $wiw_profile->slug . '/' );
$wiw_back    = array(
	'label' => $wiw_profile->name,
	'url'   => $wiw_profile->url,
);

get_header();

if ( 'form' !== $wiw_state ) {
	$wiw_claimed = 'claimed' === $wiw_state;
	wiw_page_band(
		array(
			'eyebrow' => $wiw_back['label'],
			'title'   => $wiw_claimed ? __( 'Already claimed', 'whoiswho' ) : __( 'Not yet open for claims', 'whoiswho' ),
			'lede'    => $wiw_claimed
				? __( 'A claim for this profile has already been approved. To change something, ask for a correction.', 'whoiswho' )
				: __( 'This profile has not been reviewed yet. Once it has, it can be claimed. To fix something now, ask for a correction.', 'whoiswho' ),
		)
	);
	?>
	<section class="section section--tight">
		<div class="wrap wrap--text">
			<?php wiw_form_banner(); ?>
			<div class="row">
				<a class="btn" href="<?php echo esc_url( $wiw_correct ); ?>"><?php esc_html_e( 'Suggest a correction', 'whoiswho' ); ?></a>
				<a class="btn alt" href="<?php echo esc_url( $wiw_back['url'] ); ?>"><?php esc_html_e( 'Back to the profile', 'whoiswho' ); ?></a>
			</div>
		</div>
	</section>
	<?php
	get_footer();
	return;
}//end if

wiw_page_band(
	array(
		'eyebrow' => $wiw_back['label'],
		'title'   => __( 'Claim this profile', 'whoiswho' ),
		'lede'    => __( 'Tell us who you are and how we can check it. A member of staff reviews every claim before anything changes. No account is needed to ask.', 'whoiswho' ),
	)
);
$wiw_next = array(
	__( 'One day: we acknowledge your claim.', 'whoiswho' ),
	__( 'Five days: we decide, after checking your evidence.', 'whoiswho' ),
);
?>
<section class="section section--tight">
	<div class="wrap form-grid">
		<aside class="form-aside" aria-labelledby="claim-next-h">
			<h2 id="claim-next-h"><?php esc_html_e( 'What happens next', 'whoiswho' ); ?></h2>
			<ul class="ticks">
				<?php foreach ( $wiw_next as $wiw_line ) : ?>
					<li><?php echo wiw_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><span><?php echo esc_html( $wiw_line ); ?></span></li>
				<?php endforeach; ?>
			</ul>
			<p class="meta"><?php esc_html_e( 'If we approve it, a named person on your side can ask us to keep the profile up to date, and staff check every change before it appears. A claim never changes how the profile ranks, and it cannot be bought.', 'whoiswho' ); ?></p>
		</aside>
		<form class="form" method="post" action="<?php echo esc_url( home_url( '/claim/' . $wiw_profile->slug . '/' ) ); ?>" novalidate>
			<h2 class="sr"><?php esc_html_e( 'Send your claim', 'whoiswho' ); ?></h2>
			<?php wiw_form_banner(); ?>
			<?php
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'relationship',
					'type'     => 'select',
					'label'    => __( 'Who are you?', 'whoiswho' ),
					'required' => true,
					'options'  => array(
						'self'           => __( 'The person named on this profile', 'whoiswho' ),
						'representative' => __( 'An authorised representative of the organisation', 'whoiswho' ),
					),
					'value'    => wiw_form_value( 'relationship' ),
					'error'    => wiw_form_error( 'relationship' ),
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
					'name'  => 'role',
					'label' => __( 'Your role', 'whoiswho' ),
					'hint'  => __( 'For example Chief Executive or Communications Manager.', 'whoiswho' ),
					'value' => wiw_form_value( 'role' ),
					'error' => wiw_form_error( 'role' ),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'work_email',
					'type'     => 'email',
					'label'    => __( 'Work email', 'whoiswho' ),
					'required' => true,
					'hint'     => __( 'Use an address at the organisation\'s own domain if you can. It is never public.', 'whoiswho' ),
					'value'    => wiw_form_value( 'work_email' ),
					'error'    => wiw_form_error( 'work_email' ),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'  => 'phone',
					'type'  => 'tel',
					'label' => __( 'Phone (optional)', 'whoiswho' ),
					'value' => wiw_form_value( 'phone' ),
					'error' => wiw_form_error( 'phone' ),
				)
			);
			get_template_part(
				'template-parts/forms/field',
				null,
				array(
					'name'     => 'evidence',
					'type'     => 'textarea',
					'label'    => __( 'How can we check this?', 'whoiswho' ),
					'required' => true,
					'hint'     => __( 'A page on the official website that names you, a registration number, or a letter on letterhead.', 'whoiswho' ),
					'value'    => wiw_form_value( 'evidence' ),
					'error'    => wiw_form_error( 'evidence' ),
				)
			);
			get_template_part(
				'template-parts/forms/consent-tick',
				null,
				array(
					'label'   => __( 'I agree that you may contact me about this claim, as set out in the', 'whoiswho' ),
					'purpose' => 'claim',
					'error'   => wiw_form_error( 'consent' ),
				)
			);
			get_template_part( 'template-parts/forms/bot-check', null, array( 'form' => 'claim' ) );
			get_template_part( 'template-parts/forms/privacy-notice' );
			?>
			<button class="btn" type="submit"><?php esc_html_e( 'Send claim', 'whoiswho' ); ?></button>
		</form>
	</div>
</section>
<?php
get_footer();
