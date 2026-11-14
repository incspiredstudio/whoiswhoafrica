<?php
/**
 * Writes a profile's classification (REQ-009 as amended 2026-11-14): one primary industry (a leaf of the tree, or Unclassified)
 * plus extra industries in `wiw_industry`, one primary country (a country or the Pan-African body) plus the city in `wiw_place`,
 * and exactly one organisation type for organisations and institutions. The primary choices are kept in post meta
 * (`_wiw_primary_industry`, `_wiw_primary_country`) so the breadcrumb never depends on term order. Only the importer, the editor
 * service, the upgrade migration and the demo call this; templates read the result through PublicProfile.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

/**
 * Profile classification writer.
 */
final class ProfileClassifier {

	/**
	 * Entity types that carry an organisation type.
	 */
	public const ORG_ENTITY_TYPES = array( 'organisation', 'institution' );

	/**
	 * Write the classification.
	 *
	 * @param int                  $profile_id  Profile.
	 * @param string               $entity_type Entity type.
	 * @param array<string, mixed> $input       primary_industry (slug or empty), industries (extra slugs), country (ISO alpha-2,
	 *                                          PAN or empty), country_id (a place term id, used when country is empty), city_id
	 *                                          (a city term id or 0), org_type (slug or empty), provenance (array or null).
	 * @return array{primary_industry: int|null, primary_country: int|null, org_type: int|null}
	 */
	public function assign( int $profile_id, string $entity_type, array $input ): array {
		$primary = $this->industry_id( (string) ( $input['primary_industry'] ?? '' ) ) ?? Classification::unclassified_id();
		$terms   = null === $primary ? array() : array( $primary );
		foreach ( (array) ( $input['industries'] ?? array() ) as $slug ) {
			$id = $this->industry_id( is_string( $slug ) ? $slug : '' );
			if ( null !== $id && ! in_array( $id, $terms, true ) && Classification::unclassified_id() !== $id ) {
				$terms[] = $id;
			}
		}
		wp_set_object_terms( $profile_id, $terms, Taxonomies::INDUSTRY );
		$this->meta( $profile_id, Classification::PRIMARY_INDUSTRY_META, $primary );

		$code    = strtoupper( (string) ( $input['country'] ?? '' ) );
		$country = '' !== $code ? Classification::ensure_country( $code ) : null;
		if ( null === $country && (int) ( $input['country_id'] ?? 0 ) > 0 ) {
			$country = $this->country_of( (int) $input['country_id'] );
		}
		$city   = (int) ( $input['city_id'] ?? 0 );
		$places = array();
		if ( $city > 0 ) {
			$city_term = get_term( $city, Taxonomies::PLACE );
			$kind      = $city_term instanceof \WP_Term ? (string) get_term_meta( $city, Taxonomies::META_KIND, true ) : '';
			if ( $city_term instanceof \WP_Term && Taxonomies::KIND_CITY === $kind && ( null === $country || (int) $city_term->parent === $country ) ) {
				$places[] = $city;
				$country  = $country ?? ( (int) $city_term->parent > 0 ? (int) $city_term->parent : null );
			}
		}
		if ( null !== $country ) {
			array_unshift( $places, $country );
		}
		wp_set_object_terms( $profile_id, array_values( array_unique( $places ) ), Taxonomies::PLACE );
		$this->meta( $profile_id, Classification::PRIMARY_COUNTRY_META, $country );

		$org_type = null;
		if ( in_array( $entity_type, self::ORG_ENTITY_TYPES, true ) ) {
			$slug     = (string) ( $input['org_type'] ?? '' );
			$org_type = Classification::term_id( Taxonomies::ORG_TYPE, '' !== $slug ? $slug : 'other' ) ?? Classification::term_id( Taxonomies::ORG_TYPE, 'other' );
		}
		wp_set_object_terms( $profile_id, null === $org_type ? array() : array( $org_type ), Taxonomies::ORG_TYPE );

		$provenance = $input['provenance'] ?? null;
		if ( is_array( $provenance ) ) {
			update_post_meta( $profile_id, Classification::PROVENANCE_META, (string) wp_json_encode( $provenance ) );
		}
		return array(
			'primary_industry' => $primary,
			'primary_country'  => $country,
			'org_type'         => $org_type,
		);
	}

	/**
	 * The term id of an industry leaf (a term with a parent) or of Unclassified, by slug; null for a sector or an unknown slug.
	 *
	 * @param string $slug Slug.
	 */
	public function industry_id( string $slug ): ?int {
		if ( '' === $slug || ! taxonomy_exists( Taxonomies::INDUSTRY ) ) {
			return null;
		}
		$term = get_term_by( 'slug', $slug, Taxonomies::INDUSTRY );
		if ( ! $term instanceof \WP_Term ) {
			return null;
		}
		return 0 !== (int) $term->parent || Classification::unclassified_slug() === $term->slug ? (int) $term->term_id : null;
	}

	/**
	 * The top-level country (or body) of a place term: itself when top level, else its top ancestor.
	 *
	 * @param int $term_id Place term.
	 */
	public function country_of( int $term_id ): ?int {
		$term = get_term( $term_id, Taxonomies::PLACE );
		$seen = 0;
		while ( $term instanceof \WP_Term && 0 !== (int) $term->parent && $seen < 5 ) {
			$term = get_term( (int) $term->parent, Taxonomies::PLACE );
			++$seen;
		}
		return $term instanceof \WP_Term && 0 === (int) $term->parent ? (int) $term->term_id : null;
	}

	/**
	 * Set or clear an id meta.
	 *
	 * @param int      $profile_id Profile.
	 * @param string   $key        Meta key.
	 * @param int|null $value      Term id.
	 */
	private function meta( int $profile_id, string $key, ?int $value ): void {
		if ( null === $value ) {
			delete_post_meta( $profile_id, $key );
			return;
		}
		update_post_meta( $profile_id, $key, $value );
	}
}
