<?php
/**
 * Base for the one-validator-per-form pattern. A form's validator declares its fields; the base reads them through
 * FormInput, cleans them (Unicode-safe, control characters removed) and enforces required and length limits on the server.
 * Nothing else reads request data.
 *
 * Field spec: array( 'type' => text|name|email|textarea, 'required' => bool, 'min' => int, 'max' => int ).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Validator.
 */
abstract class Validator {

	public const TYPE_TEXT     = 'text';
	public const TYPE_NAME     = 'name';
	public const TYPE_EMAIL    = 'email';
	public const TYPE_TEXTAREA = 'textarea';

	/**
	 * Default maximum lengths in characters, so no field is ever unbounded.
	 *
	 * @var array<string, int>
	 */
	private const DEFAULT_MAX = array(
		self::TYPE_TEXT     => 200,
		self::TYPE_NAME     => 120,
		self::TYPE_EMAIL    => 254,
		self::TYPE_TEXTAREA => 5000,
	);

	/**
	 * Form type, used in failure events.
	 */
	abstract public function form(): string;

	/**
	 * Field specs by field name.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	abstract protected function fields(): array;

	/**
	 * Validate a submission.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$values = array();
		$errors = array();
		foreach ( $this->fields() as $name => $spec ) {
			$type  = $spec['type'] ?? self::TYPE_TEXT;
			$clean = self::clean( $input->raw( $name ), self::TYPE_TEXTAREA === $type );
			$error = $this->check( $clean, $type, $spec );
			if ( null !== $error ) {
				$errors[ $name ] = $error;
			}
			$values[ $name ] = $clean;
		}
		$result = new ValidationResult( $values, $errors );
		if ( ! $result->is_valid() ) {
			// Field names and codes only: never submitted values (REQ-050, no personal data in events).
			FailureReport::fire( $this->form(), 'validation', array_keys( $errors ) );
		}
		return $result;
	}

	/**
	 * Normalise one value: valid UTF-8, no control characters, single-line values collapse whitespace.
	 *
	 * @param string $value     Raw value.
	 * @param bool   $multiline Keep line breaks.
	 */
	public static function clean( string $value, bool $multiline = false ): string {
		if ( 1 !== preg_match( '//u', $value ) ) {
			// Invalid UTF-8: drop the bad bytes rather than store them.
			$value = (string) mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
		}
		if ( $multiline ) {
			$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );
			return trim( str_replace( "\r\n", "\n", $value ) );
		}
		$value = (string) preg_replace( '/[\x00-\x1F\x7F]/u', ' ', $value );
		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}

	/**
	 * Check one cleaned value.
	 *
	 * @param string               $value Cleaned value.
	 * @param string               $type  Field type.
	 * @param array<string, mixed> $spec  Field spec.
	 * @return string|null Error code, or null when it passes.
	 */
	private function check( string $value, string $type, array $spec ): ?string {
		$length = mb_strlen( $value, 'UTF-8' );
		if ( 0 === $length ) {
			return ! empty( $spec['required'] ) ? 'required' : null;
		}
		$max = isset( $spec['max'] ) ? (int) $spec['max'] : ( self::DEFAULT_MAX[ $type ] ?? 200 );
		if ( $length > $max ) {
			return 'too_long';
		}
		if ( isset( $spec['min'] ) && $length < (int) $spec['min'] ) {
			return 'too_short';
		}
		if ( self::TYPE_NAME === $type && 1 !== preg_match( '/^[\p{L}\p{M}][\p{L}\p{M}\p{N} \'’.\-]*$/u', $value ) ) {
			return 'invalid_name';
		}
		if ( self::TYPE_EMAIL === $type && ! self::is_email( $value ) ) {
			return 'invalid_email';
		}
		return null;
	}

	/**
	 * Practical email check (one at sign, a dotted domain, no spaces); the mail server is the real test.
	 *
	 * @param string $value Address.
	 */
	private static function is_email( string $value ): bool {
		if ( 1 !== preg_match( '/^[^@\s]+@[^@\s]+\.[^@\s.]+$/u', $value ) ) {
			return false;
		}
		return false !== filter_var( $value, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE );
	}
}
