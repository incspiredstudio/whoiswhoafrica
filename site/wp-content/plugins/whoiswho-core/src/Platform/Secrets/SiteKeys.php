<?php
/**
 * Site keys with no setup step (REQ-056, REQ-046): the form-token signing key, the signed-link key and the health-check token.
 *
 * A key set by the site's host (the WIW_* constant, or the secrets file through wiw_secret()) always wins. When neither is there,
 * as on a plain WordPress install where the owner uploaded the plugin zip, the key is made once with random_bytes and kept in a
 * non-autoloaded option, so the site works with no wp-config.php edit. A stored key is never shown in the admin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Secrets;

/**
 * Site keys.
 */
final class SiteKeys {

	public const FORM_TOKEN_KEY  = 'FORM_TOKEN_KEY';
	public const SIGNED_LINK_KEY = 'SIGNED_LINK_KEY';
	public const HEALTHZ_TOKEN   = 'HEALTHZ_TOKEN';

	/**
	 * Each key: the constant that overrides it and the option that stores a generated value.
	 */
	private const KEYS = array(
		self::FORM_TOKEN_KEY  => array(
			'constant' => 'WIW_FORM_TOKEN_KEY',
			'option'   => 'wiw_key_form_token',
		),
		self::SIGNED_LINK_KEY => array(
			'constant' => 'WIW_SIGNED_LINK_KEY',
			'option'   => 'wiw_key_signed_link',
		),
		self::HEALTHZ_TOKEN   => array(
			'constant' => 'WIW_HEALTHZ_TOKEN',
			'option'   => 'wiw_key_healthz_token',
		),
	);

	/**
	 * The form-token signing key.
	 */
	public static function form_token_key(): string {
		return self::get( self::FORM_TOKEN_KEY );
	}

	/**
	 * The signed-link key.
	 */
	public static function signed_link_key(): string {
		return self::get( self::SIGNED_LINK_KEY );
	}

	/**
	 * The health-check token.
	 */
	public static function healthz_token(): string {
		return self::get( self::HEALTHZ_TOKEN );
	}

	/**
	 * The option a generated key is stored in.
	 *
	 * @param string $name Key name, one of the class constants.
	 * @throws \InvalidArgumentException For an unknown name.
	 */
	public static function option_name( string $name ): string {
		if ( ! isset( self::KEYS[ $name ] ) ) {
			throw new \InvalidArgumentException( 'Unknown site key.' );
		}
		return self::KEYS[ $name ]['option'];
	}

	/**
	 * Where the key in use comes from: "configured" (a constant or the secrets file) or "generated" (stored by this class).
	 *
	 * @param string $name Key name.
	 */
	public static function source( string $name ): string {
		return '' !== self::configured( $name ) ? 'configured' : 'generated';
	}

	/**
	 * A key: the configured value, else the stored one, else a new random one that is stored for next time.
	 *
	 * @param string                          $name   Key name, one of the class constants.
	 * @param (callable(string): string)|null $lookup Reads the configured value by constant name (tests pass one); constants by default.
	 */
	public static function get( string $name, ?callable $lookup = null ): string {
		$option     = self::option_name( $name );
		$configured = self::configured( $name, $lookup );
		if ( '' !== $configured ) {
			return $configured;
		}
		$stored = get_option( $option, '' );
		if ( is_string( $stored ) && '' !== $stored ) {
			return $stored;
		}
		$fresh = bin2hex( random_bytes( 32 ) );
		if ( ! add_option( $option, $fresh, '', false ) ) {
			// Another request stored one first, or a stale empty value is there: use what is stored, replacing an empty one.
			wp_cache_delete( $option, 'options' );
			$stored = get_option( $option, '' );
			if ( is_string( $stored ) && '' !== $stored ) {
				return $stored;
			}
			update_option( $option, $fresh, false );
		}
		return $fresh;
	}

	/**
	 * The configured value: the constant, else the secrets file; empty when neither has one.
	 *
	 * @param string                          $name   Key name.
	 * @param (callable(string): string)|null $lookup Reads a constant by name.
	 */
	private static function configured( string $name, ?callable $lookup = null ): string {
		$constant = self::KEYS[ $name ]['constant'];
		if ( null !== $lookup ) {
			return trim( (string) $lookup( $constant ) );
		}
		$value = defined( $constant ) ? trim( (string) constant( $constant ) ) : '';
		if ( '' === $value && function_exists( 'wiw_secret' ) ) {
			$value = trim( (string) wiw_secret( $name ) );
		}
		return $value;
	}
}
