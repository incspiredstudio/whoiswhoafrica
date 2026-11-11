<?php
/**
 * The content hash of an import record (contract section 8, REQ-221): take only the fields marked "In hash", normalise strings
 * to Unicode NFC, serialise with the JSON Canonicalization Scheme (RFC 8785), SHA-256 of the UTF-8 bytes, prefix "sha256:".
 * Plain PHP, no WordPress calls. Numbers: integers and booleans are exact; a non-integral float is written with PHP's shortest
 * round-trip form, which matches RFC 8785 for ordinary values (the contract has no floats today).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import;

/**
 * Content hasher.
 */
final class ContentHasher {

	/**
	 * Record fields marked "In hash" in contract section 3.
	 */
	public const HASHED_FIELDS = array(
		'record_id',
		'entity_type',
		'tier',
		'country',
		'external_ids',
		'inclusion',
		'fields',
		'claims',
		'passages',
		'images',
		'ai',
	);

	/**
	 * Hash a record given as a JSON string (one line of records.jsonl).
	 *
	 * @param string $json Record JSON.
	 * @return string "sha256:" and 64 hex characters.
	 * @throws \InvalidArgumentException When the text is not a JSON object.
	 */
	public static function hash_json( string $json ): string {
		$record = json_decode( $json, false, 512, JSON_BIGINT_AS_STRING ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP on purpose: objects and empty arrays must stay distinct.
		if ( ! $record instanceof \stdClass ) {
			throw new \InvalidArgumentException( 'A record must be a JSON object.' );
		}
		return self::hash_record( $record );
	}

	/**
	 * Hash a decoded record (stdClass from json_decode without the assoc flag, so empty objects stay objects).
	 *
	 * @param \stdClass $record Record.
	 * @return string "sha256:" and 64 hex characters.
	 */
	public static function hash_record( \stdClass $record ): string {
		return 'sha256:' . hash( 'sha256', self::canonical_hashed_part( $record ) );
	}

	/**
	 * The canonical text that is hashed.
	 *
	 * @param \stdClass $record Record.
	 */
	public static function canonical_hashed_part( \stdClass $record ): string {
		$subset = new \stdClass();
		foreach ( self::HASHED_FIELDS as $name ) {
			if ( property_exists( $record, $name ) ) {
				$subset->{$name} = $record->{$name};
			}
		}
		return self::canonicalize( $subset );
	}

	/**
	 * Whether a record's own content_hash matches the recomputed one.
	 *
	 * @param \stdClass $record Record carrying content_hash.
	 */
	public static function matches( \stdClass $record ): bool {
		return isset( $record->content_hash ) && is_string( $record->content_hash ) && hash_equals( self::hash_record( $record ), $record->content_hash );
	}

	/**
	 * RFC 8785 serialisation of a decoded value, with strings (and keys) normalised to NFC.
	 *
	 * @param mixed $value Value.
	 * @throws \InvalidArgumentException For a value JSON cannot hold.
	 */
	public static function canonicalize( $value ): string {
		if ( null === $value ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) ) {
			return (string) $value;
		}
		if ( is_float( $value ) ) {
			if ( ! is_finite( $value ) ) {
				throw new \InvalidArgumentException( 'Non-finite number.' );
			}
			if ( floor( $value ) === $value && abs( $value ) < 1e21 ) {
				return sprintf( '%.0f', $value );
			}
			return (string) json_encode( $value, JSON_PRESERVE_ZERO_FRACTION ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP on purpose.
		}
		if ( is_string( $value ) ) {
			return self::string( $value );
		}
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( $value as $item ) {
				$parts[] = self::canonicalize( $item );
			}
			return '[' . implode( ',', $parts ) . ']';
		}
		if ( is_object( $value ) ) {
			$members = array();
			foreach ( get_object_vars( $value ) as $key => $item ) {
				$members[ self::normalise( (string) $key ) ] = $item;
			}
			uksort( $members, array( self::class, 'compare_utf16' ) );
			$parts = array();
			foreach ( $members as $key => $item ) {
				$parts[] = self::string( (string) $key ) . ':' . self::canonicalize( $item );
			}
			return '{' . implode( ',', $parts ) . '}';
		}
		throw new \InvalidArgumentException( 'Unsupported value.' );
	}

	/**
	 * Compare two keys by UTF-16 code units, as RFC 8785 requires.
	 *
	 * @param string $a Key.
	 * @param string $b Key.
	 */
	public static function compare_utf16( string $a, string $b ): int {
		$ua = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $a, 'UTF-16BE', 'UTF-8' ) : $a;
		$ub = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $b, 'UTF-16BE', 'UTF-8' ) : $b;
		return strcmp( (string) $ua, (string) $ub );
	}

	/**
	 * NFC-normalise a string.
	 *
	 * @param string $text Text.
	 */
	public static function normalise( string $text ): string {
		if ( class_exists( '\Normalizer' ) ) {
			$normal = \Normalizer::normalize( $text, \Normalizer::FORM_C );
			if ( is_string( $normal ) ) {
				return $normal;
			}
		}
		return $text;
	}

	/**
	 * One JSON string, NFC-normalised, escaped the RFC 8785 way: only quote, backslash and control characters are escaped.
	 *
	 * @param string $text Text.
	 * @throws \InvalidArgumentException When the text is not valid UTF-8.
	 */
	private static function string( string $text ): string {
		$encoded = json_encode( self::normalise( $text ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP on purpose.
		if ( false === $encoded ) {
			throw new \InvalidArgumentException( 'A string is not valid UTF-8.' );
		}
		return $encoded;
	}
}
