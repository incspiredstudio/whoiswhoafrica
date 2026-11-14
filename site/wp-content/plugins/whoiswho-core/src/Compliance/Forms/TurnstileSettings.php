<?php
/**
 * Cloudflare Turnstile keys with no wp-config.php edit (REQ-081, REQ-056). The Administrator enters the site key and the secret key
 * on the Setup screen. The secret is kept in a non-autoloaded option and is never shown again (the screen says only "saved"). The
 * WIW_TURNSTILE_SITE_KEY and WIW_TURNSTILE_SECRET constants, from the secrets file, win over anything saved here.
 *
 * Turnstile is switched on only when both keys are present: the site key draws the widget on the forms and the secret checks its
 * answer. With either missing, forms keep the built-in honeypot and signed-token check.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Forms;

use WhoIsWho\Core\Platform\Forms\BotChallenge;
use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\NullChallenge;

/**
 * Turnstile settings.
 */
final class TurnstileSettings {

	public const OPTION_SITE_KEY = 'wiw_turnstile_site_key';
	public const OPTION_SECRET   = 'wiw_turnstile_secret';

	public const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

	/**
	 * Longest key accepted; Cloudflare keys are well under this.
	 */
	private const MAX_LENGTH = 200;

	/**
	 * A constant's value, or an empty string.
	 *
	 * @param string $name Constant name.
	 */
	private static function constant_value( string $name ): string {
		return defined( $name ) ? trim( (string) constant( $name ) ) : '';
	}

	/**
	 * A stored option's value, or an empty string.
	 *
	 * @param string $option Option name.
	 */
	private static function stored( string $option ): string {
		$value = get_option( $option, '' );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * The public site key: the constant, else the saved one.
	 */
	public static function site_key(): string {
		$constant = self::constant_value( 'WIW_TURNSTILE_SITE_KEY' );
		return '' !== $constant ? $constant : self::stored( self::OPTION_SITE_KEY );
	}

	/**
	 * The secret key: the constant, else the saved one. Never print it.
	 */
	public static function secret(): string {
		$constant = self::constant_value( 'WIW_TURNSTILE_SECRET' );
		return '' !== $constant ? $constant : self::stored( self::OPTION_SECRET );
	}

	/**
	 * Whether the host set the keys (then the screen shows them as set by the host and does not offer to change them).
	 */
	public static function from_constants(): bool {
		return '' !== self::constant_value( 'WIW_TURNSTILE_SITE_KEY' ) || '' !== self::constant_value( 'WIW_TURNSTILE_SECRET' );
	}

	/**
	 * Whether a secret is saved (in either place).
	 */
	public static function has_secret(): bool {
		return '' !== self::secret();
	}

	/**
	 * Whether Turnstile is on: both keys are present.
	 */
	public static function active(): bool {
		return '' !== self::site_key() && '' !== self::secret();
	}

	/**
	 * Check the values from the form. A blank secret means "keep the saved one".
	 *
	 * @param string $site_key Site key as typed.
	 * @param string $secret   Secret key as typed.
	 * @param bool   $remove   Whether the "remove the keys" box was ticked.
	 * @return array{ok: bool, site_key: string, secret: string|null, remove: bool, error: string}
	 */
	public static function validate( string $site_key, string $secret, bool $remove ): array {
		$site_key = trim( $site_key );
		$secret   = trim( $secret );
		$out      = array(
			'ok'       => true,
			'site_key' => $site_key,
			'secret'   => '' === $secret ? null : $secret,
			'remove'   => $remove,
			'error'    => '',
		);
		if ( $remove ) {
			return $out;
		}
		foreach ( array( $site_key, $secret ) as $value ) {
			if ( strlen( $value ) > self::MAX_LENGTH || ( '' !== $value && 1 !== preg_match( '/^[A-Za-z0-9_\-]+$/', $value ) ) ) {
				$out['ok']    = false;
				$out['error'] = __( 'A key can hold only letters, digits, dashes and underscores. Copy it again from the Cloudflare dashboard.', 'whoiswho' );
				return $out;
			}
		}
		if ( '' === $site_key ) {
			$out['ok']    = false;
			$out['error'] = __( 'Enter the site key.', 'whoiswho' );
		} elseif ( null === $out['secret'] && '' === self::stored( self::OPTION_SECRET ) ) {
			$out['ok']    = false;
			$out['error'] = __( 'Enter the secret key.', 'whoiswho' );
		}
		return $out;
	}

	/**
	 * Save checked values. Both options are non-autoloaded.
	 *
	 * @param array{ok: bool, site_key: string, secret: string|null, remove: bool, error: string} $values From validate().
	 */
	public static function save( array $values ): void {
		if ( $values['remove'] ) {
			delete_option( self::OPTION_SITE_KEY );
			delete_option( self::OPTION_SECRET );
			return;
		}
		update_option( self::OPTION_SITE_KEY, $values['site_key'], false );
		if ( null !== $values['secret'] ) {
			update_option( self::OPTION_SECRET, $values['secret'], false );
		}
	}

	/**
	 * The challenge public forms use: Turnstile when both keys are present, otherwise the honeypot and signed token.
	 *
	 * @param FormToken $token Form token.
	 */
	public static function challenge( FormToken $token ): BotChallenge {
		return self::active() ? new TurnstileChallenge( TurnstileChallenge::remote_verifier( self::secret() ) ) : new NullChallenge( $token );
	}
}
