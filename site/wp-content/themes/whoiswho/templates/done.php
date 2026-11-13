<?php
/**
 * Done page after a public form (REQ-039, REQ-177, REQ-251): Enquiry received (Get listed), Message sent (Contact) or Claim
 * received, with the reference when it has the expected shape. Not indexed. The address carries only the kind, the form and the
 * reference, never a name or an email address.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_kind = sanitize_key( (string) get_query_var( 'kind', '' ) );
$wiw_from = sanitize_key( (string) get_query_var( 'from', '' ) );
$wiw_ref  = sanitize_text_field( (string) get_query_var( 'ref', '' ) );
$wiw_ref  = 1 === preg_match( '/^[A-Z]{2,6}-[A-Z0-9]{1,8}$/', $wiw_ref ) ? $wiw_ref : '';

if ( 'claim' === $wiw_kind ) {
	$wiw_title = __( 'Claim received', 'whoiswho' );
	$wiw_text  = __( 'Our team will check the details you gave and reply within five days. Nothing on the profile changes until a person has checked.', 'whoiswho' );
} elseif ( 'correction' === $wiw_kind ) {
	$wiw_title = __( 'Correction request received', 'whoiswho' );
	$wiw_text  = __( 'We acknowledge within one business day and resolve corrections within seven days. If we sent you a link to confirm your email, please use it: your request reaches our staff once you do.', 'whoiswho' );
} elseif ( 'removal' === $wiw_kind ) {
	$wiw_title = __( 'Removal request received', 'whoiswho' );
	$wiw_text  = __( 'We acknowledge within one business day. A valid request from the subject hides the profile within two business days. If we sent you a link to confirm your email, please use it.', 'whoiswho' );
} elseif ( 'report' === $wiw_kind ) {
	$wiw_title = __( 'Report received', 'whoiswho' );
	$wiw_text  = __( 'A member of staff will look at it. If you gave an email address, we sent a link to confirm it.', 'whoiswho' );
} elseif ( 'nomination' === $wiw_kind ) {
	$wiw_title = __( 'Nomination submitted', 'whoiswho' );
	$wiw_text  = __( 'It is private until our team checks it and makes it live. Nothing is published without staff approval. Keep your reference.', 'whoiswho' );
} elseif ( 'nomination_start' === $wiw_kind ) {
	$wiw_title = __( 'Check your email to continue', 'whoiswho' );
	$wiw_text  = __( 'We sent you a link to confirm your email. Open it, then go back to the nomination form.', 'whoiswho' );
	$wiw_ref   = '';
} elseif ( 'newsletter' === $wiw_kind ) {
	$wiw_title = __( 'Check your email to finish signing up', 'whoiswho' );
	$wiw_text  = __( 'We sent you a link to confirm. You are subscribed only after you use it. Every email has an unsubscribe link.', 'whoiswho' );
	$wiw_ref   = '';
} elseif ( 'profile_enquiry' === $wiw_from ) {
	$wiw_title = __( 'Enquiry sent', 'whoiswho' );
	$wiw_text  = __( 'Our team reads each enquiry and passes it on or replies by email. The email address and phone number of the person or organisation stay private.', 'whoiswho' );
} elseif ( 'contact' === $wiw_from ) {
	$wiw_title = __( 'Message sent', 'whoiswho' );
	$wiw_text  = __( 'A member of staff will reply. The message is logged.', 'whoiswho' );
} else {
	$wiw_title = __( 'Enquiry received', 'whoiswho' );
	$wiw_text  = __( 'Our team will contact you. Terms are agreed offline; nothing is charged on this site.', 'whoiswho' );
}//end if

get_header();
get_template_part(
	'template-parts/done',
	null,
	array(
		'title'     => $wiw_title,
		'text'      => $wiw_text,
		'reference' => $wiw_ref,
	)
);
get_footer();
