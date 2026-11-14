<?php
/**
 * Moves an existing site onto the classification tree (REQ-009 as amended 2026-11-14). Forward only and safe to run again:
 *
 * 1. Seed the tree, the organisation types and the Pan-African place.
 * 2. Classify every profile that has no primary industry yet: its old flat industry terms, then its industry, field and sector
 *    text, map through the tree's aliases to one leaf, else Unclassified; its old organisation type term or text maps the same
 *    way, else "other" (organisations and institutions only); its primary country comes from its place terms.
 * 3. Fold the old flat industry and organisation type terms into the tree: one whose name the tree knows is merged into that
 *    entry (its other posts, such as stories and events, move with it, and its name becomes an alias); any other is removed.
 * 4. Empty the retired sector taxonomy.
 *
 * It returns counts only; no names or values are logged.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\State\StateRepository;

/**
 * Classification upgrade.
 */
final class ClassificationUpgrade {

	/**
	 * Run the upgrade.
	 *
	 * @return array{profiles: int, classified: int, unclassified: int, terms_merged: int, terms_removed: int, sectors_removed: int}
	 */
	public function run(): array {
		$taxonomies = new Taxonomies();
		if ( ! taxonomy_exists( Taxonomies::INDUSTRY ) || ! is_taxonomy_hierarchical( Taxonomies::INDUSTRY ) ) {
			$taxonomies->register();
		}
		( new ClassificationSeeder() )->seed();
		update_option( ClassificationSeeder::OPTION, (int) Classification::config()['version'], false );

		$counts = array(
			'profiles'        => 0,
			'classified'      => 0,
			'unclassified'    => 0,
			'terms_merged'    => 0,
			'terms_removed'   => 0,
			'sectors_removed' => 0,
		);
		$this->classify_profiles( $counts );
		$this->fold_terms( Taxonomies::INDUSTRY, $counts );
		$this->fold_terms( Taxonomies::ORG_TYPE, $counts );
		$counts['sectors_removed'] = $this->empty_sectors();
		Classification::flush();
		return $counts;
	}

	/**
	 * Repair a site that already ran the upgrade with the shorter alias lists of 0.3.0 (REQ-009): a profile that is Unclassified (or
	 * has no primary industry), or an organisation or institution with no organisation type or only "other", is matched again from
	 * its stored raw text (wiw_industry, wiw_field, wiw_sector, wiw_org_type). Only what was missing changes: a real industry or
	 * organisation type stays, and so do the country, the city and the extra industries. A classification staff chose (method
	 * "manual") or the acquisition side decided (method "rule" or "ai") is never touched. Safe to run again. Counts only.
	 *
	 * @return array{profiles: int, industries_repaired: int, org_types_repaired: int, kept_as_set: int}
	 */
	public function repair(): array {
		global $wpdb;
		if ( ! taxonomy_exists( Taxonomies::INDUSTRY ) || ! is_taxonomy_hierarchical( Taxonomies::INDUSTRY ) ) {
			( new Taxonomies() )->register();
		}
		( new ClassificationSeeder() )->seed();
		Classification::flush();
		$counts       = array(
			'profiles'            => 0,
			'industries_repaired' => 0,
			'org_types_repaired'  => 0,
			'kept_as_set'         => 0,
		);
		$state        = new StateRepository();
		$classifier   = new ProfileClassifier();
		$unclassified = Classification::unclassified_id();
		$other        = Classification::term_id( Taxonomies::ORG_TYPE, 'other' );
		$last         = 0;
		do {
			$ids = array_map(
				'intval',
				(array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT 200", ProfileType::POST_TYPE, $last )
				)
			);
			foreach ( $ids as $id ) {
				$last = $id;
				++$counts['profiles'];
				$row     = $state->get( $id );
				$type    = null === $row ? 'individual' : (string) $row['entity_type'];
				$primary = (int) get_post_meta( $id, Classification::PRIMARY_INDUSTRY_META, true );
				$orgs    = $this->terms_of( $id, Taxonomies::ORG_TYPE );
				$org_id  = isset( $orgs[0] ) ? (int) $orgs[0]->term_id : 0;

				$needs_industry = 0 === $primary || $unclassified === $primary;
				$needs_org      = in_array( $type, ProfileClassifier::ORG_ENTITY_TYPES, true ) && ( 0 === $org_id || $other === $org_id );
				if ( ! $needs_industry && ! $needs_org ) {
					continue;
				}
				$provenance = json_decode( (string) get_post_meta( $id, Classification::PROVENANCE_META, true ), true );
				$method     = is_array( $provenance ) ? (string) ( $provenance['method'] ?? '' ) : '';
				if ( in_array( $method, array( 'manual', 'rule', 'ai' ), true ) ) {
					++$counts['kept_as_set'];
					continue;
				}

				$derived      = $this->derive( $id, $type, $classifier );
				$extras       = array();
				$primary_slug = '';
				foreach ( $this->terms_of( $id, Taxonomies::INDUSTRY ) as $term ) {
					if ( (int) $term->term_id === $primary ) {
						$primary_slug = $term->slug;
					} elseif ( null !== $classifier->industry_id( $term->slug ) && $unclassified !== (int) $term->term_id ) {
						$extras[] = $term->slug;
					}
				}
				$raw_primary    = $this->raw_industry( $id );
				$industry_fixed = $needs_industry && '' !== $raw_primary;
				$org_slug       = isset( $orgs[0] ) ? $orgs[0]->slug : '';
				$raw_org        = (string) Classification::match_org_type( (string) get_post_meta( $id, FieldAllowlist::META_PREFIX . 'org_type', true ) );
				$org_fixed      = $needs_org && '' !== $raw_org && ( 'other' !== $raw_org || '' === $org_slug ) && $raw_org !== $org_slug;
				if ( ! $industry_fixed && ! $org_fixed ) {
					continue;
				}
				$country = (int) get_post_meta( $id, Classification::PRIMARY_COUNTRY_META, true );
				$classifier->assign(
					$id,
					$type,
					array(
						'primary_industry' => $industry_fixed ? $raw_primary : $primary_slug,
						'industries'       => array_values( array_diff( $extras, array( $raw_primary ) ) ),
						'country_id'       => $country > 0 ? $country : $derived['country_id'],
						'city_id'          => $derived['city_id'],
						'org_type'         => $org_fixed ? $raw_org : $org_slug,
						'provenance'       => array(
							'method'         => 'alias',
							'model'          => null,
							'prompt_version' => null,
							'confidence'     => 'medium',
						),
					)
				);
				$counts['industries_repaired'] += $industry_fixed ? 1 : 0;
				$counts['org_types_repaired']  += $org_fixed ? 1 : 0;
			}//end foreach
		} while ( array() !== $ids );
		return $counts;
	}

	/**
	 * The industry leaf a profile's stored raw text means (industry, then field, then sector), or "".
	 *
	 * @param int $id Profile.
	 */
	private function raw_industry( int $id ): string {
		foreach ( array( 'industry', 'field', 'sector' ) as $key ) {
			$raw  = (string) get_post_meta( $id, FieldAllowlist::META_PREFIX . $key, true );
			$slug = '' === trim( $raw ) ? null : Classification::match_industry( $raw );
			if ( null !== $slug ) {
				return $slug;
			}
		}
		return '';
	}

	/**
	 * Classify the profiles without a primary industry, in pages.
	 *
	 * @param array<string, int> $counts Counts.
	 */
	private function classify_profiles( array &$counts ): void {
		global $wpdb;
		$state      = new StateRepository();
		$classifier = new ProfileClassifier();
		$last       = 0;
		do {
			$ids = array_map(
				'intval',
				(array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = %s AND p.ID > %d AND m.meta_id IS NULL ORDER BY p.ID ASC LIMIT 200",
						Classification::PRIMARY_INDUSTRY_META,
						ProfileType::POST_TYPE,
						$last
					)
				)
			);
			foreach ( $ids as $id ) {
				$last = $id;
				$row  = $state->get( $id );
				$type = null === $row ? 'individual' : (string) $row['entity_type'];
				$done = $classifier->assign( $id, $type, $this->derive( $id, $type, $classifier ) );
				++$counts['profiles'];
				if ( Classification::unclassified_id() === $done['primary_industry'] ) {
					++$counts['unclassified'];
				} else {
					++$counts['classified'];
				}
			}
		} while ( array() !== $ids );
	}

	/**
	 * The classification of one existing profile from its old terms and text.
	 *
	 * @param int               $id         Profile.
	 * @param string            $type       Entity type.
	 * @param ProfileClassifier $classifier Classifier.
	 * @return array<string, mixed>
	 */
	private function derive( int $id, string $type, ProfileClassifier $classifier ): array {
		$candidates = array();
		$leaves     = array();
		foreach ( $this->terms_of( $id, Taxonomies::INDUSTRY ) as $term ) {
			if ( null !== $classifier->industry_id( $term->slug ) && Classification::unclassified_slug() !== $term->slug ) {
				$leaves[] = $term->slug;
				continue;
			}
			$candidates[] = $term->name;
		}
		foreach ( array( 'industry', 'field', 'sector' ) as $key ) {
			$candidates[] = (string) get_post_meta( $id, FieldAllowlist::META_PREFIX . $key, true );
		}
		foreach ( $this->terms_of( $id, Taxonomies::SECTOR ) as $term ) {
			$candidates[] = $term->name;
		}
		foreach ( $candidates as $raw ) {
			$slug = '' === trim( $raw ) ? null : Classification::match_industry( $raw );
			if ( null !== $slug && ! in_array( $slug, $leaves, true ) ) {
				$leaves[] = $slug;
			}
		}

		$org_type = '';
		if ( in_array( $type, ProfileClassifier::ORG_ENTITY_TYPES, true ) ) {
			$raw_types = array_map( static fn ( \WP_Term $term ): string => $term->name, $this->terms_of( $id, Taxonomies::ORG_TYPE ) );
			foreach ( $this->terms_of( $id, Taxonomies::ORG_TYPE ) as $term ) {
				if ( Classification::is_org_type( $term->slug ) ) {
					array_unshift( $raw_types, $term->slug );
				}
			}
			$raw_types[] = (string) get_post_meta( $id, FieldAllowlist::META_PREFIX . 'org_type', true );
			foreach ( $raw_types as $raw ) {
				$slug = '' === trim( $raw ) ? null : Classification::match_org_type( $raw );
				if ( null !== $slug ) {
					$org_type = $slug;
					break;
				}
			}
		}

		$country = 0;
		$city    = 0;
		foreach ( $this->terms_of( $id, Taxonomies::PLACE ) as $term ) {
			$kind = (string) get_term_meta( $term->term_id, Taxonomies::META_KIND, true );
			if ( Taxonomies::KIND_CITY === $kind && 0 === $city ) {
				$city = $term->term_id;
			} elseif ( 0 === (int) $term->parent && 0 === $country ) {
				$country = $term->term_id;
			}
		}
		if ( 0 === $country && $city > 0 ) {
			$country = (int) $classifier->country_of( $city );
		}
		return array(
			'primary_industry' => $leaves[0] ?? '',
			'industries'       => array_slice( $leaves, 1, 3 ),
			'country_id'       => $country,
			'city_id'          => $city,
			'org_type'         => $org_type,
			'provenance'       => array(
				'method'         => 'alias',
				'model'          => null,
				'prompt_version' => null,
				'confidence'     => isset( $leaves[0] ) ? 'medium' : 'low',
			),
		);
	}

	/**
	 * Fold the terms of a taxonomy that are not part of the tree: merge the ones the tree knows by name or alias into that entry
	 * (posts move with them, the name becomes an alias), remove the rest.
	 *
	 * @param string             $taxonomy Industry or organisation type.
	 * @param array<string, int> $counts   Counts.
	 */
	private function fold_terms( string $taxonomy, array &$counts ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		$keep  = $this->tree_slugs( $taxonomy );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( ! $term instanceof \WP_Term || in_array( $term->slug, $keep, true ) || ( Taxonomies::INDUSTRY === $taxonomy && 0 !== (int) $term->parent ) ) {
				continue;
			}
			if ( Taxonomies::INDUSTRY === $taxonomy && array() !== get_term_children( (int) $term->term_id, $taxonomy ) ) {
				// A sector staff added with industries under it is part of the tree.
				continue;
			}
			$target_slug = Taxonomies::INDUSTRY === $taxonomy ? Classification::match_industry( $term->name ) : Classification::match_org_type( $term->name );
			$target      = null === $target_slug ? null : Classification::term_id( $taxonomy, $target_slug );
			if ( null !== $target && $target !== (int) $term->term_id ) {
				$objects = get_objects_in_term( (int) $term->term_id, $taxonomy );
				foreach ( is_array( $objects ) ? $objects : array() as $object_id ) {
					wp_set_object_terms( (int) $object_id, array( $target ), $taxonomy, true );
				}
				$aliases = Classification::aliases_of( $target );
				$name    = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
				if ( ! in_array( $name, $aliases, true ) ) {
					$aliases[] = $name;
					delete_term_meta( $target, Taxonomies::META_ALIAS );
					add_term_meta( $target, Taxonomies::META_ALIAS, $aliases, true );
				}
				++$counts['terms_merged'];
			} else {
				++$counts['terms_removed'];
			}
			wp_delete_term( (int) $term->term_id, $taxonomy );
		}//end foreach
	}

	/**
	 * Slugs that belong to the tree: the config's sectors, industries and Unclassified, or the organisation types.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	private function tree_slugs( string $taxonomy ): array {
		if ( Taxonomies::ORG_TYPE === $taxonomy ) {
			return array_keys( Classification::config()['org_types'] );
		}
		return array_merge( array_keys( Classification::config()['sectors'] ), array_keys( Classification::industry_sectors() ), array( Classification::unclassified_slug() ) );
	}

	/**
	 * Delete every term of the retired sector taxonomy.
	 *
	 * @return int Terms removed.
	 */
	private function empty_sectors(): int {
		if ( ! taxonomy_exists( Taxonomies::SECTOR ) ) {
			return 0;
		}
		$ids = get_terms(
			array(
				'taxonomy'   => Taxonomies::SECTOR,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		$n   = 0;
		foreach ( is_array( $ids ) ? $ids : array() as $term_id ) {
			if ( true === wp_delete_term( (int) $term_id, Taxonomies::SECTOR ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * The terms of a profile in a taxonomy.
	 *
	 * @param int    $id       Profile.
	 * @param string $taxonomy Taxonomy.
	 * @return \WP_Term[]
	 */
	private function terms_of( int $id, string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = wp_get_object_terms( $id, $taxonomy );
		return is_array( $terms ) ? array_values( array_filter( $terms, static fn ( $term ): bool => $term instanceof \WP_Term ) ) : array();
	}
}
