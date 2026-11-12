<?php
/**
 * Staff preview banner (REQ-208, REQ-209): shown only when the read model hands over a profile that is not public, with its own
 * note of what visitors get. The theme decides nothing: no note, no banner. Args: profile (PublicProfile).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = isset( $args['profile'] ) ? $args['profile'] : null;
if ( ! $wiw_profile instanceof PublicProfile || null === $wiw_profile->visibility_note || '' === $wiw_profile->visibility_note ) {
	return;
}
get_template_part(
	'template-parts/notice',
	null,
	array(
		'kind' => 'preview',
		/* translators: %s: what visitors get for this profile, written by the plugin. */
		'text' => sprintf( __( 'Staff preview. %s', 'whoiswho' ), $wiw_profile->visibility_note ),
	)
);
