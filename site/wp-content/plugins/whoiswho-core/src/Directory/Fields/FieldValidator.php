<?php
/**
 * Validates a record's fields against the allowlist for its entity type (REQ-139, REQ-140, REQ-142, REQ-143): forbidden and
 * unknown fields, required fields, value types, and per-field provenance (present, complete, not low confidence). Plain PHP.
 * Refusal codes follow the import contract: FORBIDDEN_FIELD, SCHEMA, UNSOURCED_FIELD, LOW_CONFIDENCE.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Fields;

use WhoIsWho\Core\Directory\State\ProvenanceRepository;

/**
 * Field validator.
 */
final class FieldValidator {

	/**
	 * Allowlists.
	 *
	 * @var FieldAllowlist
	 */
	private FieldAllowlist $allowlist;

	/**
	 * Constructor.
	 *
	 * @param FieldAllowlist|null $allowlist Allowlists.
	 */
	public function __construct( ?FieldAllowlist $allowlist = null ) {
		$this->allowlist = $allowlist ?? new FieldAllowlist();
	}

	/**
	 * Validate the fields of one record.
	 *
	 * @param string               $entity_type Entity type.
	 * @param array<string, mixed> $fields      Key => array{value: mixed, provenance: array}.
	 * @return array<int, array{field: string, code: string}> Refusals; empty when the fields are acceptable.
	 */
	public function validate( string $entity_type, array $fields ): array {
		$refusals = array();
		$rules    = $this->allowlist->for_type( $entity_type );
		if ( array() === $rules ) {
			return array(
				array(
					'field' => '',
					'code'  => 'SCHEMA',
				),
			);
		}
		foreach ( $fields as $key => $field ) {
			$key = (string) $key;
			if ( $this->allowlist->is_forbidden( $key ) ) {
				$refusals[] = $this->refusal( $key, 'FORBIDDEN_FIELD' );
				continue;
			}
			if ( ! isset( $rules[ $key ] ) ) {
				$refusals[] = $this->refusal( $key, 'SCHEMA' );
				continue;
			}
			if ( ! is_array( $field ) || ! array_key_exists( 'value', $field ) || ! $this->value_ok( $rules[ $key ], $field['value'] ) ) {
				$refusals[] = $this->refusal( $key, 'SCHEMA' );
				continue;
			}
			$code = $this->provenance_code( $key, $field['provenance'] ?? null );
			if ( null !== $code ) {
				$refusals[] = $this->refusal( $key, $code );
			}
		}
		foreach ( $rules as $key => $rule ) {
			if ( ! empty( $rule['required'] ) && ! isset( $fields[ $key ] ) ) {
				$refusals[] = $this->refusal( $key, 'SCHEMA' );
			}
		}
		return $refusals;
	}

	/**
	 * The refusal for the provenance of one field, or null when it is fine.
	 *
	 * @param string $key        Field key.
	 * @param mixed  $provenance The provenance list.
	 */
	public function provenance_code( string $key, $provenance ): ?string {
		if ( ! is_array( $provenance ) || array() === $provenance ) {
			return 'UNSOURCED_FIELD';
		}
		$low = false;
		foreach ( $provenance as $entry ) {
			if ( ! is_array( $entry ) ) {
				return 'UNSOURCED_FIELD';
			}
			$code = ProvenanceRepository::refusal( array_merge( $entry, array( 'field' => $key ) ) );
			if ( 'LOW_CONFIDENCE' === $code ) {
				$low = true;
			} elseif ( null !== $code ) {
				return $code;
			}
		}
		return $low ? 'LOW_CONFIDENCE' : null;
	}

	/**
	 * Whether a value fits its rule.
	 *
	 * @param array<string, mixed> $rule  Rule.
	 * @param mixed                $value Value.
	 */
	private function value_ok( array $rule, $value ): bool {
		$max = (int) ( $rule['max'] ?? 300 );
		switch ( $rule['type'] ) {
			case 'int':
				return is_int( $value ) && $value > 0;
			case 'url':
				return is_string( $value ) && strlen( $value ) <= 2048 && false !== filter_var( $value, FILTER_VALIDATE_URL ) && 1 === preg_match( '#^https?://#i', $value );
			case 'list':
				if ( ! is_array( $value ) || array() === $value ) {
					return false;
				}
				foreach ( $value as $item ) {
					if ( ! is_string( $item ) || '' === trim( $item ) || strlen( $item ) > $max ) {
						return false;
					}
				}
				return true;
			default:
				return is_string( $value ) && '' !== trim( $value ) && strlen( $value ) <= $max;
		}
	}

	/**
	 * One refusal.
	 *
	 * @param string $field Field key.
	 * @param string $code  Code.
	 * @return array{field: string, code: string}
	 */
	private function refusal( string $field, string $code ): array {
		return array(
			'field' => $field,
			'code'  => $code,
		);
	}
}
