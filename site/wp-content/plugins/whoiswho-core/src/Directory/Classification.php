<?php
/**
 * The classification tree as the code reads it (REQ-009 as amended 2026-11-14): the config in `config/classification.php`, plus
 * the live terms staff add or rename on the term screens. It answers "which sector holds this industry", "which industry or
 * organisation type does this raw value mean" (slug, name or alias, case-insensitive, "&" read as "and") and "which term is this
 * slug". Matching never guesses: a value that matches nothing returns null, and the caller uses Unclassified (industry) or
 * "other" (organisation type).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

/**
 * Classification tree reader.
 */
final class Classification {

	/**
	 * Term meta holding the aliases of an industry or organisation type term (array of strings).
	 */
	public const ALIAS_META = Taxonomies::META_ALIAS;

	/**
	 * Post meta: term id of the primary industry (an industry leaf or the Unclassified term).
	 */
	public const PRIMARY_INDUSTRY_META = '_wiw_primary_industry';

	/**
	 * Post meta: term id of the primary country (a country place term or the Pan-African body).
	 */
	public const PRIMARY_COUNTRY_META = '_wiw_primary_country';

	/**
	 * Post meta: where the classification came from (method, model, prompt_version, confidence) as JSON. Internal only.
	 */
	public const PROVENANCE_META = '_wiw_classification_provenance';

	/**
	 * The ISO-like code of the Pan-African place.
	 */
	public const PAN = 'PAN';

	/**
	 * Slug of the Pan-African place term.
	 */
	public const PAN_SLUG = 'pan-african';

	/**
	 * The loaded config.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $config = null;

	/**
	 * Alias indexes per taxonomy, built once per request.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $index = array();

	/**
	 * The tree config.
	 *
	 * @return array{version: int, sectors: array<string, array{label: string, industries: array<string, array{label: string, aliases: string[]}>}>, unclassified: string, org_types: array<string, array{label: string, aliases: string[]}>}
	 */
	public static function config(): array {
		if ( null === self::$config ) {
			$loaded       = require dirname( __DIR__, 2 ) . '/config/classification.php';
			self::$config = is_array( $loaded ) ? $loaded : array();
		}
		/**
		 * The config shape is fixed by the file.
		 *
		 * @var array{version: int, sectors: array<string, array{label: string, industries: array<string, array{label: string, aliases: string[]}>}>, unclassified: string, org_types: array<string, array{label: string, aliases: string[]}>} $config
		 */
		$config = self::$config;
		return $config;
	}

	/**
	 * The tree as the acquisition side reads it (`docs/contracts/classification-tree.json`): sectors with their industries,
	 * Unclassified and the organisation types, each with slug, label and aliases, in config order. Plain PHP.
	 *
	 * @return array<string, mixed>
	 */
	public static function tree(): array {
		$config  = self::config();
		$sectors = array();
		foreach ( $config['sectors'] as $slug => $sector ) {
			$industries = array();
			foreach ( $sector['industries'] as $industry_slug => $industry ) {
				$industries[] = array(
					'slug'    => (string) $industry_slug,
					'label'   => (string) $industry['label'],
					'aliases' => array_values( $industry['aliases'] ),
				);
			}
			$sectors[] = array(
				'slug'       => (string) $slug,
				'label'      => (string) $sector['label'],
				'industries' => $industries,
			);
		}
		$types = array();
		foreach ( $config['org_types'] as $slug => $type ) {
			$types[] = array(
				'slug'    => (string) $slug,
				'label'   => (string) $type['label'],
				'aliases' => array_values( $type['aliases'] ),
			);
		}
		return array(
			'note'         => 'Generated from site/wp-content/plugins/whoiswho-core/config/classification.php by site/bin/classification-tree.php. Do not edit by hand. Contract v1.2 carries these slugs.',
			'version'      => (int) $config['version'],
			'sectors'      => $sectors,
			'unclassified' => (string) $config['unclassified'],
			'org_types'    => $types,
		);
	}

	/**
	 * The tree as pretty JSON with a final newline.
	 */
	public static function tree_json(): string {
		return json_encode( self::tree(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP, run from the command line without WordPress.
	}

	/**
	 * Forget the per-request caches (after terms change, and in tests).
	 */
	public static function flush(): void {
		self::$index = array();
	}

	/**
	 * The Unclassified slug.
	 */
	public static function unclassified_slug(): string {
		return (string) self::config()['unclassified'];
	}

	/**
	 * Industry slug => sector slug, from the config.
	 *
	 * @return array<string, string>
	 */
	public static function industry_sectors(): array {
		$out = array();
		foreach ( self::config()['sectors'] as $sector => $row ) {
			foreach ( array_keys( $row['industries'] ) as $industry ) {
				$out[ (string) $industry ] = (string) $sector;
			}
		}
		return $out;
	}

	/**
	 * Whether a slug is an industry leaf of the config.
	 *
	 * @param string $slug Slug.
	 */
	public static function is_industry( string $slug ): bool {
		return isset( self::industry_sectors()[ $slug ] );
	}

	/**
	 * Whether a slug is an organisation type of the config.
	 *
	 * @param string $slug Slug.
	 */
	public static function is_org_type( string $slug ): bool {
		return isset( self::config()['org_types'][ $slug ] );
	}

	/**
	 * A value as matching uses it: lower case, "&" as "and", single spaces.
	 *
	 * @param string $value Value.
	 */
	public static function normalise( string $value ): string {
		$value = mb_strtolower( trim( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$value = str_replace( '&', ' and ', $value );
		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}

	/**
	 * The industry leaf slug a raw value means (slug, name or alias of an industry leaf), or null. A sector name is not an
	 * industry, so it does not match unless a leaf carries it as an alias.
	 *
	 * @param string $raw Raw value.
	 */
	public static function match_industry( string $raw ): ?string {
		return self::match( Taxonomies::INDUSTRY, $raw );
	}

	/**
	 * The organisation type slug a raw value means, or null.
	 *
	 * @param string $raw Raw value.
	 */
	public static function match_org_type( string $raw ): ?string {
		return self::match( Taxonomies::ORG_TYPE, $raw );
	}

	/**
	 * Match a raw value in a taxonomy's index.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $raw      Raw value.
	 */
	private static function match( string $taxonomy, string $raw ): ?string {
		$found = self::match_one( $taxonomy, $raw );
		if ( null !== $found ) {
			return $found;
		}
		// A list such as "financial services, financial sector": each part is tried in order and the first match wins.
		$parts = preg_split( '/\s*[,;|]\s*/u', $raw );
		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return null;
		}
		foreach ( $parts as $part ) {
			$found = '' === trim( $part ) ? null : self::match_one( $taxonomy, $part );
			if ( null !== $found ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * Match one value: as given, then without a trailing "industry" or "sector" ("telecommunications industry").
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $raw      Raw value.
	 */
	private static function match_one( string $taxonomy, string $raw ): ?string {
		$key = self::normalise( $raw );
		if ( '' === $key ) {
			return null;
		}
		$index = self::index( $taxonomy );
		if ( isset( $index[ $key ] ) ) {
			return $index[ $key ];
		}
		$slug = sanitize_title( $raw );
		if ( isset( $index[ 'slug:' . $slug ] ) ) {
			return $index[ 'slug:' . $slug ];
		}
		$bare = trim( (string) preg_replace( '/\s+(industry|industries|sector)$/u', '', $key ) );
		return '' !== $bare && $bare !== $key && isset( $index[ $bare ] ) ? $index[ $bare ] : null;
	}

	/**
	 * Normalised name, alias or "slug:<slug>" => slug, for the industry leaves or the organisation types. The config comes first;
	 * live terms (staff-added terms and aliases, renamed labels) are added where they do not clash. For industries only leaves
	 * count: a term with a parent, or a config industry.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, string>
	 */
	private static function index( string $taxonomy ): array {
		if ( isset( self::$index[ $taxonomy ] ) ) {
			return self::$index[ $taxonomy ];
		}
		$out    = array();
		$add    = static function ( string $value, string $slug ) use ( &$out ): void {
			$key = self::normalise( $value );
			if ( '' !== $key && ! isset( $out[ $key ] ) ) {
				$out[ $key ] = $slug;
			}
		};
		$config = self::config();
		$rows   = array();
		if ( Taxonomies::INDUSTRY === $taxonomy ) {
			foreach ( $config['sectors'] as $sector ) {
				$rows += $sector['industries'];
			}
		} else {
			$rows = $config['org_types'];
		}
		foreach ( $rows as $slug => $row ) {
			$out[ 'slug:' . $slug ] = (string) $slug;
			$add( (string) $slug, (string) $slug );
			$add( (string) $row['label'], (string) $slug );
		}
		foreach ( $rows as $slug => $row ) {
			foreach ( $row['aliases'] as $alias ) {
				$add( (string) $alias, (string) $slug );
			}
		}
		if ( taxonomy_exists( $taxonomy ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( ! $term instanceof \WP_Term || ( Taxonomies::INDUSTRY === $taxonomy && 0 === (int) $term->parent && ! isset( $rows[ $term->slug ] ) ) ) {
					continue;
				}
				if ( ! isset( $out[ 'slug:' . $term->slug ] ) ) {
					$out[ 'slug:' . $term->slug ] = $term->slug;
				}
				$add( $term->name, $term->slug );
				foreach ( self::aliases_of( (int) $term->term_id ) as $alias ) {
					$add( $alias, $term->slug );
				}
			}
		}
		self::$index[ $taxonomy ] = $out;
		return $out;
	}

	/**
	 * The aliases stored on a term: one meta value holding an array, or string values (a comma list is split); all are read.
	 *
	 * @param int $term_id Term id.
	 * @return string[]
	 */
	public static function aliases_of( int $term_id ): array {
		$out = array();
		foreach ( (array) get_term_meta( $term_id, self::ALIAS_META, false ) as $value ) {
			foreach ( is_array( $value ) ? $value : explode( ',', (string) $value ) as $alias ) {
				if ( is_string( $alias ) && '' !== trim( $alias ) ) {
					$out[] = trim( $alias );
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * A term id by slug, or null.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $slug     Slug.
	 */
	public static function term_id( string $taxonomy, string $slug ): ?int {
		if ( '' === $slug || ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}
		$term = get_term_by( 'slug', $slug, $taxonomy );
		return $term instanceof \WP_Term ? (int) $term->term_id : null;
	}

	/**
	 * The Unclassified term id, or null before the seed ran.
	 */
	public static function unclassified_id(): ?int {
		return self::term_id( Taxonomies::INDUSTRY, self::unclassified_slug() );
	}

	/**
	 * The top-level place term with a country code (ISO alpha-2, or PAN for the Pan-African body), or null.
	 *
	 * @param string $code Code.
	 */
	public static function country_id( string $code ): ?int {
		$code = strtoupper( trim( $code ) );
		if ( 1 !== preg_match( '/^([A-Z]{2}|PAN)$/', $code ) || ! taxonomy_exists( Taxonomies::PLACE ) ) {
			return null;
		}
		$ids = get_terms(
			array(
				'taxonomy'   => Taxonomies::PLACE,
				'hide_empty' => false,
				'parent'     => 0,
				'fields'     => 'ids',
				'meta_key'   => Taxonomies::META_ISO, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a small list of countries.
				'meta_value' => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
			)
		);
		if ( is_array( $ids ) && array() !== $ids ) {
			return (int) $ids[0];
		}
		if ( self::PAN === $code ) {
			return self::term_id( Taxonomies::PLACE, self::PAN_SLUG );
		}
		return null;
	}

	/**
	 * The country term for a code, created when missing and the code is a real ISO 3166-1 alpha-2 code (from the built-in list).
	 * Null for an unknown code.
	 *
	 * @param string $code Code.
	 */
	public static function ensure_country( string $code ): ?int {
		$code  = strtoupper( trim( $code ) );
		$found = self::country_id( $code );
		if ( null !== $found ) {
			return $found;
		}
		$name = IsoCountries::name( $code );
		if ( null === $name || ! taxonomy_exists( Taxonomies::PLACE ) ) {
			return null;
		}
		$made = wp_insert_term( $name, Taxonomies::PLACE, array( 'parent' => 0 ) );
		if ( is_wp_error( $made ) ) {
			$existing = term_exists( $name, Taxonomies::PLACE, 0 );
			if ( ! is_array( $existing ) ) {
				return null;
			}
			$made = $existing;
		}
		$term_id = (int) $made['term_id'];
		update_term_meta( $term_id, Taxonomies::META_ISO, $code );
		update_term_meta( $term_id, Taxonomies::META_KIND, Taxonomies::KIND_COUNTRY );
		return $term_id;
	}
}
