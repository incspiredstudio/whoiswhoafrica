<?php
/**
 * Signed, time-based token embedded in public forms. A submission is rejected if it arrives too fast (a bot), too late,
 * for another form, or if the token was altered. The signing key comes from the secrets file.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Form token.
 */
final class FormToken {

	public const OK       = 'ok';
	public const TOO_FAST = 'too_fast';
	public const EXPIRED  = 'expired';
	public const INVALID  = 'invalid';

	/**
	 * Signing key.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Clock, for tests.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param string        $key   Signing key.
	 * @param callable|null $clock Returns the current Unix time.
	 */
	public function __construct( string $key, ?callable $clock = null ) {
		$this->key   = $key;
		$this->clock = $clock ?? static fn (): int => time();
	}

	/**
	 * Token for the site: the key is FORM_TOKEN_KEY from the secrets file, falling back to a WordPress salt.
	 */
	public static function for_site(): self {
		$key = defined( 'WIW_FORM_TOKEN_KEY' ) ? (string) constant( 'WIW_FORM_TOKEN_KEY' ) : '';
		return new self( '' !== $key ? $key : wp_salt( 'auth' ) );
	}

	/**
	 * Issue a token for a form, to embed in a hidden field.
	 *
	 * @param string $form Form type.
	 */
	public function issue( string $form ): string {
		$payload = $form . '|' . ( (int) ( $this->clock )() ) . '|' . bin2hex( random_bytes( 8 ) );
		$sig     = hash_hmac( 'sha256', $payload, $this->key );
		return rtrim( strtr( base64_encode( $payload . '|' . $sig ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Check a submitted token.
	 *
	 * @param string $token       Submitted token.
	 * @param string $form        The form it must belong to.
	 * @param int    $min_seconds Rejected if submitted sooner than this after it was issued.
	 * @param int    $max_seconds Rejected if older than this.
	 * @return string One of the class constants.
	 */
	public function verify( string $token, string $form, int $min_seconds = 3, int $max_seconds = 3600 ): string {
		$raw   = base64_decode( strtr( $token, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$parts = false === $raw ? array() : explode( '|', $raw );
		if ( 4 !== count( $parts ) ) {
			return self::INVALID;
		}
		list( $token_form, $issued, $nonce, $sig ) = $parts;
		$expected                                  = hash_hmac( 'sha256', $token_form . '|' . $issued . '|' . $nonce, $this->key );
		if ( ! hash_equals( $expected, $sig ) || $token_form !== $form || ! ctype_digit( $issued ) ) {
			return self::INVALID;
		}
		$age = (int) ( $this->clock )() - (int) $issued;
		if ( $age < $min_seconds ) {
			return self::TOO_FAST;
		}
		if ( $age > $max_seconds ) {
			return self::EXPIRED;
		}
		return self::OK;
	}
}
