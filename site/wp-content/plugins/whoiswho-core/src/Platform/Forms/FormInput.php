<?php
/**
 * The request reader for public forms. Validators read submitted values only through this object; nothing else in the plugin
 * touches the superglobals for form data. Only from_globals() does, in one place.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Form input.
 */
final class FormInput {

	/**
	 * Values.
	 *
	 * @var array<string, mixed>
	 */
	private array $values;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $values Submitted values.
	 */
	public function __construct( array $values ) {
		$this->values = $values;
	}

	/**
	 * The current POST body. The one place the superglobal is read for form data.
	 */
	public static function from_globals(): self {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public forms use FormToken, verified by the bot challenge and gate.
		return new self( wp_unslash( $_POST ) );
	}

	/**
	 * Whether a field was submitted at all.
	 *
	 * @param string $key Field name.
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->values );
	}

	/**
	 * A raw scalar value as a string, or an empty string for anything else (arrays, objects).
	 *
	 * @param string $key Field name.
	 */
	public function raw( string $key ): string {
		$value = $this->values[ $key ] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	}
}
