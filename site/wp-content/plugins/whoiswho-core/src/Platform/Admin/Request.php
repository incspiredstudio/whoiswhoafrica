<?php
/**
 * A read-only view of one request. Handlers read input through this object, never from the superglobals. Only from_globals()
 * touches them, in one place.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Admin;

/**
 * Request.
 */
final class Request {

	/**
	 * Values.
	 *
	 * @var array<string, mixed>
	 */
	private array $values;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $values Request values (query and body merged).
	 */
	public function __construct( array $values ) {
		$this->values = $values;
	}

	/**
	 * The current request. The one place the superglobals are read.
	 */
	public static function from_globals(): self {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- ActionGuard verifies the nonce before any handler reads a value.
		$values = array_merge( wp_unslash( $_GET ), wp_unslash( $_POST ) );
		return new self( $values );
	}

	/**
	 * A text value, or an empty string.
	 *
	 * @param string $key Name.
	 */
	public function string( string $key ): string {
		$value = $this->values[ $key ] ?? '';
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Whether a value was sent for a name (an empty value counts as sent).
	 *
	 * @param string $key Name.
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->values );
	}

	/**
	 * A multi-line text value (a textarea), line breaks kept, or an empty string.
	 *
	 * @param string $key Name.
	 */
	public function text( string $key ): string {
		$value = $this->values[ $key ] ?? '';
		return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
	}

	/**
	 * A whole number, or zero.
	 *
	 * @param string $key Name.
	 */
	public function int( string $key ): int {
		$value = $this->values[ $key ] ?? 0;
		return is_scalar( $value ) ? (int) $value : 0;
	}

	/**
	 * A list of whole numbers (for example checkboxes or a multi-select), or an empty list.
	 *
	 * @param string $key Name.
	 * @return array<int, int>
	 */
	public function ints( string $key ): array {
		$value = $this->values[ $key ] ?? array();
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			if ( is_scalar( $item ) ) {
				$out[] = (int) $item;
			}
		}
		return $out;
	}

	/**
	 * The nonce sent with the request.
	 */
	public function nonce(): string {
		$value = $this->values['_wpnonce'] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	}
}
