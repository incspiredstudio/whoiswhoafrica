<?php
/**
 * The correction, removal or report form for one profile (REQ-157, REQ-156, REQ-122, REQ-090). Posts to the plugin's route at
 * /{kind}/{slug}/; a refusal comes back to the same page with the message and field errors. Args: kind (correct, remove or
 * report), profile (PublicProfile), prefix (an id prefix, so the page form and the dialog form never share ids).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_kind    = isset( $args['kind'] ) ? (string) $args['kind'] : '';
$wiw_profile = isset( $args['profile'] ) && $args['profile'] instanceof PublicProfile ? $args['profile'] : null;
$wiw_prefix  = isset( $args['prefix'] ) ? sanitize_html_class( (string) $args['prefix'] ) : 'req';
if ( null === $wiw_profile || ! in_array( $wiw_kind, wiw_request_kinds(), true ) ) {
	return;
}
$wiw_forms  = array(
	'correct' => 'correction',
	'remove'  => 'removal',
	'report'  => 'report',
);
$wiw_field  = static function ( string $name, array $more ) use ( $wiw_prefix ): void {
	get_template_part(
		'template-parts/forms/field',
		null,
		array_merge(
			array(
				'name'  => $name,
				'id'    => $wiw_prefix . '-' . $name,
				'value' => wiw_form_value( $name ),
				'error' => wiw_form_error( $name ),
			),
			$more
		)
	);
};
$wiw_submit = array(
	'correct' => __( 'Send request', 'whoiswho' ),
	'remove'  => __( 'Request removal', 'whoiswho' ),
	'report'  => __( 'Send report', 'whoiswho' ),
);
?>
<form class="form" method="post" action="<?php echo esc_url( home_url( '/' . $wiw_kind . '/' . $wiw_profile->slug . '/' ) ); ?>" novalidate>
	<?php wiw_form_banner(); ?>
	<?php
	if ( 'correct' === $wiw_kind ) {
		$wiw_field(
			'relationship',
			array(
				'type'     => 'select',
				'label'    => __( 'Who are you?', 'whoiswho' ),
				'required' => true,
				'options'  => wiw_request_choices( 'relationship' ),
			)
		);
		$wiw_field(
			'need',
			array(
				'type'     => 'select',
				'label'    => __( 'What do you need?', 'whoiswho' ),
				'required' => true,
				'options'  => wiw_request_choices( 'need' ),
			)
		);
		$wiw_field(
			'field',
			array(
				'label' => __( 'Which part of the profile?', 'whoiswho' ),
				'hint'  => __( 'For example the title, the biography or the website.', 'whoiswho' ),
			)
		);
		$wiw_field(
			'new_value',
			array(
				'type'     => 'textarea',
				'label'    => __( 'New value or details', 'whoiswho' ),
				'required' => true,
			)
		);
		$wiw_field(
			'evidence',
			array(
				'type'  => 'textarea',
				'label' => __( 'Source or evidence (a link or a description)', 'whoiswho' ),
			)
		);
	} elseif ( 'remove' === $wiw_kind ) {
		$wiw_field(
			'relationship',
			array(
				'type'     => 'select',
				'label'    => __( 'Who are you in relation to this profile?', 'whoiswho' ),
				'required' => true,
				'options'  => wiw_request_choices( 'relationship' ),
			)
		);
		$wiw_field(
			'reason',
			array(
				'type'     => 'textarea',
				'label'    => __( 'Why should it be removed?', 'whoiswho' ),
				'required' => true,
			)
		);
	} else {
		$wiw_field(
			'reason',
			array(
				'type'     => 'select',
				'label'    => __( 'Reason', 'whoiswho' ),
				'required' => true,
				'options'  => wiw_request_choices( 'reason' ),
			)
		);
		$wiw_field(
			'details',
			array(
				'type'     => 'textarea',
				'label'    => __( 'Details', 'whoiswho' ),
				'required' => true,
			)
		);
	}//end if
	if ( 'report' !== $wiw_kind ) {
		$wiw_field(
			'name',
			array(
				'label'    => __( 'Your name', 'whoiswho' ),
				'required' => true,
			)
		);
	}
	$wiw_field(
		'email',
		array(
			'type'     => 'email',
			'label'    => 'report' === $wiw_kind ? __( 'Your email (optional)', 'whoiswho' ) : __( 'Your email', 'whoiswho' ),
			'required' => 'report' !== $wiw_kind,
			'hint'     => 'report' === $wiw_kind
				? __( 'If you give one, we confirm it with a single-use link. It is never public.', 'whoiswho' )
				: __( 'We confirm your email with a single-use link before your request reaches our staff. It is never public.', 'whoiswho' ),
		)
	);
	get_template_part( 'template-parts/forms/bot-check', null, array( 'form' => $wiw_forms[ $wiw_kind ] ) );
	get_template_part( 'template-parts/forms/privacy-notice' );
	?>
	<button class="btn<?php echo 'remove' === $wiw_kind ? ' bad' : ''; ?>" type="submit"><?php echo esc_html( $wiw_submit[ $wiw_kind ] ); ?></button>
</form>
