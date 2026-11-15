<?php
/**
 * The Profile owner account pages at /account/ (REQ-274, REQ-261). The plugin owns the routes, checks every request and decides
 * the state (sign-in, dashboard, edit, details, lost password, reset); the theme only draws it through the wiw_account_page filter
 * inside the site header and footer, with the shared form styles. The pages are never indexed, and the footer link to them is
 * the only way in (there is none in the header).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_account_page' ) ) {
	/**
	 * Render an account page inside the site header and footer.
	 *
	 * @param string               $html  The plugin's bare default markup (ignored).
	 * @param array<string, mixed> $state State from the plugin: view, status, errors, notice and data.
	 */
	function wiw_account_page( string $html, array $state = array() ): string {
		unset( $html );
		ob_start();
		get_header();
		get_template_part(
			'templates/account',
			null,
			array(
				'view'   => isset( $state['view'] ) ? (string) $state['view'] : 'signin',
				'errors' => isset( $state['errors'] ) && is_array( $state['errors'] ) ? array_map( 'strval', $state['errors'] ) : array(),
				'notice' => isset( $state['notice'] ) ? (string) $state['notice'] : '',
				'data'   => isset( $state['data'] ) && is_array( $state['data'] ) ? $state['data'] : array(),
			)
		);
		get_footer();
		return (string) ob_get_clean();
	}
}//end if
add_filter( 'wiw_account_page', 'wiw_account_page', 10, 2 );

if ( ! function_exists( 'wiw_account_link' ) ) {
	/**
	 * The footer's account link: "Profile owner sign in" when signed out, "My account" for a signed-in Profile owner, and nothing
	 * for staff (they use wp-admin).
	 *
	 * @return array{label: string, url: string}|null
	 */
	function wiw_account_link(): ?array {
		$owner_class = '\WhoIsWho\Core\Trust\Account\OwnerRole';
		if ( ! class_exists( $owner_class ) ) {
			return null;
		}
		$user = get_current_user_id();
		if ( $user < 1 ) {
			return array(
				'label' => __( 'Profile owner sign in', 'whoiswho' ),
				'url'   => home_url( '/account/' ),
			);
		}
		return $owner_class::is_owner( $user ) ? array(
			'label' => __( 'My account', 'whoiswho' ),
			'url'   => home_url( '/account/' ),
		) : null;
	}
}//end if

add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		$view = (string) get_query_var( 'wiw_account' );
		if ( '' === $view ) {
			return $title;
		}
		$titles = array(
			'edit'    => __( 'Edit your profile', 'whoiswho' ),
			'details' => __( 'Account details', 'whoiswho' ),
			'lost'    => __( 'Reset your password', 'whoiswho' ),
		);
		return ( $titles[ $view ] ?? __( 'Your account', 'whoiswho' ) ) . ' | ' . wiw_site_name();
	},
	11
);
