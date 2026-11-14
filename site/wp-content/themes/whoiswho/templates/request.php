<?php
/**
 * Correction, removal and report pages at /correct/{slug}, /remove/{slug} and /report/{slug} (REQ-157, REQ-156, REQ-122, REQ-090).
 * One band, the response promises beside the form, and the form. No account or payment. The plugin handles the POST; when it
 * refuses one the message and the field errors show here and there is no success state. Prototype V39, V40 and V44.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = wiw_request_profile();
$wiw_kind    = wiw_request_kind();
if ( null === $wiw_profile ) {
	// Should not be reached: the router answers 404 first.
	status_header( 404 );
	get_header();
	get_template_part( 'template-parts/empty-state', null, array( 'title' => __( 'We could not find that profile', 'whoiswho' ) ) );
	get_footer();
	return;
}

$wiw_copy = array(
	'correct' => array(
		'title'    => __( 'Ask for a correction', 'whoiswho' ),
		'lede'     => __( 'No account or payment. A member of staff reads every request and edits the profile directly, and every edit is logged.', 'whoiswho' ),
		'promises' => array(
			__( 'One business day: we acknowledge your request.', 'whoiswho' ),
			__( 'Seven days: we resolve it.', 'whoiswho' ),
		),
		'note'     => __( 'Use this form to fix a fact, add a right-of-reply note, choose which contact details show, switch enquiries on or off, or ask for the view count and the sources used.', 'whoiswho' ),
	),
	'remove'  => array(
		'title'    => __( 'Ask us to remove a profile', 'whoiswho' ),
		'lede'     => __( 'No account or payment needed. If you are the person or organisation named, this is your right.', 'whoiswho' ),
		'promises' => array(
			__( 'One business day: we acknowledge your request.', 'whoiswho' ),
			__( 'Two business days: we hide the profile after a valid request from the subject.', 'whoiswho' ),
		),
		'note'     => __( 'Once a valid removal is done, the page is gone and leaves search and the sitemap. We keep the name on a list so it is not added again.', 'whoiswho' ),
	),
	'report'  => array(
		'title'    => __( 'Report this profile', 'whoiswho' ),
		'lede'     => __( 'Tell us what is wrong. Anyone can report a profile, without an account. A member of staff reads every report.', 'whoiswho' ),
		'promises' => array(
			__( 'One business day: we acknowledge your report.', 'whoiswho' ),
			__( 'A person decides what to do and records why.', 'whoiswho' ),
		),
		'note'     => __( 'To have a profile about you taken down, use the removal request instead.', 'whoiswho' ),
	),
);
$wiw_c    = $wiw_copy[ $wiw_kind ];

get_header();
wiw_page_band(
	array(
		'eyebrow' => $wiw_profile->name,
		'title'   => (string) $wiw_c['title'],
		'tone'    => 'dark',
		'lede'    => (string) $wiw_c['lede'],
	)
);
?>
<section class="section section--tight">
	<div class="wrap form-grid">
		<aside class="form-aside" aria-labelledby="req-next-h">
			<h2 id="req-next-h"><?php esc_html_e( 'What happens next', 'whoiswho' ); ?></h2>
			<ul class="ticks">
				<?php foreach ( (array) $wiw_c['promises'] as $wiw_line ) : ?>
					<li><?php echo wiw_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><span><?php echo esc_html( (string) $wiw_line ); ?></span></li>
				<?php endforeach; ?>
			</ul>
			<p class="meta"><?php echo esc_html( (string) $wiw_c['note'] ); ?></p>
			<p class="meta"><a href="<?php echo esc_url( home_url( '/complaints/' ) ); ?>"><?php esc_html_e( 'Complaints and takedown', 'whoiswho' ); ?></a> · <a href="<?php echo esc_url( $wiw_profile->url ); ?>"><?php esc_html_e( 'Back to the profile', 'whoiswho' ); ?></a></p>
		</aside>
		<div>
			<h2 class="sr"><?php echo esc_html( (string) $wiw_c['title'] ); ?></h2>
			<?php
			get_template_part(
				'template-parts/forms/request-form',
				null,
				array(
					'kind'    => $wiw_kind,
					'profile' => $wiw_profile,
					'prefix'  => 'req',
				)
			);
			?>
		</div>
	</div>
</section>
<?php
get_footer();
