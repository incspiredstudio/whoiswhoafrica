<?php
/**
 * The customer invitation page at /invite/{token} (REQ-013, REQ-175, REQ-233, REQ-141). The plugin owns the route and decides the
 * state (form, saved, invalid, done, expired or limited); the theme only draws it through the wiw_invitation_page filter inside the
 * site header and footer. Field errors come back as codes and are turned into words here, never echoing what was typed beyond
 * refilling the form. The page is never indexed. The consent step uses the single-use link page (inc/link-page.php).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_invitation_error_text' ) ) {
	/**
	 * The words for the error code of one invitation field, or an empty string when it has none.
	 *
	 * @param array<string, string> $errors Field errors from the plugin.
	 * @param string                $field  Field name.
	 */
	function wiw_invitation_error_text( array $errors, string $field ): string {
		$code = isset( $errors[ $field ] ) ? (string) $errors[ $field ] : '';
		if ( '' === $code ) {
			return '';
		}
		$words = array(
			'required'             => __( 'This is needed.', 'whoiswho' ),
			'too_long'             => __( 'This is too long.', 'whoiswho' ),
			'too_short'            => __( 'This is too short.', 'whoiswho' ),
			'invalid'              => __( 'Please choose one of the options.', 'whoiswho' ),
			'invalid_url'          => __( 'Please enter a full web address that starts with http:// or https://.', 'whoiswho' ),
			'remove_personal_text' => __( 'Please remove personal details such as phone numbers, email addresses, home addresses, ages or sensitive personal information.', 'whoiswho' ),
			'too_big'              => __( 'The picture is too large. Please use a file up to 2 MB.', 'whoiswho' ),
			'wrong_type'           => __( 'The picture must be a JPEG, PNG or WebP file.', 'whoiswho' ),
		);
		return $words[ $code ] ?? __( 'Please check this.', 'whoiswho' );
	}
}//end if

if ( ! function_exists( 'wiw_invitation_page' ) ) {
	/**
	 * Render the invitation page inside the site header and footer.
	 *
	 * @param string               $html  The plugin's bare default markup (ignored).
	 * @param array<string, mixed> $state State from the plugin: state, errors and values.
	 */
	function wiw_invitation_page( string $html, array $state = array() ): string {
		unset( $html );
		ob_start();
		get_header();
		get_template_part(
			'templates/invite',
			null,
			array(
				'state'  => isset( $state['state'] ) ? (string) $state['state'] : 'expired',
				'errors' => isset( $state['errors'] ) && is_array( $state['errors'] ) ? $state['errors'] : array(),
				'values' => isset( $state['values'] ) && is_array( $state['values'] ) ? $state['values'] : array(),
				'token'  => (string) get_query_var( 'wiw_invite' ),
			)
		);
		get_footer();
		return (string) ob_get_clean();
	}
}//end if
add_filter( 'wiw_invitation_page', 'wiw_invitation_page', 10, 2 );

add_action(
	'wp_head',
	static function (): void {
		if ( '' !== (string) get_query_var( 'wiw_invite' ) ) {
			echo '<meta name="robots" content="noindex, nofollow">' . "\n";
		}
	},
	3
);

add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		return '' === (string) get_query_var( 'wiw_invite' ) ? $title : __( 'Your profile', 'whoiswho' ) . ' | ' . wiw_site_name();
	},
	11
);
