<?php
/**
 * Puts a message into the shared HTML and plain-text layout. When the active theme supplies the branded layout
 * (wiw_render_email, which draws colours and fonts from the child theme's tokens file) it is used; otherwise a neutral
 * table layout with no brand values is used, so mail still goes out when no theme is active (and in unit tests).
 *
 * Parts: subject, preheader, heading, greeting, paragraphs (string[]), details (label => value), act (what to do, staff),
 * cta (array label, url) or null, class (transactional or marketing), unsubscribe_url.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail;

/**
 * Mail layout.
 */
final class MailLayout {

	/**
	 * Render a message in both formats.
	 *
	 * @param array<string, mixed> $parts Message parts.
	 * @return array{html: string, text: string}
	 */
	public static function render( array $parts ): array {
		$greeting   = (string) ( $parts['greeting'] ?? '' );
		$paragraphs = array_values( array_filter( array_map( 'strval', (array) ( $parts['paragraphs'] ?? array() ) ), static fn ( string $p ): bool => '' !== $p ) );
		$details    = (array) ( $parts['details'] ?? array() );
		$act        = (string) ( $parts['act'] ?? '' );
		$cta        = isset( $parts['cta'] ) && is_array( $parts['cta'] ) && ! empty( $parts['cta']['url'] ) ? $parts['cta'] : null;
		$marketing  = 'marketing' === ( $parts['class'] ?? '' );
		$unsub      = $marketing ? (string) ( $parts['unsubscribe_url'] ?? '' ) : '';
		$heading    = (string) ( $parts['heading'] ?? '' );
		$bare       = ! empty( $parts['bare'] );

		$html_body = '';
		if ( '' !== $greeting ) {
			$html_body .= '<p>' . self::esc( $greeting ) . '</p>';
		}
		foreach ( $paragraphs as $paragraph ) {
			$html_body .= '<p>' . self::link_urls( self::esc( $paragraph ) ) . '</p>';
		}
		if ( array() !== $details ) {
			$html_body .= '<ul>';
			foreach ( $details as $label => $value ) {
				$html_body .= '<li><strong>' . self::esc( (string) $label ) . ':</strong> ' . self::link_urls( self::esc( (string) $value ) ) . '</li>';
			}
			$html_body .= '</ul>';
		}
		if ( '' !== $act ) {
			$html_body .= '<p><strong>' . self::esc( 'What to do' ) . ':</strong> ' . self::esc( $act ) . '</p>';
		}

		$text_blocks = array();
		if ( '' !== $greeting ) {
			$text_blocks[] = $greeting;
		}
		foreach ( $paragraphs as $paragraph ) {
			$text_blocks[] = $paragraph;
		}
		if ( array() !== $details ) {
			$rows = array();
			foreach ( $details as $label => $value ) {
				$rows[] = $label . ': ' . $value;
			}
			$text_blocks[] = implode( "\n", $rows );
		}
		if ( '' !== $act ) {
			$text_blocks[] = 'What to do: ' . $act;
		}
		$body_text = implode( "\n\n", $text_blocks );

		if ( function_exists( 'wiw_render_email' ) ) {
			$mail = wiw_render_email(
				array(
					'subject'         => (string) ( $parts['subject'] ?? '' ),
					'preheader'       => (string) ( $parts['preheader'] ?? '' ),
					'heading'         => $heading,
					'body_html'       => $html_body,
					'body_text'       => $body_text,
					'cta'             => $cta,
					'class'           => $marketing ? 'marketing' : 'transactional',
					'unsubscribe_url' => $unsub,
					'contact_email'   => $bare ? '' : MailSettings::contact_email(),
					'privacy_url'     => $bare ? '' : MailSettings::privacy_url(),
					'bare'            => $bare,
				)
			);
			return array(
				'html' => $mail['html'],
				'text' => $mail['text'],
			);
		}//end if
		return self::fallback( (string) ( $parts['subject'] ?? '' ), $heading, $html_body, $body_text, $cta, $unsub, $bare );
	}

	/**
	 * Neutral layout: table based, 600 wide at most, real text, no colours and no images (client defaults, so dark mode works).
	 *
	 * @param string                    $subject   Subject.
	 * @param string                    $heading   Heading.
	 * @param string                    $html_body Body HTML (already escaped).
	 * @param string                    $text_body Plain body.
	 * @param array<string, mixed>|null $cta       Button.
	 * @param string                    $unsub     Unsubscribe address, marketing only.
	 * @param bool                      $bare      No links at all.
	 * @return array{html: string, text: string}
	 */
	private static function fallback( string $subject, string $heading, string $html_body, string $text_body, ?array $cta, string $unsub, bool $bare = false ): array {
		$brand  = MailSettings::brand();
		$button = '';
		$text   = ( '' !== $heading ? $heading . "\n\n" : '' ) . $text_body;
		if ( null !== $cta ) {
			$url    = (string) $cta['url'];
			$label  = (string) ( $cta['label'] ?? '' );
			$button = '<p><a href="' . self::esc( $url ) . '" style="display:inline-block;padding:12px 22px;border:2px solid;font-weight:bold;text-decoration:none">' . self::esc( $label ) . '</a></p>'
				. '<p style="font-size:13px">If the button does not work, copy this address into your browser:<br>' . self::link_urls( self::esc( $url ) ) . '</p>';
			$text  .= "\n\n" . $label . ': ' . $url;
		}
		$foot_html = '' !== $brand ? self::esc( $brand ) : '';
		$text     .= '' !== $brand ? "\n\n--\n" . $brand : '';
		$contact   = $bare ? '' : MailSettings::contact_email();
		if ( '' !== $contact ) {
			$foot_html .= ' &middot; ' . self::esc( $contact );
			$text      .= ' - ' . $contact;
		}
		if ( '' !== $unsub ) {
			$foot_html .= '<br>You receive this because you asked for it. <a href="' . self::esc( $unsub ) . '">Unsubscribe</a>';
			$text      .= "\nYou receive this because you asked for it. Unsubscribe: " . $unsub;
		}
		$html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light dark"><title>' . self::esc( $subject ) . '</title></head>'
			. '<body style="margin:0;padding:0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px"><tr><td style="padding:24px;font-size:16px;line-height:1.6">'
			. ( '' !== $heading ? '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.25">' . self::esc( $heading ) . '</h1>' : '' )
			. $html_body . $button
			. '<p style="font-size:13px;margin-top:32px">' . $foot_html . '</p>'
			. '</td></tr></table></td></tr></table></body></html>';
		return array(
			'html' => $html,
			'text' => $text,
		);
	}

	/**
	 * Escape text for HTML.
	 *
	 * @param string $text Text.
	 */
	private static function esc( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Turn the web addresses in escaped text into links. A full stop or bracket at the end is not part of the address.
	 *
	 * @param string $escaped HTML-escaped text.
	 */
	public static function link_urls( string $escaped ): string {
		return (string) preg_replace( '~(https?://[^\s<]*[^\s<.,;:!?)])~', '<a href="$1">$1</a>', $escaped );
	}
}
