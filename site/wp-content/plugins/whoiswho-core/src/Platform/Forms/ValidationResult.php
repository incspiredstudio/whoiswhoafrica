<?php
/**
 * Outcome of validating one form: cleaned values and error codes per field.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Validation result.
 */
final class ValidationResult {

	/**
	 * Cleaned values by field.
	 *
	 * @var array<string, string>
	 */
	private array $values;

	/**
	 * Error codes by field.
	 *
	 * @var array<string, string>
	 */
	private array $errors;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $values Cleaned values.
	 * @param array<string, string> $errors Error code per failing field.
	 */
	public function __construct( array $values, array $errors ) {
		$this->values = $values;
		$this->errors = $errors;
	}

	/**
	 * Whether every field passed.
	 */
	public function is_valid(): bool {
		return array() === $this->errors;
	}

	/**
	 * Cleaned values.
	 *
	 * @return array<string, string>
	 */
	public function values(): array {
		return $this->values;
	}

	/**
	 * One cleaned value.
	 *
	 * @param string $field Field name.
	 */
	public function value( string $field ): string {
		return $this->values[ $field ] ?? '';
	}

	/**
	 * Error codes: required, too_short, too_long, invalid_name, invalid_email.
	 *
	 * @return array<string, string>
	 */
	public function errors(): array {
		return $this->errors;
	}
}
