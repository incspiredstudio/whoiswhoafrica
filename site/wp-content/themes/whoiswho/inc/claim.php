<?php
/**
 * The claim page at /claim/{slug} (REQ-251, REQ-252, REQ-156): helpers that find the profile through the PublicProfile read model
 * and decide which of the three pages to show (the form, Already claimed, or the review-first message). The route and the form
 * helpers are in inc/forms.php; the plugin answers the POST. A slug that is not a public profile gives a 404.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! function_exists( 'wiw_claim_profile' ) ) {
	/**
	 * The public profile the claim page is about, or null when the slug is not a public profile.
	 */
	function wiw_claim_profile(): ?PublicProfile {
		$slug = (string) get_query_var( WIW_CLAIM_SLUG_VAR, '' );
		if ( '' === $slug || ! class_exists( PublicProfile::class ) ) {
			return null;
		}
		return PublicProfile::find( $slug );
	}
}

if ( ! function_exists( 'wiw_claim_is_servable' ) ) {
	/**
	 * Whether the claim page can be shown: the slug names a public profile. Otherwise the request stays a 404.
	 */
	function wiw_claim_is_servable(): bool {
		return null !== wiw_claim_profile();
	}
}

if ( ! function_exists( 'wiw_claim_state' ) ) {
	/**
	 * Which page the visitor gets: "form", "claimed" or "review_first".
	 *
	 * @param PublicProfile $profile Profile.
	 */
	function wiw_claim_state( PublicProfile $profile ): string {
		if ( $profile->claimed ) {
			return 'claimed';
		}
		return $profile->claimable ? 'form' : 'review_first';
	}
}

add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		if ( 'claim' !== wiw_form_current() ) {
			return $title;
		}
		$profile = wiw_claim_profile();
		return null === $profile ? $title : sprintf( /* translators: %s: profile name */ __( 'Claim %s', 'whoiswho' ), $profile->name ) . ' | ' . wiw_site_name();
	},
	11
);
