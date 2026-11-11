<?php
/**
 * Registers the controlled-list taxonomies (REQ-009): industry, sector, profession, organisation type and place.
 *
 * Term capabilities use the generic names the role catalogue defines: Reviewers hold assign_terms only, Editors and
 * Administrators also hold manage_terms, edit_terms and delete_terms. Terms are managed on the native term screens.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

/**
 * Taxonomy registration.
 */
final class Taxonomies {

	public const INDUSTRY     = 'wiw_industry';
	public const SECTOR       = 'wiw_sector';
	public const PROFESSION   = 'wiw_profession';
	public const ORG_TYPE     = 'wiw_org_type';
	public const PLACE        = 'wiw_place';
	public const META_ISO     = 'wiw_iso_code';
	public const META_KIND    = 'wiw_place_kind';
	public const KIND_COUNTRY = 'country';
	public const KIND_BODY    = 'body';
	public const KIND_CITY    = 'city';

	/**
	 * All taxonomy names.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::INDUSTRY, self::SECTOR, self::PROFESSION, self::ORG_TYPE, self::PLACE );
	}

	/**
	 * Register the taxonomies and the place term meta. Hooked to init.
	 */
	public function register(): void {
		$labels = array(
			self::INDUSTRY   => array( __( 'Industries', 'whoiswho' ), __( 'Industry', 'whoiswho' ), false ),
			self::SECTOR     => array( __( 'Sectors', 'whoiswho' ), __( 'Sector', 'whoiswho' ), false ),
			self::PROFESSION => array( __( 'Professions', 'whoiswho' ), __( 'Profession', 'whoiswho' ), false ),
			self::ORG_TYPE   => array( __( 'Organisation types', 'whoiswho' ), __( 'Organisation type', 'whoiswho' ), false ),
			self::PLACE      => array( __( 'Places', 'whoiswho' ), __( 'Place', 'whoiswho' ), true ),
		);
		foreach ( $labels as $taxonomy => list( $plural, $singular, $hierarchical ) ) {
			register_taxonomy(
				$taxonomy,
				ProfileType::POST_TYPE,
				array(
					'labels'             => array(
						'name'          => $plural,
						'singular_name' => $singular,
					),
					'public'             => false,
					'publicly_queryable' => false,
					'show_ui'            => true,
					'show_in_menu'       => true,
					'show_in_nav_menus'  => false,
					'show_in_rest'       => false,
					'show_tagcloud'      => false,
					'show_admin_column'  => true,
					'hierarchical'       => $hierarchical,
					'rewrite'            => false,
					'query_var'          => false,
					'capabilities'       => array(
						'manage_terms' => 'manage_terms',
						'edit_terms'   => 'edit_terms',
						'delete_terms' => 'delete_terms',
						'assign_terms' => 'assign_terms',
					),
				)
			);
		}//end foreach

		register_term_meta(
			self::PLACE,
			self::META_ISO,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => static fn ( $value ): string => strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $value ), 0, 3 ) ),
			)
		);
		register_term_meta(
			self::PLACE,
			self::META_KIND,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => static fn ( $value ): string => in_array( $value, array( self::KIND_COUNTRY, self::KIND_BODY, self::KIND_CITY ), true ) ? (string) $value : self::KIND_CITY,
			)
		);
	}
}
