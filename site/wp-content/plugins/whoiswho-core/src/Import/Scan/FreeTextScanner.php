<?php
/**
 * Scans the free-text fields of an import record for personal data (REQ-141, REQ-068): phone numbers, email addresses, street
 * addresses, ages and dates of birth, and sensitive terms from the config list. Plain PHP, no WordPress calls. The result names
 * the field and the category only; the matched text is never returned, so it cannot be logged by accident.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Scan;

/**
 * Free text scanner.
 */
final class FreeTextScanner {

	public const PHONE     = 'phone';
	public const EMAIL     = 'email';
	public const ADDRESS   = 'address';
	public const AGE       = 'age';
	public const SENSITIVE = 'sensitive_term';

	/**
	 * Fields that hold free text.
	 */
	public const FIELDS = array( 'summary', 'mandate', 'public_record_roles', 'published_awards' );

	/**
	 * Sensitive terms, lower case.
	 *
	 * @var string[]
	 */
	private array $terms;

	/**
	 * Constructor.
	 *
	 * @param string[]|null $terms Sensitive terms; null loads the config list.
	 */
	public function __construct( ?array $terms = null ) {
		if ( null === $terms ) {
			$config = require dirname( __DIR__, 3 ) . '/config/sensitive-terms.php';
			$terms  = (array) $config['terms'];
		}
		$this->terms = array_values( array_filter( array_map( static fn ( $term ): string => strtolower( trim( (string) $term ) ), $terms ) ) );
	}

	/**
	 * Scan the free-text fields of a record.
	 *
	 * @param array<string, mixed> $fields Field key => value, or field key => array with a "value" key (a contract record's fields).
	 * @return array<int, array{field: string, category: string}> One entry per field and category found, in field order.
	 */
	public function scan( array $fields ): array {
		$found = array();
		foreach ( self::FIELDS as $key ) {
			if ( ! array_key_exists( $key, $fields ) ) {
				continue;
			}
			$value = is_array( $fields[ $key ] ) && array_key_exists( 'value', $fields[ $key ] ) ? $fields[ $key ]['value'] : $fields[ $key ];
			$text  = self::flatten( $value );
			if ( '' === $text ) {
				continue;
			}
			foreach ( $this->categories( $text ) as $category ) {
				$found[] = array(
					'field'    => $key,
					'category' => $category,
				);
			}
		}
		return $found;
	}

	/**
	 * The categories found in a piece of text.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public function categories( string $text ): array {
		$out = array();
		if ( 1 === preg_match( '/[a-z0-9._%+\-]+@[a-z0-9\-]+(?:\.[a-z0-9\-]+)*\.[a-z]{2,}/i', $text ) ) {
			$out[] = self::EMAIL;
		}
		if ( $this->has_phone( $text ) ) {
			$out[] = self::PHONE;
		}
		if ( 1 === preg_match( '/\b\d{1,5}[a-z]?,?\s+[\w.\' \-]{2,40}?\s(?:street|st|road|rd|avenue|ave|close|crescent|drive|lane|way|boulevard|blvd|estate|layout)\b|\bp\.?\s?o\.?\s?box\s+\d+|\bplot\s+\d+/i', $text ) ) {
			$out[] = self::ADDRESS;
		}
		if ( 1 === preg_match( '/\b(?:aged?\s*:?\s*\d{1,3}|\d{1,3}[\s-]+years?[\s-]+old|born\s+(?:on|in)?\s*\d|born\s+(?:on\s+)?(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+\d|date\s+of\s+birth|dob)\b/i', $text ) ) {
			$out[] = self::AGE;
		}
		if ( $this->has_sensitive_term( $text ) ) {
			$out[] = self::SENSITIVE;
		}
		return $out;
	}

	/**
	 * Whether the text holds a phone number: 9 to 15 digits in one run of digits and separators, written as an international
	 * or local number (a leading plus or zero, brackets, or at least ten digits).
	 *
	 * @param string $text Text.
	 */
	private function has_phone( string $text ): bool {
		if ( ! preg_match_all( '/\+?\(?\d[\d\s().\-]{6,}\d/', $text, $matches ) ) {
			return false;
		}
		foreach ( $matches[0] as $candidate ) {
			$digits = (string) preg_replace( '/\D/', '', $candidate );
			$length = strlen( $digits );
			if ( $length < 9 || $length > 15 ) {
				continue;
			}
			// A run of years or a date range is not a phone number.
			if ( 1 === preg_match( '/^(?:(?:19|20)\d{2}[\s\-.,]*)+$/', $candidate ) ) {
				continue;
			}
			$trimmed = ltrim( $candidate );
			if ( in_array( $trimmed[0], array( '+', '0', '(' ), true ) || $length >= 10 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the text holds a sensitive term as a whole word or phrase.
	 *
	 * @param string $text Text.
	 */
	private function has_sensitive_term( string $text ): bool {
		$lower = strtolower( $text );
		foreach ( $this->terms as $term ) {
			if ( 1 === preg_match( '/(?<![a-z0-9])' . preg_quote( $term, '/' ) . '(?![a-z0-9])/', $lower ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A value as one string: a list is joined with line breaks.
	 *
	 * @param mixed $value Value.
	 */
	private static function flatten( $value ): string {
		if ( is_array( $value ) ) {
			return implode( "\n", array_map( static fn ( $item ): string => is_scalar( $item ) ? (string) $item : '', $value ) );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
}
