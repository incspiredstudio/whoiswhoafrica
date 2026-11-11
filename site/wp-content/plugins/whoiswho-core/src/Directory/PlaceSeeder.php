<?php
/**
 * Seeds the place taxonomy: African countries (Nigeria first) as top-level terms with an ISO 3166-1 alpha-2 code, and a
 * top-level term for pan-African bodies. Cities are added by staff or by the import as needed. Idempotent: a country
 * already present (matched by ISO code) is left alone.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

/**
 * Place seeder.
 */
final class PlaceSeeder {

	/**
	 * Option holding the seed version already applied.
	 */
	public const OPTION = 'wiw_place_seed_version';

	/**
	 * Seed version; bump when the list changes.
	 */
	public const VERSION = 1;

	/**
	 * Countries in display order: Nigeria first, then the rest alphabetically.
	 *
	 * @return array<string,string> ISO code => name.
	 */
	public static function countries(): array {
		return array(
			'NG' => 'Nigeria',
			'DZ' => 'Algeria',
			'AO' => 'Angola',
			'BJ' => 'Benin',
			'BW' => 'Botswana',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'CM' => 'Cameroon',
			'CV' => 'Cabo Verde',
			'CF' => 'Central African Republic',
			'TD' => 'Chad',
			'KM' => 'Comoros',
			'CG' => 'Congo',
			'CD' => 'Democratic Republic of the Congo',
			'CI' => "C\u{f4}te d'Ivoire",
			'DJ' => 'Djibouti',
			'EG' => 'Egypt',
			'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea',
			'SZ' => 'Eswatini',
			'ET' => 'Ethiopia',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GH' => 'Ghana',
			'GN' => 'Guinea',
			'GW' => 'Guinea-Bissau',
			'KE' => 'Kenya',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libya',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'ML' => 'Mali',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'NA' => 'Namibia',
			'NE' => 'Niger',
			'RW' => 'Rwanda',
			'ST' => "S\u{e3}o Tom\u{e9} and Pr\u{ed}ncipe",
			'SN' => 'Senegal',
			'SC' => 'Seychelles',
			'SL' => 'Sierra Leone',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'SS' => 'South Sudan',
			'SD' => 'Sudan',
			'TZ' => 'Tanzania',
			'TG' => 'Togo',
			'TN' => 'Tunisia',
			'UG' => 'Uganda',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		);
	}

	/**
	 * Seed when the stored version is behind. Hooked to init after the taxonomy is registered.
	 */
	public function maybe_seed(): void {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return;
		}
		$this->seed();
		update_option( self::OPTION, self::VERSION, false );
	}

	/**
	 * Create the missing terms.
	 *
	 * @return int Number of terms created.
	 */
	public function seed(): int {
		$created = 0;
		foreach ( self::countries() as $iso => $name ) {
			if ( null !== $this->find_country( $iso ) ) {
				continue;
			}
			$created += $this->insert( $name, $iso, Taxonomies::KIND_COUNTRY );
		}
		$body = __( 'Pan-African', 'whoiswho' );
		if ( ! term_exists( $body, Taxonomies::PLACE, 0 ) ) {
			$created += $this->insert( $body, '', Taxonomies::KIND_BODY );
		}
		return $created;
	}

	/**
	 * Find a top-level country term by ISO code.
	 *
	 * @param string $iso ISO code.
	 * @return int|null Term id.
	 */
	public function find_country( string $iso ): ?int {
		$ids = get_terms(
			array(
				'taxonomy'   => Taxonomies::PLACE,
				'hide_empty' => false,
				'parent'     => 0,
				'fields'     => 'ids',
				'meta_key'   => Taxonomies::META_ISO, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small, one-off seeding lookup.
				'meta_value' => $iso, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
			)
		);
		return is_array( $ids ) && array() !== $ids ? (int) $ids[0] : null;
	}

	/**
	 * Insert one top-level term with its meta.
	 *
	 * @param string $name Name.
	 * @param string $iso  ISO code, may be empty.
	 * @param string $kind Place kind.
	 * @return int 1 when created, else 0.
	 */
	private function insert( string $name, string $iso, string $kind ): int {
		$result = wp_insert_term( $name, Taxonomies::PLACE, array( 'parent' => 0 ) );
		if ( is_wp_error( $result ) ) {
			return 0;
		}
		$term_id = (int) $result['term_id'];
		if ( '' !== $iso ) {
			update_term_meta( $term_id, Taxonomies::META_ISO, $iso );
		}
		update_term_meta( $term_id, Taxonomies::META_KIND, $kind );
		return 1;
	}
}
