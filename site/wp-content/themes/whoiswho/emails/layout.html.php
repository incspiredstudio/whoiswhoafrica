<?php
/**
 * HTML email layout (REQ-075, REQ-043, REQ-052): table layout for mail clients, text first, one call to action, brand values only
 * through $style (resolved from the child theme's tokens). Variables: $args (see wiw_render_email) and $style.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Variables set by wiw_render_email().
 *
 * @var array<string, mixed> $args
 * @var array<string, string> $style
 */

/**
 * Inline declaration for a style key, or nothing when the token is missing.
 *
 * @param string               $prop  CSS property.
 * @param string               $key   Style key.
 * @param array<string, mixed> $style Style map.
 */
$wiw_decl = static function ( string $prop, string $key, array $style ): string {
	return '' !== (string) ( $style[ $key ] ?? '' ) ? $prop . ':' . $style[ $key ] . ';' : '';
};

$wiw_marketing = 'marketing' === $args['class'];
$wiw_logo      = get_stylesheet_directory_uri() . '/assets/logo/wiw-logo.png';
$wiw_cta       = is_array( $args['cta'] ) && ! empty( $args['cta']['url'] ) ? $args['cta'] : null;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', get_locale() ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?php echo esc_html( (string) $args['subject'] ); ?></title>
</head>
<body style="margin:0;padding:0;<?php echo esc_attr( $wiw_decl( 'background-color', 'page', $style ) . $wiw_decl( 'color', 'text', $style ) . $wiw_decl( 'font-family', 'body', $style ) ); ?>">
<?php if ( '' !== (string) $args['preheader'] ) : ?>
<div style="display:none;max-height:0;overflow:hidden;opacity:0;"><?php echo esc_html( (string) $args['preheader'] ); ?></div>
<?php endif; ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="<?php echo esc_attr( $wiw_decl( 'background-color', 'page', $style ) ); ?>">
	<tr>
		<td align="center" style="padding:24px 12px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;<?php echo esc_attr( $wiw_decl( 'background-color', 'card', $style ) ); ?>">
				<tr>
					<td style="padding:20px 28px;<?php echo esc_attr( '' !== (string) ( $style['line'] ?? '' ) ? 'border-bottom:1px solid ' . $style['line'] . ';' : '' ); ?>">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="text-decoration:none;<?php echo esc_attr( $wiw_decl( 'color', 'text', $style ) ); ?>"><img src="<?php echo esc_url( $wiw_logo ); ?>" alt="<?php echo esc_attr( wiw_brand( 'name' ) ); ?>" width="140" style="display:block;border:0;height:auto;max-width:140px;"></a>
					</td>
				</tr>
				<tr>
					<td style="padding:28px;font-size:16px;line-height:1.6;<?php echo esc_attr( $wiw_decl( 'color', 'text', $style ) . $wiw_decl( 'font-family', 'body', $style ) ); ?>">
						<?php if ( '' !== (string) $args['heading'] ) : ?>
							<h1 style="margin:0 0 16px;font-size:26px;line-height:1.2;font-weight:400;<?php echo esc_attr( $wiw_decl( 'font-family', 'headline', $style ) . $wiw_decl( 'color', 'text', $style ) ); ?>"><?php echo esc_html( (string) $args['heading'] ); ?></h1>
						<?php endif; ?>
						<div><?php echo wp_kses( (string) $args['body_html'], wiw_email_kses() ); ?></div>
						<?php if ( null !== $wiw_cta ) : ?>
							<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px;">
								<tr>
									<td style="border-radius:4px;<?php echo esc_attr( $wiw_decl( 'background-color', 'cta', $style ) ); ?>">
										<a href="<?php echo esc_url( (string) $wiw_cta['url'] ); ?>" style="display:inline-block;padding:14px 24px;font-weight:700;text-decoration:none;<?php echo esc_attr( $wiw_decl( 'color', 'on_cta', $style ) . $wiw_decl( 'font-family', 'body', $style ) ); ?>"><?php echo esc_html( (string) ( $wiw_cta['label'] ?? '' ) ); ?></a>
									</td>
								</tr>
							</table>
							<p style="margin:8px 0 0;font-size:13px;<?php echo esc_attr( $wiw_decl( 'color', 'muted', $style ) ); ?>"><?php esc_html_e( 'If the button does not work, copy this address into your browser:', 'whoiswho' ); ?><br><a href="<?php echo esc_url( (string) $wiw_cta['url'] ); ?>" style="word-break:break-all;<?php echo esc_attr( $wiw_decl( 'color', 'link', $style ) ); ?>"><?php echo esc_html( (string) $wiw_cta['url'] ); ?></a></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding:20px 28px;font-size:13px;line-height:1.5;<?php echo esc_attr( $wiw_decl( 'background-color', 'dark', $style ) . $wiw_decl( 'color', 'on_dark', $style ) . $wiw_decl( 'font-family', 'body', $style ) ); ?>">
						<?php echo esc_html( wiw_brand( 'legal_name' ) ); ?> · <?php echo esc_html( wiw_brand( 'domain' ) ); ?>
						<?php if ( $wiw_marketing && '' !== (string) $args['unsubscribe_url'] ) : ?>
							<br><?php esc_html_e( 'You receive this because you asked for it.', 'whoiswho' ); ?>
							<a href="<?php echo esc_url( (string) $args['unsubscribe_url'] ); ?>" style="<?php echo esc_attr( $wiw_decl( 'color', 'on_dark', $style ) ); ?>text-decoration:underline;"><?php esc_html_e( 'Unsubscribe', 'whoiswho' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
