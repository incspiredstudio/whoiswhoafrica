<?php
/**
 * A profile's classification as staff set it on Edit profile and Add profile (REQ-009, REQ-125): one primary industry (an industry
 * under a sector, or the "unclassified" term), extra industries, one organisation type (organisations and institutions only), one
 * primary country (a country or the Pan-African body) and an optional city in that country. The primary industry and country are
 * kept in post meta _wiw_primary_industry and _wiw_primary_country; the profile is also given the matching terms. Only this class,
 * the importer and the demo write those keys; templates read them through the PublicProfile read model.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Edit;

use WhoIsWho\Core\Directory\Taxonomies;

/**
 * Profile classification: read, check and apply.
 */
final class ProfileClassifier {

	public const META_PRIMARY_INDUSTRY = '_wiw_primary_industry';
	public const META_PRIMARY_COUNTRY  = '_wiw_primary_country';

	/**
	 * Slug of the industry term for a profile that is not classified yet; hidden from public navigation.
	 */
	public const UNCLASSIFIED = 'unclassified';

	/**
	 * Entity types that carry an organisation type.
	 */
	public const ORG_ENTITY_TYPES = array( 'organisation', 'institution' );

	/**
	 * The empty classification.
	 *
	 * @return array{primary_industry: int, industries: int[], org_type: int, country: int, city: int}
	 */
	public static function blank(): array {
		return array(
			'primary_industry' => 0,
			'industries'       => array(),
			'org_type'         => 0,
			'country'          => 0,
			'city'             => 0,
		);
	}

	/**
	 * The term id of the "unclassified" industry, or 0 when it does not exist.
	 */
	public static function unclassified_id(): int {
		$term = get_term_by( 'slug', self::UNCLASSIFIED, Taxonomies::INDUSTRY );
		return $term instanceof \WP_Term ? (int) $term->term_id : 0;
	}

	/**
	 * A profile's current classification. Extras are its other industry terms that are industries (never sectors).
	 *
	 * @param int $profile_id Profile.
	 * @return array{primary_industry: int, industries: int[], org_type: int, country: int, city: int}
	 */
	public function current( int $profile_id ): array {
		$out                     = self::blank();
		$out['primary_industry'] = (int) get_post_meta( $profile_id, self::META_PRIMARY_INDUSTRY, true );
		$out['country']          = (int) get_post_meta( $profile_id, self::META_PRIMARY_COUNTRY, true );
		foreach ( $this->terms_of( $profile_id, Taxonomies::INDUSTRY ) as $term ) {
			if ( (int) $term->term_id !== $out['primary_industry'] && $term->parent > 0 ) {
				$out['industries'][] = (int) $term->term_id;
			}
		}
		sort( $out['industries'] );
		$org             = $this->terms_of( $profile_id, Taxonomies::ORG_TYPE );
		$out['org_type'] = array() === $org ? 0 : (int) $org[0]->term_id;
		foreach ( $this->terms_of( $profile_id, Taxonomies::PLACE ) as $term ) {
			if ( Taxonomies::KIND_CITY === self::place_kind( (int) $term->term_id ) && ( 0 === $out['country'] || (int) $term->parent === $out['country'] ) ) {
				$out['city'] = (int) $term->term_id;
				break;
			}
		}
		return $out;
	}

	/**
	 * Check and clean a wanted classification.
	 *
	 * @param array<string, mixed> $wanted      primary_industry, industries, org_type, country, city (term ids).
	 * @param string               $entity_type Entity type of the profile.
	 * @return array{primary_industry: int, industries: int[], org_type: int, country: int, city: int}
	 * @throws EditRefused With a message fit to show.
	 */
	public function normalise( array $wanted, string $entity_type ): array {
		$out     = self::blank();
		$primary = (int) ( $wanted['primary_industry'] ?? 0 );
		if ( $primary > 0 ) {
			if ( ! $this->is_industry( $primary ) && self::unclassified_id() !== $primary ) {
				throw EditRefused::because( 'Choose an industry under a sector, or Unclassified, as the main industry.' );
			}
			$out['primary_industry'] = $primary;
		}
		$extras = array();
		foreach ( (array) ( $wanted['industries'] ?? array() ) as $id ) {
			$id = (int) $id;
			if ( $id <= 0 || $id === $primary ) {
				continue;
			}
			if ( ! $this->is_industry( $id ) ) {
				throw EditRefused::because( 'Other industries must be industries under a sector.' );
			}
			$extras[] = $id;
		}
		$extras = array_values( array_unique( $extras ) );
		sort( $extras );
		$out['industries'] = $extras;
		$org               = (int) ( $wanted['org_type'] ?? 0 );
		if ( $org > 0 && in_array( $entity_type, self::ORG_ENTITY_TYPES, true ) ) {
			if ( ! self::term_in( $org, Taxonomies::ORG_TYPE ) ) {
				throw EditRefused::because( 'Choose an organisation type from the list.' );
			}
			$out['org_type'] = $org;
		}
		$country = (int) ( $wanted['country'] ?? 0 );
		if ( $country > 0 ) {
			if ( ! self::term_in( $country, Taxonomies::PLACE ) || ! in_array( self::place_kind( $country ), array( Taxonomies::KIND_COUNTRY, Taxonomies::KIND_BODY ), true ) ) {
				throw EditRefused::because( 'Choose a country, or Pan-African, as the main country.' );
			}
			$out['country'] = $country;
		}
		$city = (int) ( $wanted['city'] ?? 0 );
		if ( $city > 0 ) {
			$term = get_term( $city, Taxonomies::PLACE );
			if ( ! $term instanceof \WP_Term || Taxonomies::KIND_CITY !== self::place_kind( $city ) ) {
				throw EditRefused::because( 'Choose a city from the list.' );
			}
			if ( 0 === $country || (int) $term->parent !== $country ) {
				throw EditRefused::because( 'The city must be in the chosen country.' );
			}
			$out['city'] = $city;
		}
		return $out;
	}

	/**
	 * Write a checked classification: the two meta keys and the industry, organisation type and place terms.
	 *
	 * @param int                                                                                     $profile_id     Profile.
	 * @param array{primary_industry: int, industries: int[], org_type: int, country: int, city: int} $classification Output of normalise().
	 */
	public function apply( int $profile_id, array $classification ): void {
		foreach ( array(
			self::META_PRIMARY_INDUSTRY => $classification['primary_industry'],
			self::META_PRIMARY_COUNTRY  => $classification['country'],
		) as $key => $value ) {
			if ( $value > 0 ) {
				update_post_meta( $profile_id, $key, (string) $value );
			} else {
				delete_post_meta( $profile_id, $key );
			}
		}
		foreach ( self::terms_for( $classification ) as $taxonomy => $ids ) {
			wp_set_object_terms( $profile_id, $ids, $taxonomy );
		}
	}

	/**
	 * The term sets a classification gives: taxonomy => term ids.
	 *
	 * @param array{primary_industry: int, industries: int[], org_type: int, country: int, city: int} $classification Classification.
	 * @return array<string, int[]>
	 */
	public static function terms_for( array $classification ): array {
		$industry = $classification['primary_industry'] > 0 ? array( $classification['primary_industry'] ) : array();
		return array(
			Taxonomies::INDUSTRY => array_values( array_unique( array_merge( $industry, $classification['industries'] ) ) ),
			Taxonomies::ORG_TYPE => $classification['org_type'] > 0 ? array( $classification['org_type'] ) : array(),
			Taxonomies::PLACE    => array_values( array_filter( array( $classification['country'], $classification['city'] ) ) ),
		);
	}

	/**
	 * The industry tree for a picker: sector id => name and its industries (id => name), by name. The unclassified term is left out.
	 *
	 * @return array<int, array{name: string, industries: array<int, string>}>
	 */
	public static function industry_tree(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::INDUSTRY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'number'     => 2000,
			)
		);
		$tree  = array();
		$kids  = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( ! $term instanceof \WP_Term || self::UNCLASSIFIED === $term->slug ) {
				continue;
			}
			if ( 0 === (int) $term->parent ) {
				$tree[ (int) $term->term_id ] = array(
					'name'       => $term->name,
					'industries' => array(),
				);
			} else {
				$kids[] = $term;
			}
		}
		foreach ( $kids as $term ) {
			if ( isset( $tree[ (int) $term->parent ] ) ) {
				$tree[ (int) $term->parent ]['industries'][ (int) $term->term_id ] = $term->name;
			}
		}
		return $tree;
	}

	/**
	 * Organisation types: id => name, by name.
	 *
	 * @return array<int, string>
	 */
	public static function org_types(): array {
		$out   = array();
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::ORG_TYPE,
				'hide_empty' => false,
				'orderby'    => 'name',
				'number'     => 500,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$out[ (int) $term->term_id ] = $term->name;
			}
		}
		return $out;
	}

	/**
	 * Places for the pickers: countries and the Pan-African body (id => name, by name) and cities grouped by their country id.
	 *
	 * @return array{countries: array<int, string>, cities: array<int, array<int, string>>}
	 */
	public static function places(): array {
		$terms     = get_terms(
			array(
				'taxonomy'   => Taxonomies::PLACE,
				'hide_empty' => false,
				'orderby'    => 'name',
				'number'     => 5000,
			)
		);
		$countries = array();
		$cities    = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$kind = self::place_kind( (int) $term->term_id );
			if ( Taxonomies::KIND_CITY === $kind && $term->parent > 0 ) {
				$cities[ (int) $term->parent ][ (int) $term->term_id ] = $term->name;
			} elseif ( in_array( $kind, array( Taxonomies::KIND_COUNTRY, Taxonomies::KIND_BODY ), true ) ) {
				$countries[ (int) $term->term_id ] = $term->name;
			}
		}
		return array(
			'countries' => $countries,
			'cities'    => $cities,
		);
	}

	/**
	 * A place term's kind (country, city or body); a term with no kind counts as a city.
	 *
	 * @param int $term_id Place term.
	 */
	public static function place_kind( int $term_id ): string {
		$kind = (string) get_term_meta( $term_id, Taxonomies::META_KIND, true );
		return '' === $kind ? Taxonomies::KIND_CITY : $kind;
	}

	/**
	 * Whether a term is an industry: an industry term whose parent is a top-level term (a sector) other than unclassified.
	 *
	 * @param int $term_id Term.
	 */
	public function is_industry( int $term_id ): bool {
		$term = get_term( $term_id, Taxonomies::INDUSTRY );
		if ( ! $term instanceof \WP_Term || $term->parent <= 0 ) {
			return false;
		}
		$sector = get_term( (int) $term->parent, Taxonomies::INDUSTRY );
		return $sector instanceof \WP_Term && 0 === (int) $sector->parent && self::UNCLASSIFIED !== $sector->slug;
	}

	/**
	 * Whether a term id exists in a taxonomy.
	 *
	 * @param int    $term_id  Term.
	 * @param string $taxonomy Taxonomy.
	 */
	private static function term_in( int $term_id, string $taxonomy ): bool {
		return get_term( $term_id, $taxonomy ) instanceof \WP_Term;
	}

	/**
	 * A profile's terms in one taxonomy.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $taxonomy   Taxonomy.
	 * @return \WP_Term[]
	 */
	private function terms_of( int $profile_id, string $taxonomy ): array {
		$terms = wp_get_object_terms( $profile_id, $taxonomy );
		return is_array( $terms ) ? array_values( array_filter( $terms, static fn ( $term ): bool => $term instanceof \WP_Term ) ) : array();
	}
}
