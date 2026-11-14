<?php
/**
 * Maps the controlled-list values of an import record to the site's terms (REQ-009 as amended 2026-11-14, REQ-212).
 *
 * Classification (industry, organisation type, primary country) never refuses a record any more. A contract 1.2 record's
 * `classification` object wins: its primary industry, extra industries and primary country are used as they are when the tree
 * knows them. Without it (contract 1.0 and 1.1) the raw `industry`, then the person's `field`, map to an industry leaf through the
 * tree's slugs, names and aliases (or a mapping an Administrator saved), else to Unclassified; `org_type` maps the same way, else
 * to "other"; the primary country is the record's `country`.
 *
 * The head office city still uses the mapping list: a value with no place term and no saved mapping is skipped (not refused) and
 * listed in the dry run, where staff can add it as a new place or map it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Mapping;

use WhoIsWho\Core\Directory\Classification;
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
	 * Record field => taxonomy, for the values a saved mapping may resolve.
	 */
	public const FIELDS = array(
		'org_type' => Taxonomies::ORG_TYPE,
		'industry' => Taxonomies::INDUSTRY,
		'sector'   => Taxonomies::SECTOR,
		'hq_city'  => Taxonomies::PLACE,
	);

	/**
	 * Fields that still need a list entry or a mapping: only the head office city. Industry, sector and organisation type are
	 * classified instead (classification_for()).
	 */
	public const LIST_FIELDS = array(
		'hq_city' => Taxonomies::PLACE,
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
	 * Place values in a record that need a list entry or a mapping, for the dry run. Never a reason to refuse the record.
	 *
	 * @param array<string, mixed> $record A contract record.
	 * @return array<int, array{field: string, value: string, country?: string}> country is the record's primary country code, so
	 *         "Add all as new places" can file a new city under it.
	 */
	public function unmapped( array $record ): array {
		$out     = array();
		$country = $record['classification']['primary_country'] ?? ( $record['country'] ?? '' );
		foreach ( array_keys( self::LIST_FIELDS ) as $field ) {
			$value = self::value_of( $record, $field );
			if ( '' !== $value && null === $this->resolve( $field, $value ) ) {
				$entry = array(
					'field' => $field,
					'value' => $value,
				);
				if ( is_string( $country ) && 1 === preg_match( '/^[A-Z]{2}$/', $country ) ) {
					$entry['country'] = $country;
				}
				$out[] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Place terms to attach to a profile: field => term id, resolved values only. An unmapped or skipped value is not in the result,
	 * so it is never written.
	 *
	 * @param array<string, mixed> $record A contract record.
	 * @return array<string, int>
	 */
	public function terms_for( array $record ): array {
		$out = array();
		foreach ( array_keys( self::LIST_FIELDS ) as $field ) {
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
	 * The classification of a record, ready for ProfileClassifier::assign(): primary_industry and industries (leaf slugs, or
	 * Unclassified), country (ISO alpha-2 or PAN), org_type (slug; organisations and institutions only) and provenance.
	 *
	 * @param array<string, mixed> $record A validated contract record.
	 * @return array{primary_industry: string, industries: string[], country: string, org_type: string, provenance: array<string, mixed>}
	 */
	public function classification_for( array $record ): array {
		$given      = isset( $record['classification'] ) && is_array( $record['classification'] ) ? $record['classification'] : null;
		$primary    = null;
		$industries = array();
		$country    = is_string( $record['country'] ?? null ) ? (string) $record['country'] : '';
		$provenance = array(
			'method'         => 'alias',
			'model'          => null,
			'prompt_version' => null,
			'confidence'     => 'medium',
		);
		if ( null !== $given ) {
			$primary = $this->leaf( is_string( $given['primary_industry'] ?? null ) ? (string) $given['primary_industry'] : '' );
			foreach ( (array) ( $given['industries'] ?? array() ) as $extra ) {
				$leaf = $this->leaf( is_string( $extra ) ? $extra : '' );
				if ( null !== $leaf && Classification::unclassified_slug() !== $leaf && $leaf !== $primary && ! in_array( $leaf, $industries, true ) ) {
					$industries[] = $leaf;
				}
			}
			if ( is_string( $given['primary_country'] ?? null ) && '' !== $given['primary_country'] ) {
				$country = (string) $given['primary_country'];
			}
			$prov = isset( $given['provenance'] ) && is_array( $given['provenance'] ) ? $given['provenance'] : array();
			foreach ( array_keys( $provenance ) as $key ) {
				if ( array_key_exists( $key, $prov ) && ( null === $prov[ $key ] || is_string( $prov[ $key ] ) ) ) {
					$provenance[ $key ] = $prov[ $key ];
				}
			}
		}
		if ( null === $primary ) {
			$primary = $this->industry_from_raw( self::value_of( $record, 'industry' ) ) ?? $this->industry_from_raw( self::value_of( $record, 'field' ) );
			if ( null === $given ) {
				$provenance['confidence'] = null === $primary ? 'low' : 'medium';
			}
		}
		$org_type = '';
		if ( in_array( (string) ( $record['entity_type'] ?? '' ), array( 'organisation', 'institution' ), true ) ) {
			$org_type = $this->org_type_from_raw( self::value_of( $record, 'org_type' ) ) ?? 'other';
		}
		return array(
			'primary_industry' => $primary ?? Classification::unclassified_slug(),
			'industries'       => $industries,
			'country'          => $country,
			'org_type'         => $org_type,
			'provenance'       => $provenance,
		);
	}

	/**
	 * An industry leaf slug the tree knows (config or a staff-added leaf), Unclassified itself, or null.
	 *
	 * @param string $slug Slug from a contract 1.2 record.
	 */
	private function leaf( string $slug ): ?string {
		if ( '' === $slug ) {
			return null;
		}
		if ( Classification::unclassified_slug() === $slug || Classification::is_industry( $slug ) ) {
			return $slug;
		}
		$term = taxonomy_exists( Taxonomies::INDUSTRY ) ? get_term_by( 'slug', $slug, Taxonomies::INDUSTRY ) : false;
		return $term instanceof \WP_Term && 0 !== (int) $term->parent ? $slug : null;
	}

	/**
	 * The industry leaf for a raw value: a saved mapping first, then the tree's slugs, names and aliases.
	 *
	 * @param string $value Raw value.
	 */
	private function industry_from_raw( string $value ): ?string {
		if ( '' === $value ) {
			return null;
		}
		$saved = $this->saved()[ Taxonomies::INDUSTRY . '|' . self::normalise( $value ) ] ?? null;
		if ( null !== $saved && (int) $saved > 0 ) {
			$term = get_term( (int) $saved, Taxonomies::INDUSTRY );
			if ( $term instanceof \WP_Term && null !== $this->leaf( $term->slug ) ) {
				return $term->slug;
			}
		}
		return Classification::match_industry( $value );
	}

	/**
	 * The organisation type slug for a raw value: a saved mapping first, then the slugs, names and aliases.
	 *
	 * @param string $value Raw value.
	 */
	private function org_type_from_raw( string $value ): ?string {
		if ( '' === $value ) {
			return null;
		}
		$saved = $this->saved()[ Taxonomies::ORG_TYPE . '|' . self::normalise( $value ) ] ?? null;
		if ( null !== $saved && (int) $saved > 0 ) {
			$term = get_term( (int) $saved, Taxonomies::ORG_TYPE );
			if ( $term instanceof \WP_Term ) {
				return $term->slug;
			}
		}
		return Classification::match_org_type( $value );
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
	 * Add unknown place values as new entries of the place list, in one go (a no-code alternative to mapping hundreds of values
	 * one by one). Industries, sectors and organisation types are never added this way: the tree is fixed and staff add to it on
	 * the term screens. A value that has meanwhile been mapped or added is left alone. One audit row carries the count.
	 *
	 * @param array<int, array{field: string, value: string, country?: string}> $terms Unknown values from the dry run.
	 * @return int Entries added.
	 */
	public function create_missing( array $terms ): int {
		$added = 0;
		foreach ( $terms as $term ) {
			$taxonomy = self::LIST_FIELDS[ $term['field'] ] ?? null;
			$value    = trim( (string) $term['value'] );
			if ( null === $taxonomy || '' === $value || ! taxonomy_exists( $taxonomy ) || null !== $this->resolve( $term['field'], $value ) ) {
				continue;
			}
			// A new city is filed under the record's country, as a city. A value that is a country's own name is not a city.
			$parent = isset( $term['country'] ) ? Classification::ensure_country( (string) $term['country'] ) : null;
			if ( null !== $parent ) {
				$country = get_term( $parent, $taxonomy );
				if ( $country instanceof \WP_Term && self::normalise( html_entity_decode( $country->name, ENT_QUOTES, 'UTF-8' ) ) === self::normalise( $value ) ) {
					continue;
				}
			}
			$made = wp_insert_term( mb_substr( $value, 0, 190 ), $taxonomy, array( 'parent' => $parent ?? 0 ) );
			if ( ! is_wp_error( $made ) ) {
				if ( null !== $parent ) {
					update_term_meta( (int) $made['term_id'], Taxonomies::META_KIND, Taxonomies::KIND_CITY );
				}
				++$added;
			}
		}//end foreach
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
				'meta_key'   => self::ALIAS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small controlled list.
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term_id ) {
			foreach ( Classification::aliases_of( (int) $term_id ) as $alias ) {
				if ( self::normalise( $alias ) === $key ) {
					return (int) $term_id;
				}
			}
		}
		return null;
	}
}
