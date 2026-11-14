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
 * An unknown head office city is added on import as a city under the record's primary country (never under Pan-African, never
 * a value that is a country name or code). Only a city with no country to file it under is listed in the dry run for mapping.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Mapping;

use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\IsoCountries;
use WhoIsWho\Core\Directory\PlaceSeeder;
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
	 * Head office cities in a record that the import cannot place, for the dry run's mapping step: a value with no place term
	 * and no saved mapping whose record has no country to file a new city under (none, or Pan-African). A city the import will
	 * add under the record's country, and a value that is a country name or code, are not listed. Never a reason to refuse.
	 *
	 * @param array<string, mixed> $record A contract record.
	 * @return array<int, array{field: string, value: string}>
	 */
	public function unmapped( array $record ): array {
		$out = array();
		foreach ( array_keys( self::LIST_FIELDS ) as $field ) {
			$value = self::value_of( $record, $field );
			if ( '' === $value || null !== $this->resolve( $field, $value ) || self::is_country_value( $value ) || null !== $this->country_for( $record ) ) {
				continue;
			}
			$out[] = array(
				'field' => $field,
				'value' => $value,
			);
		}
		return $out;
	}

	/**
	 * The city term for a record's head office city, created when missing (REQ-009): a saved mapping first, then a city of that
	 * name under the record's primary country, else a new city term (kind city) under that country, with a clean name. Null when
	 * there is no city, the value is a country name or code, the mapping says skip, or the record has no country to file it
	 * under (none, an unknown code, or Pan-African). Only the apply step calls this; the dry run writes nothing.
	 *
	 * @param array<string, mixed> $record A validated contract record.
	 */
	public function city_for( array $record ): ?int {
		$value = self::value_of( $record, 'hq_city' );
		if ( '' === $value || self::is_country_value( $value ) ) {
			return null;
		}
		$saved = $this->saved();
		$key   = Taxonomies::PLACE . '|' . self::normalise( $value );
		if ( array_key_exists( $key, $saved ) ) {
			$id = (int) $saved[ $key ];
			return $id > 0 && term_exists( $id, Taxonomies::PLACE ) ? $id : null;
		}
		$country = $this->country_for( $record );
		if ( null === $country ) {
			return null;
		}
		$name     = self::city_name( $value );
		$children = get_terms(
			array(
				'taxonomy'   => Taxonomies::PLACE,
				'hide_empty' => false,
				'parent'     => $country,
			)
		);
		foreach ( is_array( $children ) ? $children : array() as $term ) {
			if ( $term instanceof \WP_Term && self::normalise( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ) === self::normalise( $name ) ) {
				return (int) $term->term_id;
			}
		}
		$made = wp_insert_term( mb_substr( $name, 0, 190 ), Taxonomies::PLACE, array( 'parent' => $country ) );
		if ( is_wp_error( $made ) ) {
			return null;
		}
		update_term_meta( (int) $made['term_id'], Taxonomies::META_KIND, Taxonomies::KIND_CITY );
		return (int) $made['term_id'];
	}

	/**
	 * The country term a new city of this record is filed under: its primary country (contract 1.2), else its country; never the
	 * Pan-African body. A real ISO code the list lacks creates the country, as the classification does.
	 *
	 * @param array<string, mixed> $record Record.
	 */
	private function country_for( array $record ): ?int {
		$code = $record['classification']['primary_country'] ?? ( $record['country'] ?? '' );
		$code = is_string( $code ) ? strtoupper( trim( $code ) ) : '';
		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $code ) ) {
			return null;
		}
		return Classification::ensure_country( $code );
	}

	/**
	 * Whether a value is a country rather than a city: an ISO alpha-2 code, PAN, a country name from the ISO list, or the name of
	 * a top-level place.
	 *
	 * @param string $value Raw value.
	 */
	private static function is_country_value( string $value ): bool {
		$trimmed = trim( $value );
		if ( 1 === preg_match( '/^(PAN|[A-Za-z]{2})$/', $trimmed ) && ( 'PAN' === strtoupper( $trimmed ) || null !== IsoCountries::name( $trimmed ) ) ) {
			return true;
		}
		$key = self::normalise( $trimmed );
		foreach ( IsoCountries::all() + PlaceSeeder::countries() as $name ) {
			if ( self::normalise( $name ) === $key ) {
				return true;
			}
		}
		$top = get_terms(
			array(
				'taxonomy'   => Taxonomies::PLACE,
				'hide_empty' => false,
				'parent'     => 0,
				'fields'     => 'names',
			)
		);
		foreach ( is_array( $top ) ? $top : array() as $name ) {
			if ( self::normalise( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) ) === $key ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A city name as stored: trimmed, single spaces, and title case only when the value is all lower or all upper case.
	 *
	 * @param string $value Raw value.
	 */
	public static function city_name( string $value ): string {
		$name = trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
		if ( mb_strtolower( $name ) === $name || mb_strtoupper( $name ) === $name ) {
			$name = mb_convert_case( mb_strtolower( $name ), MB_CASE_TITLE, 'UTF-8' );
		}
		return $name;
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
