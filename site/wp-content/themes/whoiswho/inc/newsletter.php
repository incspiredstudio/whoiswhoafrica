<?php
/**
 * Newsletter pages (REQ-061, REQ-249, REQ-058): the sign-up page at /newsletter (its form posts to the plugin, which answers a
 * refusal on the same address), and the page the plugin shows for the confirmation link, in its confirmed and expired states.
 * The confirm page never repeats the email address. The route and the token handling are in the plugin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

const WIW_NEWSLETTER_RULES_OP = 'wiw_theme_newsletter_rules';

add_action(
	'init',
	static function (): void {
		if ( '1' !== get_option( WIW_NEWSLETTER_RULES_OP ) ) {
			flush_rewrite_rules( false );
			update_option( WIW_NEWSLETTER_RULES_OP, '1', false );
		}
	},
	9
);

add_filter(
	'wiw_newsletter_confirm_page',
	static function ( $page, $state ): string {
		$confirmed = is_array( $state ) && isset( $state['state'] ) && 'confirmed' === $state['state'];
		ob_start();
		get_header();
		if ( $confirmed ) {
			get_template_part(
				'template-parts/done',
				null,
				array(
					'title' => __( 'You are subscribed', 'whoiswho' ),
					'text'  => __( 'Every email has an unsubscribe link. You can stop at any time.', 'whoiswho' ),
				)
			);
		} else {
			get_template_part(
				'template-parts/done',
				null,
				array(
					'title'      => __( 'This link has expired or was already used', 'whoiswho' ),
					'text'       => __( 'Confirmation links work once and for a limited time. You can sign up again and we will send a new one.', 'whoiswho' ),
					'back_url'   => home_url( '/newsletter/' ),
					'back_label' => __( 'Sign up again', 'whoiswho' ),
				)
			);
		}//end if
		get_footer();
		return (string) ob_get_clean();
	},
	10,
	2
);

add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		if ( '' === (string) get_query_var( 'wiw_nl_confirm', '' ) ) {
			return $title;
		}
		return __( 'Newsletter confirmation', 'whoiswho' ) . ' | ' . wiw_site_name();
	},
	11
);
