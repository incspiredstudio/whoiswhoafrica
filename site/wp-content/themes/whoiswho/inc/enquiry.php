<?php
/**
 * The profile enquiry page at /enquire/{slug} and its panel in the profile's right rail (REQ-018, REQ-081, REQ-122). The plugin
 * answers the POST at the same address (it validates, protects, stores and relays); when it refuses, this page shows the message
 * and the field errors. The owner's email and phone are never read here: the page reads the profile only through PublicProfile.
 * A slug that is not a public profile is a 404.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

const WIW_ENQUIRY_SLUG_VAR = 'wiw_enquiry_slug';
const WIW_ENQUIRY_RULES_OP = 'wiw_theme_enquiry_rules';

if ( ! function_exists( 'wiw_enquiry_profile' ) ) {
	/**
	 * The public profile the enquiry page is about, or null when the slug is not a public profile.
	 */
	function wiw_enquiry_profile(): ?PublicProfile {
		$slug = (string) get_query_var( WIW_ENQUIRY_SLUG_VAR, '' );
		if ( '' === $slug || ! class_exists( PublicProfile::class ) ) {
			return null;
		}
		return PublicProfile::find( $slug );
	}
}

if ( ! function_exists( 'wiw_enquiry_is_servable' ) ) {
	/**
	 * Whether the request is an enquiry page for a public profile.
	 */
	function wiw_enquiry_is_servable(): bool {
		return null !== wiw_enquiry_profile();
	}
}

if ( ! function_exists( 'wiw_is_enquiry_request' ) ) {
	/**
	 * Whether the request is for an enquiry address, whatever the slug.
	 */
	function wiw_is_enquiry_request(): bool {
		return '' !== (string) get_query_var( WIW_ENQUIRY_SLUG_VAR, '' );
	}
}

add_filter(
	'query_vars',
	static function ( array $vars ): array {
		return array_merge( $vars, array( WIW_ENQUIRY_SLUG_VAR ) );
	}
);

add_action(
	'init',
	static function (): void {
		add_rewrite_rule( '^enquire/([A-Za-z0-9_-]+)/?$', 'index.php?' . WIW_ENQUIRY_SLUG_VAR . '=$matches[1]', 'top' );
		if ( '1' !== get_option( WIW_ENQUIRY_RULES_OP ) ) {
			flush_rewrite_rules( false );
			update_option( WIW_ENQUIRY_RULES_OP, '1', false );
		}
	},
	7
);

add_filter(
	'template_include',
	static function ( string $template ): string {
		if ( ! wiw_is_enquiry_request() || ! wiw_enquiry_is_servable() ) {
			return $template;
		}
		$found = locate_template( 'templates/enquiry.php' );
		return '' !== $found ? $found : $template;
	},
	11
);

add_action(
	'wp',
	static function (): void {
		if ( ! wiw_is_enquiry_request() ) {
			return;
		}
		global $wp_query;
		if ( wiw_enquiry_is_servable() ) {
			$wp_query->is_404 = false;
			// The plugin sets 4xx for a refused submission later, on template_redirect.
			status_header( 200 );
			return;
		}
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
);

add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		$profile = wiw_is_enquiry_request() ? wiw_enquiry_profile() : null;
		if ( null === $profile ) {
			return $title;
		}
		/* translators: %s: profile name */
		return sprintf( __( 'Enquire: %s', 'whoiswho' ), $profile->name ) . ' | ' . wiw_site_name();
	},
	11
);

add_action(
	'wp_head',
	static function (): void {
		if ( wiw_is_enquiry_request() ) {
			echo '<meta name="robots" content="noindex, nofollow">' . "\n";
		}
	},
	3
);

add_action(
	'wiw_profile_rail',
	static function ( $profile ): void {
		if ( $profile instanceof PublicProfile ) {
			get_template_part( 'template-parts/enquiry-panel', null, array( 'profile' => $profile ) );
		}
	},
	20
);
