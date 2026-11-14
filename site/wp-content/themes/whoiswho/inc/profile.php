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

if ( ! function_exists( 'wiw_profile_key_facts' ) ) {
	/**
	 * Rows of the key facts table (REQ-144, REQ-133): label, text value, source link, source host and retrieval date. A fact
	 * with no source link or no valid retrieval date is left out. The long text fields shown in the body (biography, summary)
	 * are not table rows.
	 *
	 * @param PublicProfile $profile Profile.
	 * @return array<int, array{label: string, value: string, source_url: string, source_host: string, retrieved: string, retrieved_iso: string}>
	 */
	function wiw_profile_key_facts( PublicProfile $profile ): array {
		$rows = array();
		foreach ( $profile->facts as $key => $fact ) {
			if ( in_array( $key, array( 'bio', 'summary', 'achievement', 'influence', 'legacy' ), true ) ) {
				continue;
			}
			$source = esc_url_raw( (string) ( $fact['source_url'] ?? '' ) );
			$when   = strtotime( (string) ( $fact['retrieved_at'] ?? '' ) );
			$value  = $fact['value'] ?? '';
			$value  = is_array( $value ) ? implode( ', ', array_filter( $value, 'is_string' ) ) : (string) $value;
			if ( '' === $source || false === $when || '' === trim( $value ) ) {
				continue;
			}
			$host   = (string) wp_parse_url( $source, PHP_URL_HOST );
			$rows[] = array(
				'label'         => ucfirst( str_replace( '_', ' ', (string) $key ) ),
				'value'         => $value,
				'source_url'    => $source,
				'source_host'   => '' !== $host ? (string) preg_replace( '#^www\.#', '', $host ) : $source,
				'retrieved'     => wp_date( (string) get_option( 'date_format' ), $when ),
				'retrieved_iso' => gmdate( 'Y-m-d', $when ),
			);
		}//end foreach
		return $rows;
	}
}//end if

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
		// The narrative sections (Bio, Achievement, Influence, Legacy) are drawn by wiw_profile_narrative(); an organisation
		// without a Bio keeps its reviewed summary here.
		$about = '' === wiw_profile_fact( $profile, 'bio' ) ? wiw_profile_fact( $profile, 'summary' ) : '';
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

if ( ! function_exists( 'wiw_profile_paragraphs' ) ) {
	/**
	 * Split plain text into paragraphs at blank lines (contract 1.1 section 4.3). Single line breaks stay inside a paragraph.
	 *
	 * @param string $text Plain text.
	 * @return string[]
	 */
	function wiw_profile_paragraphs( string $text ): array {
		$parts = preg_split( '/\n\s*\n/', str_replace( array( "\r\n", "\r" ), "\n", trim( $text ) ) );
		return array_values( array_filter( array_map( 'trim', is_array( $parts ) ? $parts : array() ), static fn ( string $p ): bool => '' !== $p ) );
	}
}

if ( ! function_exists( 'wiw_profile_section_title' ) ) {
	/**
	 * The translated heading of a narrative section.
	 *
	 * @param string $key   Section key.
	 * @param string $title Title from the read model.
	 */
	function wiw_profile_section_title( string $key, string $title ): string {
		$titles = array(
			'bio'         => __( 'Bio', 'whoiswho' ),
			'achievement' => __( 'Achievement', 'whoiswho' ),
			'influence'   => __( 'Influence', 'whoiswho' ),
			'legacy'      => __( 'Legacy', 'whoiswho' ),
		);
		return $titles[ $key ] ?? $title;
	}
}

if ( ! function_exists( 'wiw_licence_label' ) ) {
	/**
	 * A licence id as people read it: "CC-BY-SA-4.0" becomes "CC BY-SA 4.0"; the public-domain ids get plain words.
	 *
	 * @param string $licence Licence id.
	 */
	function wiw_licence_label( string $licence ): string {
		if ( 'PDM-1.0' === $licence ) {
			return __( 'Public domain', 'whoiswho' );
		}
		if ( 'CC0-1.0' === $licence ) {
			return 'CC0 1.0';
		}
		return (string) preg_replace( '/^CC-(BY(?:-SA)?)-(\d\.\d)$/', 'CC $1 $2', $licence );
	}
}

if ( ! function_exists( 'wiw_profile_lifespan' ) ) {
	/**
	 * The lifespan of a person who has died (for example "1924–2013"), or an empty string.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	function wiw_profile_lifespan( PublicProfile $profile ): string {
		return in_array( $profile->entity_type, array( 'individual', 'entrepreneur' ), true ) ? wiw_profile_fact( $profile, 'lifespan' ) : '';
	}
}
