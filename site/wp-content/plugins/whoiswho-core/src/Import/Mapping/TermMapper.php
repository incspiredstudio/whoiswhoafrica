<?php
/**
 * Maps the controlled-list values of an import record (organisation type, industry, sector, city) to the site's terms (REQ-009,
 * REQ-212). A value is known when a term has that slug, name or alias; otherwise it needs a mapping that an Administrator saves
 * (to a term, or to "skip"). A saved mapping is reused by every later import. A value with no term and no mapping is never
 * written to a profile, and a record holding one is refused at apply.
 *
 * The site owns the controlled lists (contract unknown U5); the pipeline learns them through these mappings.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Mapping;

use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Term mapper.
 */
final class TermMapper {

	public const OPTION     = 'wiw_import_term_map';
	public const ALIAS_META = 'wiw_alias';
	public const SKIP       = 0;

	/**
	 * Record field => taxonomy.
	 */
	public const FIELDS = array(
		'org_type' => Taxonomies::ORG_TYPE,
		'industry' => Taxonomies::INDUSTRY,
		'sector'   => Taxonomies::SECTOR,
		'hq_city'  => Taxonomies::PLACE,
	);

	/**
	 * The term id a value resolves to (a positive id), SKIP for a value an Administrator chose to skip, or null when the value
	 * needs a mapping.
	 *
	 * @param string $field Record field name.
	 * @param string $value The value in the record.
	 */
	public function resolve( string $field, string $value ): ?int {
		$taxonomy = self::FIELDS[ $field ] ?? null;
		$key      = self::normalise( $value );
		if ( null === $taxonomy || '' === $key || ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}
		$saved = $this->saved();
		if ( array_key_exists( $taxonomy . '|' . $key, $saved ) ) {
			$id = (int) $saved[ $taxonomy . '|' . $key ];
			return self::SKIP === $id || term_exists( $id, $taxonomy ) ? $id : null;
		}
		return $this->find( $taxonomy, $value, $key );
	}

	/**
	 * Controlled values in a record that need a mapping, for the dry run.
	 *
	 * @param array<string, mixed> $record A contract record.
	 * @return array<int, array{field: string, value: string}>
	 */
	public function unmapped( array $record ): array {
		$out = array();
		foreach ( array_keys( self::FIELDS ) as $field ) {
			$value = self::value_of( $record, $field );
			if ( '' !== $value && null === $this->resolve( $field, $value ) ) {
				$out[] = array(
					'field' => $field,
					'value' => $value,
				);
			}
		}
		return $out;
	}

	/**
	 * Terms to attach to a profile: field => term id, resolved values only. An unmapped or skipped value is not in the result, so
	 * it is never written.
	 *
	 * @param array<string, mixed> $record A contract record.
	 * @return array<string, int>
	 */
	public function terms_for( array $record ): array {
		$out = array();
		foreach ( array_keys( self::FIELDS ) as $field ) {
			$value = self::value_of( $record, $field );
			if ( '' === $value ) {
				continue;
			}
			$id = $this->resolve( $field, $value );
			if ( null !== $id && $id > 0 ) {
				$out[ $field ] = $id;
			}
		}
		return $out;
	}

	/**
	 * Refuse a record that holds an unmapped controlled value.
	 *
	 * @param array<string, mixed> $record A contract record.
	 * @throws \RuntimeException When a value needs a mapping.
	 */
	public function assert_mapped( array $record ): void {
		if ( array() !== $this->unmapped( $record ) ) {
			throw new \RuntimeException( 'The record has a controlled value that needs a mapping.' );
		}
	}

	/**
	 * Save a mapping: a term id, or SKIP. It is used by this and every later import.
	 *
	 * @param string $field   Record field name.
	 * @param string $value   The value as it appears in records.
	 * @param int    $term_id Term id, or SKIP.
	 * @throws \InvalidArgumentException For an unknown field or a term not in that field's list.
	 */
	public function save( string $field, string $value, int $term_id ): void {
		$taxonomy = self::FIELDS[ $field ] ?? null;
		$key      = self::normalise( $value );
		if ( null === $taxonomy || '' === $key ) {
			throw new \InvalidArgumentException( 'Unknown field or empty value.' );
		}
		if ( self::SKIP !== $term_id && ! term_exists( $term_id, $taxonomy ) ) {
			throw new \InvalidArgumentException( 'That term is not in the list.' );
		}
		$saved                           = $this->saved();
		$saved[ $taxonomy . '|' . $key ] = $term_id;
		update_option( self::OPTION, $saved, false );
		AuditLogger::for_site()->log(
			'import.term_mapping',
			array(
				'type' => 'taxonomy',
				'id'   => $taxonomy,
			),
			null,
			array(
				'term_id' => $term_id,
				'key'     => $key,
			)
		);
	}

	/**
	 * Add unknown values as new entries of their lists, in one go (a no-code alternative to mapping hundreds of values one by
	 * one). A value that has meanwhile been mapped or added is left alone. One audit row carries the count.
	 *
	 * @param array<int, array{field: string, value: string}> $terms Unknown values from the dry run.
	 * @return int Entries added.
	 */
	public function create_missing( array $terms ): int {
		$added = 0;
		foreach ( $terms as $term ) {
			$taxonomy = self::FIELDS[ $term['field'] ] ?? null;
			$value    = trim( (string) $term['value'] );
			if ( null === $taxonomy || '' === $value || ! taxonomy_exists( $taxonomy ) || null !== $this->resolve( $term['field'], $value ) ) {
				continue;
			}
			$made = wp_insert_term( mb_substr( $value, 0, 190 ), $taxonomy );
			if ( ! is_wp_error( $made ) ) {
				++$added;
			}
		}
		if ( $added > 0 ) {
			AuditLogger::for_site()->log(
				'import.term_mapping',
				array(
					'type' => 'taxonomy',
					'id'   => 'bulk',
				),
				null,
				array( 'added' => $added )
			);
		}
		return $added;
	}

	/**
	 * A value as the key mappings use: lower case, single spaces.
	 *
	 * @param string $value Value.
	 */
	public static function normalise( string $value ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( $value ) ) );
	}

	/**
	 * The value of a field in a record, or an empty string.
	 *
	 * @param array<string, mixed> $record Record.
	 * @param string               $field  Field.
	 */
	private static function value_of( array $record, string $field ): string {
		$value = $record['fields'][ $field ]['value'] ?? '';
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Saved mappings.
	 *
	 * @return array<string, int>
	 */
	private function saved(): array {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Find a term by slug, name or alias.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $value    Raw value.
	 * @param string $key      Normalised value.
	 */
	private function find( string $taxonomy, string $value, string $key ): ?int {
		$term = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
		if ( ! $term instanceof \WP_Term ) {
			$term = get_term_by( 'name', trim( $value ), $taxonomy );
		}
		if ( $term instanceof \WP_Term ) {
			return $term->term_id;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small controlled list.
					array(
						'key'     => self::ALIAS_META,
						'value'   => $key,
						'compare' => '=',
					),
				),
			)
		);
		return is_array( $terms ) && array() !== $terms ? (int) $terms[0] : null;
	}
}
