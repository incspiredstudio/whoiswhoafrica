<?php
/**
 * Repairs profile list fields that an earlier release stored without their backslashes (issue 806, REQ-139, REQ-143).
 *
 * Until 0.2.0 a list field was stored as JSON without wp_slash, and WordPress strips one level of backslashes on save. So "é"
 * (JSON "é") was stored as "u00e9", and an inner quote (JSON "\"") became a bare quote, which made the JSON invalid and
 * emptied the list on screen. A stored value is repaired only when that damage is certain and the repaired text is a valid JSON
 * list of strings: every backslash that survived is doubled back (it was one of a pair), a backslash goes back before each
 * "uXXXX" that names a printable non-ASCII character, and each inner quote is escaped again. A value that cannot be repaired
 * safely is left as it is and counted. Values are never logged, only counts.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Fields;

use WhoIsWho\Core\Directory\ProfileType;

/**
 * List meta repair.
 */
final class ListMetaRepair {

	public const OK            = 'ok';
	public const REPAIRED      = 'repaired';
	public const UNRECOVERABLE = 'unrecoverable';

	/**
	 * A "uXXXX" whose backslash was stripped: a surrogate pair, or one code unit. Not preceded by a backslash.
	 */
	private const BARE_ESCAPE = '/(?<!\\\\)u(d[89ab][0-9a-f]{2})u(d[c-f][0-9a-f]{2})|(?<!\\\\)u([0-9a-f]{4})/i';

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
	 * The meta keys of every list field.
	 *
	 * @return string[]
	 */
	public function list_meta_keys(): array {
		$keys = array();
		foreach ( $this->allowlist->entity_types() as $type ) {
			foreach ( $this->allowlist->for_type( $type ) as $key => $rule ) {
				if ( 'list' === ( $rule['type'] ?? '' ) ) {
					$keys[] = (string) FieldAllowlist::meta_key( (string) $key );
				}
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Check every stored profile list value and repair the damaged ones.
	 *
	 * @return array{checked: int, repaired: int, unrecoverable: int}
	 */
	public function run(): array {
		global $wpdb;
		$counts = array(
			'checked'       => 0,
			'repaired'      => 0,
			'unrecoverable' => 0,
		);
		$keys = $this->list_meta_keys();
		if ( array() === $keys ) {
			return $counts;
		}
		$holders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$rows    = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the placeholders are built from the key count.
				"SELECT m.meta_id, m.post_id, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = %s AND m.meta_key IN ($holders)",
				array_merge( array( ProfileType::POST_TYPE ), $keys )
			),
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			++$counts['checked'];
			$result = self::repair( (string) $row['meta_value'] );
			if ( self::UNRECOVERABLE === $result['status'] ) {
				++$counts['unrecoverable'];
			} elseif ( self::REPAIRED === $result['status'] ) {
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $result['value'] ), array( 'meta_id' => (int) $row['meta_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one row by its id.
				wp_cache_delete( (int) $row['post_id'], 'post_meta' );
				++$counts['repaired'];
			}
		}
		return $counts;
	}

	/**
	 * Repair one stored list value.
	 *
	 * @param string $raw The stored text.
	 * @return array{status: string, value: string} The status, and the text to store (unchanged unless repaired).
	 */
	public static function repair( string $raw ): array {
		$healthy = self::as_list( $raw );
		if ( '' === trim( $raw ) || ( null !== $healthy && ! self::has_bare_escape( $raw ) ) ) {
			return array(
				'status' => self::OK,
				'value'  => $raw,
			);
		}
		$unrecoverable = array(
			'status' => self::UNRECOVERABLE,
			'value'  => $raw,
		);
		if ( 1 !== preg_match( '/^\["(.*)"\]$/s', $raw, $match ) ) {
			return $unrecoverable;
		}
		// Each backslash still there was one of a pair, so it is doubled back first.
		$inner = str_replace( '\\', '\\\\', $match[1] );
		$inner = (string) preg_replace_callback( self::BARE_ESCAPE, array( self::class, 'restore_escape' ), $inner );
		$parts = explode( '","', $inner );
		foreach ( $parts as $i => $part ) {
			$parts[ $i ] = str_replace( '"', '\\"', $part );
		}
		$list = self::as_list( '["' . implode( '","', $parts ) . '"]' );
		if ( null === $list ) {
			return $unrecoverable;
		}
		return array(
			'status' => self::REPAIRED,
			'value'  => ProfileFields::encode( $list ),
		);
	}

	/**
	 * Whether a stored value holds a "uXXXX" that lost its backslash and names printable text.
	 *
	 * @param string $raw The stored text.
	 */
	public static function has_bare_escape( string $raw ): bool {
		if ( ! preg_match_all( self::BARE_ESCAPE, $raw, $found, PREG_SET_ORDER ) ) {
			return false;
		}
		foreach ( $found as $match ) {
			if ( self::restore_escape( $match ) !== $match[0] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The escape with its backslash back, or the text unchanged when it does not name a printable non-ASCII character.
	 *
	 * @param string[] $m Regex match.
	 */
	private static function restore_escape( array $m ): string {
		if ( isset( $m[3] ) && '' !== $m[3] ) {
			$code = (int) hexdec( $m[3] );
			// Below U+00A0 is ASCII or a control character (never escaped as text); a lone surrogate is not text either.
			return $code < 0xA0 || ( $code >= 0xD800 && $code <= 0xDFFF ) ? $m[0] : '\\u' . $m[3];
		}
		return '\\u' . $m[1] . '\\u' . $m[2];
	}

	/**
	 * The decoded list of strings, or null when the text is not one.
	 *
	 * @param string $json Text.
	 * @return string[]|null
	 */
	private static function as_list( string $json ): ?array {
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) || ! array_is_list( $decoded ) ) {
			return null;
		}
		foreach ( $decoded as $item ) {
			if ( ! is_string( $item ) ) {
				return null;
			}
		}
		return $decoded;
	}
}
