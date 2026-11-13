<?php
/**
 * Public form pages (REQ-039, REQ-177, REQ-081, REQ-058): the routes /contact, /subscribe (Get listed) and /done, the template
 * choice, and the helpers the form templates share. The plugin answers the POST (it validates, protects and stores) and, when a
 * submission is refused, calls wiw_public_form_result so the same page shows the message and the field errors. Nothing here
 * reads a superglobal: the values to refill come from the plugin's FormInput.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Platform\Forms\FormInput;

const WIW_FORM_PAGE_VAR  = 'wiw_form_page';
const WIW_FORM_RULES_OP  = 'wiw_theme_form_rules_2';
const WIW_CLAIM_SLUG_VAR = 'wiw_claim_slug';

if ( ! function_exists( 'wiw_form_pages' ) ) {
	/**
	 * The form pages: path => array( template file, title ).
	 *
	 * @return array<string, array{template: string, title: string}>
	 */
	function wiw_form_pages(): array {
		return array(
			'contact'   => array(
				'template' => 'templates/contact.php',
				'title'    => __( 'Contact', 'whoiswho' ),
			),
			'subscribe' => array(
				'template' => 'templates/get-listed.php',
				'title'    => __( 'Get listed', 'whoiswho' ),
			),
			'claim'     => array(
				'template' => 'templates/claim.php',
				'title'    => __( 'Claim this profile', 'whoiswho' ),
			),
			'done'      => array(
				'template' => 'templates/done.php',
				'title'    => __( 'Received', 'whoiswho' ),
			),
		);
	}
}//end if

if ( ! function_exists( 'wiw_form_current' ) ) {
	/**
	 * The form page of the current request, or an empty string.
	 */
	function wiw_form_current(): string {
		$page = (string) get_query_var( WIW_FORM_PAGE_VAR, '' );
		return isset( wiw_form_pages()[ $page ] ) ? $page : '';
	}
}

if ( ! function_exists( 'wiw_form_template' ) ) {
	/**
	 * Use the form template for a form page.
	 *
	 * @param string $template Template path.
	 */
	function wiw_form_template( string $template ): string {
		$page = wiw_form_current();
		if ( '' === $page || ( 'claim' === $page && ! wiw_claim_is_servable() ) ) {
			return $template;
		}
		$found = locate_template( wiw_form_pages()[ $page ]['template'] );
		return '' !== $found ? $found : $template;
	}
}
add_filter( 'template_include', 'wiw_form_template', 11 );

if ( ! function_exists( 'wiw_form_result' ) ) {
	/**
	 * The refused submission of this request: set it with an array, read it with null. An empty array clears it.
	 *
	 * @param array<string, mixed>|null $set Result from the plugin, or null to read.
	 * @return array<string, mixed>
	 */
	function wiw_form_result( ?array $set = null ): array {
		static $result = array();
		if ( null !== $set ) {
			$result = $set;
		}
		return $result;
	}
}
add_action(
	'wiw_public_form_result',
	static function ( $result ): void {
		wiw_form_result( is_array( $result ) ? $result : array() );
	}
);

if ( ! function_exists( 'wiw_form_error' ) ) {
	/**
	 * The words for the error of one field, or an empty string when it has none.
	 *
	 * @param string $field Field name.
	 */
	function wiw_form_error( string $field ): string {
		$errors = wiw_form_result()['errors'] ?? array();
		$code   = is_array( $errors ) && isset( $errors[ $field ] ) ? (string) $errors[ $field ] : '';
		if ( '' === $code ) {
			return '';
		}
		$words = array(
			'required'      => __( 'This is needed.', 'whoiswho' ),
			'invalid_email' => __( 'Please check the email address.', 'whoiswho' ),
			'invalid_name'  => __( 'Please check the name.', 'whoiswho' ),
			'too_short'     => __( 'This is too short.', 'whoiswho' ),
			'too_long'      => __( 'This is too long.', 'whoiswho' ),
		);
		return $words[ $code ] ?? __( 'Please check this.', 'whoiswho' );
	}
}//end if

if ( ! function_exists( 'wiw_form_value' ) ) {
	/**
	 * What the visitor typed in a field, to refill the form after a refusal. Empty on a first visit.
	 *
	 * @param string $field Field name.
	 */
	function wiw_form_value( string $field ): string {
		if ( array() === wiw_form_result() || ! class_exists( FormInput::class ) ) {
			return '';
		}
		return FormInput::from_globals()->raw( $field );
	}
}

if ( ! function_exists( 'wiw_form_banner' ) ) {
	/**
	 * Print the message of a refused submission above the form (an alert), or nothing when there was none.
	 */
	function wiw_form_banner(): void {
		$result = wiw_form_result();
		if ( array() === $result || empty( $result['message'] ) ) {
			return;
		}
		echo '<div class="errbox" role="alert"><p>' . esc_html( (string) $result['message'] ) . '</p></div>';
	}
}

add_filter(
	'wiw_form_done_url',
	static function ( $url, $result ): string {
		$form = is_array( $result ) && isset( $result['form'] ) ? sanitize_key( (string) $result['form'] ) : '';
		return '' === $form ? (string) $url : add_query_arg( 'from', $form, (string) $url );
	},
	10,
	2
);

add_filter(
	'query_vars',
	static function ( array $vars ): array {
		return array_merge( $vars, array( WIW_FORM_PAGE_VAR, WIW_CLAIM_SLUG_VAR, 'kind', 'from', 'ref' ) );
	}
);

add_action(
	'init',
	static function (): void {
		// The claim page carries a profile slug, so it has its own rule.
		add_rewrite_rule( '^(' . implode( '|', array_keys( array_diff_key( wiw_form_pages(), array( 'claim' => 1 ) ) ) ) . ')/?$', 'index.php?' . WIW_FORM_PAGE_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( '^claim/([A-Za-z0-9_-]+)/?$', 'index.php?' . WIW_FORM_PAGE_VAR . '=claim&' . WIW_CLAIM_SLUG_VAR . '=$matches[1]', 'top' );
		if ( '1' !== get_option( WIW_FORM_RULES_OP ) ) {
			flush_rewrite_rules( false );
			update_option( WIW_FORM_RULES_OP, '1', false );
		}
	},
	7
);

add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		$page = wiw_form_current();
		return '' === $page ? $title : wiw_form_pages()[ $page ]['title'] . ' | ' . wiw_site_name();
	}
);

add_action(
	'wp',
	static function (): void {
		// A form page has no post of its own, so WordPress marked the request 404 just before this hook: undo that.
		if ( '' !== wiw_form_current() && ( 'claim' !== wiw_form_current() || wiw_claim_is_servable() ) ) {
			global $wp_query;
			$wp_query->is_404 = false;
			// The plugin sets 4xx for a refused submission later, on template_redirect.
			status_header( 200 );
		} elseif ( 'claim' === wiw_form_current() ) {
			// A slug that is not a public profile is a 404 (WordPress does not flag it: the request looks like the home query).
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
);

add_action(
	'wp_head',
	static function (): void {
		if ( in_array( wiw_form_current(), array( 'done', 'claim' ), true ) ) {
			echo '<meta name="robots" content="noindex, nofollow">' . "\n";
		}
	},
	3
);
