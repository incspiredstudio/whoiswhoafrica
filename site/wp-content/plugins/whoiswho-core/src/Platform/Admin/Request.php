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
	 * @param array<string, mixed> $files  Uploaded files.
	 */
	public function __construct( array $values, array $files = array() ) {
		$this->values = $values;
		$this->files  = $files;
	}

	/**
	 * Uploaded files, as PHP reports them.
	 *
	 * @var array<string, mixed>
	 */
	private array $files;

	/**
	 * One uploaded file, or null when none was sent. Name, temporary path, size and error code only.
	 *
	 * @param string $key Field name.
	 * @return array{name: string, tmp_name: string, size: int, error: int}|null
	 */
	public function file( string $key ): ?array {
		$file = $this->files[ $key ] ?? null;
		if ( ! is_array( $file ) || ! isset( $file['tmp_name'], $file['error'] ) || is_array( $file['tmp_name'] ) ) {
			return null;
		}
		return array(
			'name'     => sanitize_file_name( (string) ( $file['name'] ?? '' ) ),
			'tmp_name' => (string) $file['tmp_name'],
			'size'     => (int) ( $file['size'] ?? 0 ),
			'error'    => (int) $file['error'],
		);
	}

	/**
	 * The current request. The one place the superglobals are read.
	 */
	public static function from_globals(): self {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- ActionGuard verifies the nonce before any handler reads a value.
		$values = array_merge( wp_unslash( $_GET ), wp_unslash( $_POST ) );
		return new self( $values, $_FILES ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ActionGuard verifies the nonce; file() returns only name, path, size and error.
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
