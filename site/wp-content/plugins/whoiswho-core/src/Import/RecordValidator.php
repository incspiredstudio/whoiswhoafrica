<?php
/**
 * Validates one record of a bundle (contract sections 3 to 8 and 10, REQ-140, REQ-142, REQ-143, REQ-212, REQ-214, REQ-221):
 * schema, allowlisted fields per entity type, provenance on every field, confidence not low, a passing verification report, the
 * recomputed content hash and empty images. A failing record gets exactly one contract code and the rest of the bundle carries
 * on; nothing is written here, so a refused record can leave no partial write. Plain PHP, no WordPress calls.
 *
 * Order of checks (the first failure is the one code returned): SCHEMA (including any state, publish, visibility or status key),
 * CONTRACT_VERSION, IMAGE_NOT_ALLOWED, FORBIDDEN_FIELD, field SCHEMA, UNSOURCED_FIELD, LOW_CONFIDENCE, VERIFICATION_FAILED,
 * HASH_MISMATCH. The SUPPRESSED check needs the live suppression list and is made by the importer's apply step.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\Fields\FieldValidator;

/**
 * Record validator.
 */
final class RecordValidator {

	/**
	 * Every key a record may have.
	 */
	public const KEYS = array( 'contract_version', 'record_id', 'record_version', 'run_id', 'batch_id', 'entity_type', 'tier', 'country', 'external_ids', 'inclusion', 'fields', 'claims', 'passages', 'images', 'ai', 'flags', 'verification', 'content_hash' );

	public const ENTITY_TYPES = array( 'organisation', 'individual', 'entrepreneur' );

	public const EXTERNAL_ID_KEYS = array( 'wikidata_qid', 'rc_number', 'licence_number', 'lei', 'official_domain' );

	public const CHECKS = array( 'approved_source', 'source_url_ok', 'exact_quotes', 'allowlisted_fields', 'sensitive_scan', 'suppression' );

	/**
	 * Order in which field refusals are reported.
	 */
	private const FIELD_CODE_ORDER = array( 'FORBIDDEN_FIELD', 'SCHEMA', 'UNSOURCED_FIELD', 'LOW_CONFIDENCE' );

	/**
	 * Field validator.
	 *
	 * @var FieldValidator
	 */
	private FieldValidator $fields;

	/**
	 * Constructor.
	 *
	 * @param FieldValidator|null $fields Field validator.
	 */
	public function __construct( ?FieldValidator $fields = null ) {
		$this->fields = $fields ?? new FieldValidator( new FieldAllowlist() );
	}

	/**
	 * Validate one line of records.jsonl.
	 *
	 * @param string $line The record as JSON text.
	 * @return array{ok: bool, code: string|null, record_id: string, field: string, record: array<string, mixed>|null}
	 *         code is one contract refusal code or null; field names the offending field when there is one (never a value);
	 *         record is the decoded record (arrays) when it is accepted.
	 */
	public function validate( string $line ): array {
		$object = json_decode( $line, false, 512, JSON_BIGINT_AS_STRING ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- objects and empty arrays must stay distinct for the hash.
		if ( ! $object instanceof \stdClass ) {
			return $this->refuse( 'SCHEMA', '', '' );
		}
		$record = json_decode( $line, true, 512, JSON_BIGINT_AS_STRING ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- decoding the same text as arrays.
		if ( ! is_array( $record ) ) {
			return $this->refuse( 'SCHEMA', '', '' );
		}
		$id = isset( $record['record_id'] ) && is_string( $record['record_id'] ) ? $record['record_id'] : '';

		$shape = $this->shape( $record );
		if ( null !== $shape ) {
			return $this->refuse( $shape[0], $id, $shape[1] );
		}
		if ( ! in_array( $record['contract_version'], BundleReader::ACCEPTED_VERSIONS, true ) ) {
			return $this->refuse( 'CONTRACT_VERSION', $id, 'contract_version' );
		}
		if ( array() !== $record['images'] ) {
			return $this->refuse( 'IMAGE_NOT_ALLOWED', $id, 'images' );
		}
		$refusals = $this->fields->validate( (string) $record['entity_type'], $record['fields'] );
		if ( array() !== $refusals ) {
			usort(
				$refusals,
				static fn ( array $a, array $b ): int => array_search( $a['code'], self::FIELD_CODE_ORDER, true ) <=> array_search( $b['code'], self::FIELD_CODE_ORDER, true )
			);
			return $this->refuse( (string) $refusals[0]['code'], $id, (string) $refusals[0]['field'] );
		}
		if ( ! $this->verification_passes( $record['verification'] ) ) {
			return $this->refuse( 'VERIFICATION_FAILED', $id, 'verification' );
		}
		if ( ! ContentHasher::matches( $object ) ) {
			return $this->refuse( 'HASH_MISMATCH', $id, 'content_hash' );
		}
		return array(
			'ok'        => true,
			'code'      => null,
			'record_id' => $id,
			'field'     => '',
			'record'    => $record,
		);
	}

	/**
	 * Whether the verification report has every required check passing and the duplicates check clear or flagged.
	 *
	 * @param mixed $verification Decoded verification object.
	 */
	public function verification_passes( $verification ): bool {
		if ( ! is_array( $verification ) || ! isset( $verification['checks'] ) || ! is_array( $verification['checks'] ) ) {
			return false;
		}
		foreach ( self::CHECKS as $check ) {
			if ( ( $verification['checks'][ $check ] ?? null ) !== 'pass' ) {
				return false;
			}
		}
		return in_array( $verification['checks']['duplicates'] ?? null, array( 'clear', 'flagged' ), true );
	}

	/**
	 * The shape of the record outside its fields.
	 *
	 * @param array<string, mixed> $record Record.
	 * @return array{0: string, 1: string}|null Code and field, or null when the shape is right.
	 */
	private function shape( array $record ): ?array {
		foreach ( array_keys( $record ) as $key ) {
			if ( ! in_array( $key, self::KEYS, true ) ) {
				return array( 'SCHEMA', (string) $key );
			}
		}
		foreach ( self::KEYS as $key ) {
			if ( ! array_key_exists( $key, $record ) ) {
				return array( 'SCHEMA', $key );
			}
		}
		$strings = array( 'contract_version', 'record_id', 'run_id', 'batch_id', 'content_hash' );
		foreach ( $strings as $key ) {
			if ( ! is_string( $record[ $key ] ) || '' === $record[ $key ] ) {
				return array( 'SCHEMA', $key );
			}
		}
		if ( ! is_int( $record['record_version'] ) || $record['record_version'] < 1 ) {
			return array( 'SCHEMA', 'record_version' );
		}
		if ( ! in_array( $record['entity_type'], self::ENTITY_TYPES, true ) ) {
			return array( 'SCHEMA', 'entity_type' );
		}
		if ( ! in_array( $record['tier'], array( 'T1', 'T2', 'T3' ), true ) ) {
			return array( 'SCHEMA', 'tier' );
		}
		if ( ! is_string( $record['country'] ) || 1 !== preg_match( '/^([A-Z]{2}|PAN)$/', $record['country'] ) ) {
			return array( 'SCHEMA', 'country' );
		}
		if ( ! is_array( $record['external_ids'] ) || ! $this->external_ids_ok( $record['external_ids'] ) ) {
			return array( 'SCHEMA', 'external_ids' );
		}
		$inclusion = $record['inclusion'];
		if ( ! is_array( $inclusion ) || array() !== array_diff( array_keys( $inclusion ), array( 'criterion_code', 'evidence_url' ) ) || ! is_string( $inclusion['criterion_code'] ?? null ) || ! is_string( $inclusion['evidence_url'] ?? null ) ) {
			return array( 'SCHEMA', 'inclusion' );
		}
		if ( ! is_array( $record['fields'] ) || array() === $record['fields'] ) {
			return array( 'SCHEMA', 'fields' );
		}
		foreach ( array( 'claims', 'passages', 'images' ) as $key ) {
			if ( ! is_array( $record[ $key ] ) || ( array() !== $record[ $key ] && array_values( $record[ $key ] ) !== $record[ $key ] ) ) {
				return array( 'SCHEMA', $key );
			}
		}
		$ai = $record['ai'];
		if ( ! is_array( $ai ) || ! array_key_exists( 'ai_assisted', $ai ) || ! is_bool( $ai['ai_assisted'] ) ) {
			return array( 'SCHEMA', 'ai' );
		}
		$flags = $record['flags'];
		if ( ! is_array( $flags ) || ! is_bool( $flags['probable_duplicate'] ?? null ) || ! is_array( $flags['duplicate_of'] ?? null ) || ! is_array( $flags['flag_reasons'] ?? null ) ) {
			return array( 'SCHEMA', 'flags' );
		}
		if ( ! is_array( $record['verification'] ) ) {
			return array( 'SCHEMA', 'verification' );
		}
		if ( 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', $record['content_hash'] ) ) {
			return array( 'SCHEMA', 'content_hash' );
		}
		return null;
	}

	/**
	 * Whether external ids use only known keys with string values.
	 *
	 * @param array<mixed> $ids External ids.
	 */
	private function external_ids_ok( array $ids ): bool {
		foreach ( $ids as $key => $value ) {
			if ( ! in_array( $key, self::EXTERNAL_ID_KEYS, true ) || ! is_string( $value ) || '' === $value ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A refusal result.
	 *
	 * @param string $code      Contract code.
	 * @param string $record_id Record id, when known.
	 * @param string $field     Offending field name, when there is one.
	 * @return array{ok: bool, code: string|null, record_id: string, field: string, record: null}
	 */
	private function refuse( string $code, string $record_id, string $field ): array {
		return array(
			'ok'        => false,
			'code'      => $code,
			'record_id' => $record_id,
			'field'     => $field,
			'record'    => null,
		);
	}
}
