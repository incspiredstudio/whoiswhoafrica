<?php
/**
 * The translations file (REQ-276): a JSON document listing, per profile, the expected current value and the English value of each
 * field to change, the original-language name to keep, and optionally list terms to rename. Parsing refuses anything that is not
 * exactly this shape, so the applier only ever sees checked data.
 *
 * Shape (format "wiw-translations", version 1):
 *   {"format": "wiw-translations", "version": 1,
 *    "items": [{"record_id": "wd:Q1", "profile_id": 12, "original_name": "…" or null, "original_lang": "ar",
 *               "fields": {"name": {"old": "…", "new": "…"}, "bio": {"old": "…", "new": "…"}}}],
 *    "terms": [{"taxonomy": "wiw_place", "term_id": 348, "old": "…", "new": "…"}]}
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Translate;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\Taxonomies;

/**
 * Translations file parser.
 */
final class TranslationFile {

	public const FORMAT    = 'wiw-translations';
	public const VERSION   = 1;
	public const MAX_BYTES = 2097152;
	public const MAX_ITEMS = 5000;

	/**
	 * Parse and check a file's text.
	 *
	 * @param string $json File contents.
	 * @return array{items: list<array{record_id: string, profile_id: int, original_name: string|null, original_lang: string, fields: array<string, array{old: string, new: string}>}>, terms: list<array{taxonomy: string, term_id: int, old: string, new: string}>}
	 * @throws \InvalidArgumentException With a message fit to show.
	 */
	public static function parse( string $json ): array {
		if ( strlen( $json ) > self::MAX_BYTES ) {
			throw new \InvalidArgumentException( 'The file is larger than 2 MB.' );
		}
		$doc = json_decode( $json, true );
		if ( ! is_array( $doc ) ) {
			throw new \InvalidArgumentException( 'The file is not valid JSON.' );
		}
		if ( self::FORMAT !== ( $doc['format'] ?? null ) || self::VERSION !== ( $doc['version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'This is not a translations file (format wiw-translations, version 1).' );
		}
		$items = $doc['items'] ?? null;
		$terms = $doc['terms'] ?? array();
		if ( ! is_array( $items ) || ! array_is_list( $items ) || ! is_array( $terms ) || ! array_is_list( $terms ) ) {
			throw new \InvalidArgumentException( 'The file needs a list of items (and, optionally, a list of terms).' );
		}
		if ( array() === $items && array() === $terms ) {
			throw new \InvalidArgumentException( 'The file lists nothing to translate.' );
		}
		if ( count( $items ) > self::MAX_ITEMS ) {
			throw new \InvalidArgumentException( 'The file lists more than 5,000 profiles.' );
		}
		$allow = new FieldAllowlist();
		$keys  = self::text_keys( $allow );
		$out   = array(
			'items' => array(),
			'terms' => array(),
		);
		foreach ( $items as $n => $item ) {
			$out['items'][] = self::item( $item, (int) $n + 1, $keys );
		}
		foreach ( $terms as $n => $term ) {
			$out['terms'][] = self::term( $term, (int) $n + 1 );
		}
		return $out;
	}

	/**
	 * The field keys a translation may change: allowlisted, not forbidden, and stored as text (lists and numbers are not
	 * translated).
	 *
	 * @param FieldAllowlist $allow Allowlist.
	 * @return string[]
	 */
	private static function text_keys( FieldAllowlist $allow ): array {
		$keys = array();
		foreach ( $allow->entity_types() as $type ) {
			foreach ( $allow->for_type( (string) $type ) as $key => $rule ) {
				if ( in_array( $rule['type'] ?? '', array( 'string', 'url' ), true ) && ! $allow->is_forbidden( (string) $key ) ) {
					$keys[] = (string) $key;
				}
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * One item.
	 *
	 * @param mixed    $item Raw item.
	 * @param int      $n    Position, for the message.
	 * @param string[] $keys Field keys allowed.
	 * @return array{record_id: string, profile_id: int, original_name: string|null, original_lang: string, fields: array<string, array{old: string, new: string}>}
	 * @throws \InvalidArgumentException With a message fit to show.
	 */
	private static function item( $item, int $n, array $keys ): array {
		$bad = static function ( string $why ) use ( $n ): \InvalidArgumentException {
			return new \InvalidArgumentException( sprintf( 'Item %d: %s', $n, $why ) );
		};
		if ( ! is_array( $item ) ) {
			throw $bad( 'not an object.' );
		}
		$record  = $item['record_id'] ?? '';
		$profile = $item['profile_id'] ?? 0;
		if ( ! is_string( $record ) || ! is_int( $profile ) || ( '' === trim( $record ) && $profile < 1 ) ) {
			throw $bad( 'needs a record_id or a profile_id.' );
		}
		$original = $item['original_name'] ?? null;
		$lang     = $item['original_lang'] ?? '';
		if ( null !== $original && ( ! is_string( $original ) || '' === trim( $original ) || mb_strlen( $original ) > OriginalName::MAX ) ) {
			throw $bad( 'original_name must be a name or null.' );
		}
		if ( null !== $original && ( ! is_string( $lang ) || ! OriginalName::valid_lang( $lang ) ) ) {
			throw $bad( 'original_lang must be a language code such as ar.' );
		}
		$fields = $item['fields'] ?? null;
		if ( ! is_array( $fields ) || array() === $fields || array_is_list( $fields ) ) {
			throw $bad( 'fields must name at least one field.' );
		}
		$clean = array();
		foreach ( $fields as $key => $change ) {
			$key = (string) $key;
			if ( ! in_array( $key, $keys, true ) ) {
				throw $bad( sprintf( 'the field %s cannot be translated.', esc_html( $key ) ) );
			}
			if ( ! is_array( $change ) || ! is_string( $change['old'] ?? null ) || ! is_string( $change['new'] ?? null ) ) {
				throw $bad( sprintf( 'the field %s needs an old and a new value.', esc_html( $key ) ) );
			}
			if ( 'name' === $key && '' === trim( $change['new'] ) ) {
				throw $bad( 'the new name is empty.' );
			}
			$clean[ $key ] = array(
				'old' => $change['old'],
				'new' => $change['new'],
			);
		}
		return array(
			'record_id'     => trim( $record ),
			'profile_id'    => $profile,
			'original_name' => null === $original ? null : trim( $original ),
			'original_lang' => is_string( $lang ) ? $lang : '',
			'fields'        => $clean,
		);
	}

	/**
	 * One term rename.
	 *
	 * @param mixed $term Raw term.
	 * @param int   $n    Position.
	 * @return array{taxonomy: string, term_id: int, old: string, new: string}
	 * @throws \InvalidArgumentException With a message fit to show.
	 */
	private static function term( $term, int $n ): array {
		if ( ! is_array( $term ) || ! is_string( $term['taxonomy'] ?? null ) || ! in_array( $term['taxonomy'], Taxonomies::all(), true ) ) {
			throw new \InvalidArgumentException( esc_html( sprintf( 'Term %d: the list is not one of the directory lists.', $n ) ) );
		}
		if ( ! is_int( $term['term_id'] ?? null ) || $term['term_id'] < 1 || ! is_string( $term['old'] ?? null ) || ! is_string( $term['new'] ?? null ) || '' === trim( $term['new'] ) ) {
			throw new \InvalidArgumentException( esc_html( sprintf( 'Term %d: needs a term_id, an old and a new name.', $n ) ) );
		}
		return array(
			'taxonomy' => $term['taxonomy'],
			'term_id'  => $term['term_id'],
			'old'      => $term['old'],
			'new'      => trim( $term['new'] ),
		);
	}
}
