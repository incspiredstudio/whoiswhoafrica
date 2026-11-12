<?php
/**
 * Profile page support (REQ-003, REQ-004, REQ-005, REQ-247): finds the profile the plugin's route selected, only through the
 * PublicProfile read model, and picks the profile template for /profile/{slug}. Hidden or unreviewed profiles never reach the
 * template: the route answers 404 or 410 before it, and the read model returns null for any state that may not show.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! function_exists( 'wiw_current_profile' ) ) {
	/**
	 * The public profile of the current request, or null. A member of staff also gets a profile that is not public, with the note
	 * that explains it (the read model checks the capability; the theme decides nothing).
	 */
	function wiw_current_profile(): ?PublicProfile {
		if ( ! class_exists( PublicProfile::class ) ) {
			return null;
		}
		$id = (int) get_query_var( 'wiw_profile_id', 0 );
		return $id > 0 ? PublicProfile::find_by_id( $id, true ) : null;
	}
}

if ( ! function_exists( 'wiw_profile_template' ) ) {
	/**
	 * Use the profile template when the route accepted the address.
	 *
	 * @param string $template Template path.
	 */
	function wiw_profile_template( string $template ): string {
		if ( is_404() || (int) get_query_var( 'wiw_profile_id', 0 ) <= 0 ) {
			return $template;
		}
		$found = locate_template( 'templates/profile.php' );
		return '' !== $found ? $found : $template;
	}
}
add_filter( 'template_include', 'wiw_profile_template', 11 );

if ( ! function_exists( 'wiw_profile_kind' ) ) {
	/**
	 * Label, list address and section title for a profile's entity type.
	 *
	 * @param string $entity_type individual, entrepreneur, organisation or institution.
	 * @return array{label: string, list: string, list_title: string}
	 */
	function wiw_profile_kind( string $entity_type ): array {
		switch ( $entity_type ) {
			case 'entrepreneur':
				return array(
					'label'      => __( 'Entrepreneur', 'whoiswho' ),
					'list'       => home_url( '/entrepreneurs/' ),
					'list_title' => __( 'Entrepreneurs', 'whoiswho' ),
				);
			case 'organisation':
				return array(
					'label'      => __( 'Business', 'whoiswho' ),
					'list'       => home_url( '/businesses/' ),
					'list_title' => __( 'Businesses', 'whoiswho' ),
				);
			case 'institution':
				return array(
					'label'      => __( 'Institution', 'whoiswho' ),
					'list'       => home_url( '/institutions/' ),
					'list_title' => __( 'Institutions', 'whoiswho' ),
				);
			default:
				return array(
					'label'      => __( 'Person', 'whoiswho' ),
					'list'       => home_url( '/people/' ),
					'list_title' => __( 'People', 'whoiswho' ),
				);
		}//end switch
	}
}//end if

if ( ! function_exists( 'wiw_profile_fact' ) ) {
	/**
	 * A text fact of a profile, or an empty string.
	 *
	 * @param PublicProfile $profile Profile.
	 * @param string        $key     Field key.
	 */
	function wiw_profile_fact( PublicProfile $profile, string $key ): string {
		$value = $profile->facts[ $key ]['value'] ?? '';
		return is_string( $value ) ? $value : '';
	}
}

if ( ! function_exists( 'wiw_profile_list' ) ) {
	/**
	 * A list fact of a profile (empty when absent).
	 *
	 * @param PublicProfile $profile Profile.
	 * @param string        $key     Field key.
	 * @return string[]
	 */
	function wiw_profile_list( PublicProfile $profile, string $key ): array {
		$value = $profile->facts[ $key ]['value'] ?? array();
		return is_array( $value ) ? array_values( array_filter( $value, 'is_string' ) ) : array();
	}
}

if ( ! function_exists( 'wiw_profile_sections' ) ) {
	/**
	 * The body sections of a profile, in order; a section appears only when it has content (REQ-003, REQ-004, REQ-005).
	 * Each: id, title, text (string) or items (list of strings).
	 *
	 * @param PublicProfile $profile Profile.
	 * @return array<int, array{id: string, title: string, text?: string, items?: string[]}>
	 */
	function wiw_profile_sections( PublicProfile $profile ): array {
		$sections = array();
		$about    = '' !== wiw_profile_fact( $profile, 'bio' ) ? wiw_profile_fact( $profile, 'bio' ) : wiw_profile_fact( $profile, 'summary' );
		if ( '' !== $about ) {
			$sections[] = array(
				'id'    => 'about',
				'title' => __( 'About', 'whoiswho' ),
				'text'  => $about,
			);
		}
		if ( 'institution' === $profile->entity_type && '' !== wiw_profile_fact( $profile, 'mandate' ) ) {
			$sections[] = array(
				'id'    => 'mandate',
				'title' => __( 'Mandate', 'whoiswho' ),
				'text'  => wiw_profile_fact( $profile, 'mandate' ),
			);
		}
		$roles = wiw_profile_list( $profile, 'public_record_roles' );
		if ( array() !== $roles ) {
			$sections[] = array(
				'id'    => 'career',
				'title' => __( 'Public record roles', 'whoiswho' ),
				'items' => $roles,
			);
		}
		$awards = wiw_profile_list( $profile, 'published_awards' );
		if ( array() !== $awards ) {
			$sections[] = array(
				'id'    => 'achievements',
				'title' => 'individual' === $profile->entity_type || 'entrepreneur' === $profile->entity_type ? __( 'Awards and achievements', 'whoiswho' ) : __( 'Achievements', 'whoiswho' ),
				'items' => $awards,
			);
		}
		/**
		 * Filters the profile sections (later issues add key facts, related stories).
		 *
		 * @param array<int, array<string, mixed>> $sections Sections.
		 * @param PublicProfile                    $profile  Profile.
		 */
		return apply_filters( 'wiw_profile_sections', $sections, $profile );
	}
}//end if

if ( ! function_exists( 'wiw_profile_key_facts' ) ) {
	/**
	 * The key facts of a profile, each with its source and retrieval date (REQ-144, REQ-133). A fact without a source address is
	 * not returned, even though the read model already drops it: the page must never show a fact it cannot cite.
	 * Each row: label, value, source_url, source_host, retrieved (Y-m-d, or empty).
	 *
	 * @param PublicProfile $profile Profile.
	 * @return array<int, array{label: string, value: string, source_url: string, source_host: string, retrieved: string}>
	 */
	function wiw_profile_key_facts( PublicProfile $profile ): array {
		$labels = array(
			'current_title'        => __( 'Current title', 'whoiswho' ),
			'current_organisation' => __( 'Organisation', 'whoiswho' ),
			'org_type'             => __( 'Organisation type', 'whoiswho' ),
			'industry'             => __( 'Industry', 'whoiswho' ),
			'sector'               => __( 'Sector', 'whoiswho' ),
			'regulator'            => __( 'Regulator', 'whoiswho' ),
			'licence_category'     => __( 'Licence category', 'whoiswho' ),
			'hq_city'              => __( 'Headquarters', 'whoiswho' ),
			'year_founded'         => __( 'Founded', 'whoiswho' ),
		);
		$rows   = array();
		foreach ( $labels as $key => $label ) {
			$fact   = $profile->facts[ $key ] ?? null;
			$value  = is_array( $fact ) ? ( $fact['value'] ?? '' ) : '';
			$value  = is_scalar( $value ) ? trim( (string) $value ) : '';
			$source = is_array( $fact ) && is_string( $fact['source_url'] ?? null ) ? esc_url_raw( $fact['source_url'], array( 'http', 'https' ) ) : '';
			if ( '' === $value || '' === $source ) {
				continue;
			}
			$time   = is_string( $fact['retrieved_at'] ?? null ) ? strtotime( $fact['retrieved_at'] ) : false;
			$host   = wp_parse_url( $source, PHP_URL_HOST );
			$rows[] = array(
				'label'       => $label,
				'value'       => $value,
				'source_url'  => $source,
				'source_host' => is_string( $host ) ? (string) preg_replace( '/^www\./', '', $host ) : $source,
				'retrieved'   => false === $time ? '' : gmdate( 'Y-m-d', $time ),
			);
		}
		return $rows;
	}
}//end if
