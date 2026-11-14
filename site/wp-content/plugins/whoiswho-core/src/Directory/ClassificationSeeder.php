<?php
/**
 * Seeds the classification tree from `config/classification.php` (REQ-009 as amended 2026-11-14): the sectors as top-level
 * industry terms, their industries under them, the top-level Unclassified term, the organisation types, and the aliases as term
 * meta. Idempotent: a term that already has its slug is kept (a staff rename survives), an old flat term whose slug is an
 * industry is moved under its sector, and the config aliases are added to the ones staff entered.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

/**
 * Classification seeder.
 */
final class ClassificationSeeder {

	/**
	 * Option holding the config version already seeded.
	 */
	public const OPTION = 'wiw_classification_seed_version';

	/**
	 * Seed when the stored version is behind the config. Hooked to init after the taxonomies are registered.
	 */
	public function maybe_seed(): void {
		$version = (int) Classification::config()['version'];
		if ( (int) get_option( self::OPTION, 0 ) >= $version || ! taxonomy_exists( Taxonomies::INDUSTRY ) ) {
			return;
		}
		$this->seed();
		update_option( self::OPTION, $version, false );
	}

	/**
	 * Create or repair the terms.
	 *
	 * @return int Terms created.
	 */
	public function seed(): int {
		$config  = Classification::config();
		$created = 0;
		foreach ( $config['sectors'] as $sector_slug => $sector ) {
			$sector_id = $this->ensure( Taxonomies::INDUSTRY, (string) $sector_slug, (string) $sector['label'], 0, array(), $created );
			if ( null === $sector_id ) {
				continue;
			}
			foreach ( $sector['industries'] as $slug => $industry ) {
				$this->ensure( Taxonomies::INDUSTRY, (string) $slug, (string) $industry['label'], $sector_id, (array) $industry['aliases'], $created );
			}
		}
		$this->ensure( Taxonomies::INDUSTRY, Classification::unclassified_slug(), __( 'Unclassified', 'whoiswho' ), 0, array(), $created );
		foreach ( $config['org_types'] as $slug => $type ) {
			$this->ensure( Taxonomies::ORG_TYPE, (string) $slug, (string) $type['label'], 0, (array) $type['aliases'], $created );
		}
		$created += ( new PlaceSeeder() )->seed();
		Classification::flush();
		return $created;
	}

	/**
	 * Find a term by slug or create it; place it under its parent and add the aliases.
	 *
	 * @param string   $taxonomy  Taxonomy.
	 * @param string   $slug      Slug.
	 * @param string   $label     Name for a new term.
	 * @param int      $parent_id Parent term id (0 for top level).
	 * @param string[] $aliases   Aliases from the config.
	 * @param int      $created   Counter of created terms.
	 * @return int|null Term id.
	 */
	private function ensure( string $taxonomy, string $slug, string $label, int $parent_id, array $aliases, int &$created ): ?int {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term instanceof \WP_Term ) {
			$term_id = (int) $term->term_id;
			if ( (int) $term->parent !== $parent_id && ( 0 === (int) $term->parent || 0 === $parent_id ) ) {
				wp_update_term( $term_id, $taxonomy, array( 'parent' => $parent_id ) );
			}
		} else {
			$made = wp_insert_term(
				$label,
				$taxonomy,
				array(
					'slug'   => $slug,
					'parent' => $parent_id,
				)
			);
			if ( is_wp_error( $made ) ) {
				return null;
			}
			$term_id = (int) $made['term_id'];
			++$created;
		}
		if ( array() !== $aliases ) {
			$merged = array_values( array_unique( array_merge( Classification::aliases_of( $term_id ), $aliases ) ) );
			delete_term_meta( $term_id, Taxonomies::META_ALIAS );
			add_term_meta( $term_id, Taxonomies::META_ALIAS, $merged, true );
		}
		return $term_id;
	}
}
