<?php
/**
 * The correction, removal and report pages at /correct/{slug}, /remove/{slug} and /report/{slug} (REQ-157, REQ-156, REQ-122,
 * REQ-090, REQ-233). The plugin answers the POST at the same address (it validates, protects, stores and sends the confirmation
 * link); when it refuses, the same page shows the message and the field errors and no success state. The profile is read only
 * through PublicProfile, so a slug that is not public is a 404. Every request page is noindex.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

const WIW_REQUEST_KIND_VAR = 'wiw_request_kind';
const WIW_REQUEST_SLUG_VAR = 'wiw_request_slug';
const WIW_REQUEST_RULES_OP = 'wiw_theme_request_rules';

if ( ! function_exists( 'wiw_request_kinds' ) ) {
	/**
	 * The request kinds the pages serve.
	 *
	 * @return string[]
	 */
	function wiw_request_kinds(): array {
		return array( 'correct', 'remove', 'report' );
	}
}

if ( ! function_exists( 'wiw_request_kind' ) ) {
	/**
	 * The kind of the current request page (correct, remove or report), or an empty string.
	 */
	function wiw_request_kind(): string {
		$kind = (string) get_query_var( WIW_REQUEST_KIND_VAR, '' );
		return in_array( $kind, wiw_request_kinds(), true ) ? $kind : '';
	}
}

if ( ! function_exists( 'wiw_request_profile' ) ) {
	/**
	 * The public profile the request page is about, or null when the slug is not a public profile.
	 */
	function wiw_request_profile(): ?PublicProfile {
		$slug = (string) get_query_var( WIW_REQUEST_SLUG_VAR, '' );
		if ( '' === $slug || '' === wiw_request_kind() || ! class_exists( PublicProfile::class ) ) {
			return null;
		}
		return PublicProfile::find( $slug );
	}
}

if ( ! function_exists( 'wiw_request_choices' ) ) {
	/**
	 * The fixed lists the forms offer, keyed by the values the plugin validators accept.
	 *
	 * @param string $set relationship, need, field or reason.
	 * @return array<string, string>
	 */
	function wiw_request_choices( string $set ): array {
		$lists = array(
			'relationship' => array(
				'person'         => __( 'The person named on this profile', 'whoiswho' ),
				'representative' => __( 'An authorised representative of the organisation', 'whoiswho' ),
				'someone_else'   => __( 'Someone else (a visitor)', 'whoiswho' ),
			),
			'need'         => array(
				'correct_fact'       => __( 'Correct a fact', 'whoiswho' ),
				'right_of_reply'     => __( 'Add a right-of-reply note', 'whoiswho' ),
				'contact_details'    => __( 'Change which contact details are public', 'whoiswho' ),
				'enquiries_switch'   => __( 'Switch enquiries on or off', 'whoiswho' ),
				'view_count_sources' => __( 'Send me the view count or the sources used', 'whoiswho' ),
			),
			'reason'       => array(
				'inaccurate'            => __( 'Inaccurate information', 'whoiswho' ),
				'defamatory_or_harmful' => __( 'Defamatory or harmful', 'whoiswho' ),
				'privacy'               => __( 'Privacy concern', 'whoiswho' ),
				'copyright'             => __( 'Copyright', 'whoiswho' ),
				'other'                 => __( 'Other', 'whoiswho' ),
			),
		);
		return $lists[ $set ] ?? array();
	}
}//end if

add_filter(
	'query_vars',
	static function ( array $vars ): array {
		return array_merge( $vars, array( WIW_REQUEST_KIND_VAR, WIW_REQUEST_SLUG_VAR ) );
	}
);

add_action(
	'init',
	static function (): void {
		add_rewrite_rule( '^(correct|remove|report)/([A-Za-z0-9_-]+)/?$', 'index.php?' . WIW_REQUEST_KIND_VAR . '=$matches[1]&' . WIW_REQUEST_SLUG_VAR . '=$matches[2]', 'top' );
		if ( '1' !== get_option( WIW_REQUEST_RULES_OP ) ) {
			flush_rewrite_rules( false );
			update_option( WIW_REQUEST_RULES_OP, '1', false );
		}
	},
	7
);

add_filter(
	'template_include',
	static function ( string $template ): string {
		if ( '' === wiw_request_kind() || null === wiw_request_profile() ) {
			return $template;
		}
		$found = locate_template( 'templates/request.php' );
		return '' !== $found ? $found : $template;
	},
	11
);

add_action(
	'wp',
	static function (): void {
		if ( '' === wiw_request_kind() ) {
			return;
		}
		global $wp_query;
		if ( null !== wiw_request_profile() ) {
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
		$kind = wiw_request_kind();
		if ( '' === $kind ) {
			return $title;
		}
		$profile = wiw_request_profile();
		if ( null === $profile ) {
			return __( 'Page not found', 'whoiswho' ) . " \u{2013} " . wiw_site_name();
		}
		$heads = array(
			/* translators: %s: profile name */
			'correct' => __( 'Ask for a correction: %s', 'whoiswho' ),
			/* translators: %s: profile name */
			'remove'  => __( 'Ask us to remove: %s', 'whoiswho' ),
			/* translators: %s: profile name */
			'report'  => __( 'Report: %s', 'whoiswho' ),
		);
		return sprintf( $heads[ $kind ], $profile->name ) . ' | ' . wiw_site_name();
	},
	11
);

add_action(
	'wp_head',
	static function (): void {
		if ( '' !== wiw_request_kind() ) {
			echo '<meta name="robots" content="noindex, nofollow">' . "\n";
		}
	},
	3
);
add_action(
	'wiw_profile_rail',
	static function ( $profile ): void {
		if ( $profile instanceof PublicProfile ) {
			get_template_part( 'template-parts/report-panel', null, array( 'profile' => $profile ) );
		}
	},
	30
);
