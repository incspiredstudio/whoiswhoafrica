<?php
/**
 * Base email templates (REQ-075, REQ-043, REQ-052): one layout in HTML and plain text, brand values drawn from the child theme's
 * tokens through the names listed in its config/email-style.php. One purpose per message; links carry no personal data.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_email_tokens' ) ) {
	/**
	 * Custom properties declared in the active theme's tokens file, resolved to plain values (var() references followed).
	 *
	 * @return array<string, string>
	 */
	function wiw_email_tokens(): array {
		static $cache = array();
		$file         = get_stylesheet_directory() . '/assets/css/tokens.css';
		if ( isset( $cache[ $file ] ) ) {
			return $cache[ $file ];
		}
		$raw = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$raw = (string) preg_replace( '#/\*.*?\*/#s', '', $raw );
		preg_match_all( '/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $raw, $found, PREG_SET_ORDER );
		$defs = array();
		foreach ( $found as $match ) {
			$defs[ $match[1] ] = trim( $match[2] );
		}
		$resolved = array();
		foreach ( $defs as $name => $value ) {
			$depth = 0;
			while ( $depth < 5 && 1 === preg_match( '/^var\((--[a-z0-9-]+)\)$/i', $value, $ref ) ) {
				$value = $defs[ $ref[1] ] ?? '';
				++$depth;
			}
			$resolved[ $name ] = $value;
		}
		$cache[ $file ] = $resolved;
		return $resolved;
	}
}//end if

if ( ! function_exists( 'wiw_email_style' ) ) {
	/**
	 * The inline-style values for the email layout: key => value, from the child theme's token map. A missing token gives an
	 * empty value, which the layout renders as no declaration (the client default), never a literal.
	 *
	 * @return array<string, string>
	 */
	function wiw_email_style(): array {
		$file = get_stylesheet_directory() . '/config/email-style.php';
		$map  = is_readable( $file ) ? require $file : array();
		$out  = array();
		$all  = wiw_email_tokens();
		foreach ( is_array( $map ) ? $map : array() as $key => $token ) {
			$out[ (string) $key ] = (string) ( $all[ (string) $token ] ?? '' );
		}
		return $out;
	}
}

if ( ! function_exists( 'wiw_email_args' ) ) {
	/**
	 * Fill in the defaults of the layout arguments.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return array<string, mixed>
	 */
	function wiw_email_args( array $args ): array {
		return wp_parse_args(
			$args,
			array(
				'subject'         => '',
				'preheader'       => '',
				'heading'         => '',
				'body_html'       => '',
				'body_text'       => '',
				'cta'             => null,
				'class'           => 'transactional',
				'unsubscribe_url' => '',
				'contact_email'   => '',
				'privacy_url'     => '',
				'bare'            => false,
			)
		);
	}
}//end if

if ( ! function_exists( 'wiw_email_kses' ) ) {
	/**
	 * Allowed markup in a message body.
	 *
	 * @return array<string, array<string, bool>>
	 */
	function wiw_email_kses(): array {
		return array(
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'a'      => array( 'href' => true ),
		);
	}
}

if ( ! function_exists( 'wiw_render_email' ) ) {
	/**
	 * Render a message in both formats.
	 *
	 * Arguments: subject, preheader, heading, body_html (limited markup), body_text (plain), cta (array label, url),
	 * class (transactional or marketing), unsubscribe_url (shown only for marketing), contact_email and privacy_url (footer), bare (no logo link, no links at all).
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return array{subject: string, html: string, text: string}
	 */
	function wiw_render_email( array $args ): array {
		$args = wiw_email_args( $args );
		if ( '' === $args['body_text'] && '' !== $args['body_html'] ) {
			$args['body_text'] = trim( html_entity_decode( wp_strip_all_tags( (string) preg_replace( '#</(p|li)>#i', "\n\n", (string) $args['body_html'] ) ), ENT_QUOTES, 'UTF-8' ) );
		}
		$style = wiw_email_style();
		$dir   = get_template_directory() . '/emails/';

		ob_start();
		include $dir . 'layout.html.php';
		$html = (string) ob_get_clean();

		ob_start();
		include $dir . 'layout.txt.php';
		$text = (string) ob_get_clean();

		return array(
			'subject' => (string) $args['subject'],
			'html'    => $html,
			'text'    => $text,
		);
	}
}//end if

if ( ! function_exists( 'wiw_email_samples' ) ) {
	/**
	 * Sample messages for the preview command and the tests. Links carry no personal data.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function wiw_email_samples(): array {
		return array(
			'transactional' => array(
				'subject'   => __( 'Confirm your email address', 'whoiswho' ),
				'preheader' => __( 'One step left: confirm this address.', 'whoiswho' ),
				'heading'   => __( 'Confirm your email address', 'whoiswho' ),
				'body_html' => '<p>' . esc_html__( 'Use the button to confirm this address. The link works once and expires.', 'whoiswho' ) . '</p>',
				'cta'       => array(
					'label' => __( 'Confirm email', 'whoiswho' ),
					'url'   => home_url( '/confirm/sample-token/' ),
				),
				'class'     => 'transactional',
			),
			'marketing'     => array(
				'subject'         => __( 'Stories from the directory', 'whoiswho' ),
				'preheader'       => __( 'A short digest of new stories.', 'whoiswho' ),
				'heading'         => __( 'Stories from the directory', 'whoiswho' ),
				'body_html'       => '<p>' . esc_html__( 'New stories were published this week.', 'whoiswho' ) . '</p>',
				'cta'             => array(
					'label' => __( 'Read the stories', 'whoiswho' ),
					'url'   => home_url( '/stories/' ),
				),
				'class'           => 'marketing',
				'unsubscribe_url' => home_url( '/unsubscribe/sample-token/' ),
			),
		);
	}
}//end if

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
	/**
	 * Print each sample message, as plain text or HTML (wp wiw-email-preview [--format=html]).
	 *
	 * @param array<int, string>    $args       Positional.
	 * @param array<string, string> $assoc_args Named.
	 */
	$wiw_email_preview = static function ( array $args, array $assoc_args ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$format = isset( $assoc_args['format'] ) && 'html' === $assoc_args['format'] ? 'html' : 'text';
		foreach ( wiw_email_samples() as $name => $sample ) {
			$mail = wiw_render_email( $sample );
			WP_CLI::line( '=== ' . $name . ' (' . $format . ') ===' );
			WP_CLI::line( $mail[ $format ] );
		}
	};
	WP_CLI::add_command( 'wiw-email-preview', $wiw_email_preview );
}
