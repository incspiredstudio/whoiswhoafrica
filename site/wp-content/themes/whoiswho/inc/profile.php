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
use WhoIsWho\Core\Platform\Settings\SettingsService;

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
	 * are not table rows. The industry, sector, organisation type and city rows show the classified values (REQ-009) with the
	 * source of the fact they came from; the old free-text values are never shown.
	 *
	 * @param PublicProfile $profile Profile.
	 * @return array<int, array{label: string, value: string, source_url: string, source_host: string, retrieved: string, retrieved_iso: string}>
	 */
	function wiw_profile_key_facts( PublicProfile $profile ): array {
		return wiw_key_fact_rows( $profile->facts, wiw_profile_classification( $profile ), wiw_profile_org_type( $profile ), wiw_profile_place( $profile ) );
	}
}//end if

if ( ! function_exists( 'wiw_key_fact_rows' ) ) {
	/**
	 * Build the key facts rows from sourced facts and the classified values (REQ-144, REQ-133, REQ-009). The sector and industry
	 * rows carry the industry fact's source (the sector follows from the industry in the tree); a classified row with no value
	 * (an unclassified profile, an unknown city) is left out.
	 *
	 * @param array<string, array<string, mixed>>  $facts          Sourced facts from the read model.
	 * @param array<string, mixed>                 $classification From wiw_profile_classification().
	 * @param string                               $org_type       Organisation type name.
	 * @param array{city: string, country: string} $place         From wiw_profile_place().
	 * @return array<int, array{label: string, value: string, source_url: string, source_host: string, retrieved: string, retrieved_iso: string}>
	 */
	function wiw_key_fact_rows( array $facts, array $classification, string $org_type, array $place ): array {
		$name       = static fn ( $part ): string => is_array( $part ) ? (string) ( $part['name'] ?? '' ) : '';
		$classified = array(
			'industry' => array(
				array( __( 'Sector', 'whoiswho' ), $name( $classification['sector'] ?? null ) ),
				array( __( 'Industry', 'whoiswho' ), $name( $classification['industry'] ?? null ) ),
			),
			'sector'   => array( array( __( 'Sector', 'whoiswho' ), $name( $classification['sector'] ?? null ) ) ),
			'org_type' => array( array( __( 'Organisation type', 'whoiswho' ), $org_type ) ),
			'hq_city'  => array( array( __( 'City', 'whoiswho' ), $place['city'] ) ),
		);
		$rows       = array();
		$shown      = array();
		foreach ( $facts as $key => $fact ) {
			if ( in_array( $key, array( 'bio', 'summary', 'achievement', 'influence', 'legacy' ), true ) ) {
				continue;
			}
			$source = esc_url_raw( (string) ( $fact['source_url'] ?? '' ) );
			$when   = strtotime( (string) ( $fact['retrieved_at'] ?? '' ) );
			if ( '' === $source || false === $when ) {
				continue;
			}
			if ( isset( $classified[ $key ] ) ) {
				$pairs = $classified[ $key ];
			} else {
				$value = $fact['value'] ?? '';
				$pairs = array( array( ucfirst( str_replace( '_', ' ', (string) $key ) ), is_array( $value ) ? implode( ', ', array_filter( $value, 'is_string' ) ) : (string) $value ) );
			}
			$host = (string) wp_parse_url( $source, PHP_URL_HOST );
			foreach ( $pairs as $pair ) {
				if ( '' === trim( $pair[1] ) || isset( $shown[ $pair[0] ] ) ) {
					continue;
				}
				$shown[ $pair[0] ] = true;
				$rows[]            = array(
					'label'         => $pair[0],
					'value'         => $pair[1],
					'source_url'    => $source,
					'source_host'   => '' !== $host ? (string) preg_replace( '#^www\.#', '', $host ) : $source,
					'retrieved'     => wp_date( (string) get_option( 'date_format' ), $when ),
					'retrieved_iso' => gmdate( 'Y-m-d', $when ),
				);
			}
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

if ( ! function_exists( 'wiw_profile_classification' ) ) {
	/**
	 * The profile's place in the classification tree (REQ-009), read only through PublicProfile::classification(): country,
	 * sector, industry (each with name, slug and the filtered directory link) and the extra industries. Sector and industry are
	 * null when the profile is unclassified; country is null when no country is known.
	 *
	 * @param PublicProfile $profile Profile.
	 * @return array{country: array{name: string, slug: string, code: string, url: string}|null, sector: array{name: string, slug: string, url: string}|null, industry: array{name: string, slug: string, url: string}|null, extra: list<array{name: string, slug: string, url: string}>}
	 */
	function wiw_profile_classification( PublicProfile $profile ): array {
		return $profile->classification();
	}
}//end if

if ( ! function_exists( 'wiw_profile_org_type' ) ) {
	/**
	 * The organisation type's display name, or an empty string (REQ-009). Read through PublicProfile::org_type().
	 *
	 * @param PublicProfile $profile Profile.
	 */
	function wiw_profile_org_type( PublicProfile $profile ): string {
		$type = $profile->org_type();
		return null === $type ? '' : $type['name'];
	}
}

if ( ! function_exists( 'wiw_profile_place' ) ) {
	/**
	 * The profile's city and country names (REQ-009), each an empty string when unknown. Read through PublicProfile::place().
	 *
	 * @param PublicProfile $profile Profile.
	 * @return array{city: string, country: string}
	 */
	function wiw_profile_place( PublicProfile $profile ): array {
		$place = $profile->place();
		return array(
			'city'    => (string) ( $place['city'] ?? '' ),
			'country' => (string) ( $place['country'] ?? '' ),
		);
	}
}//end if

if ( ! function_exists( 'wiw_profile_crumbs' ) ) {
	/**
	 * The profile breadcrumb (REQ-009, REQ-003): Country › Sector › Industry › Name, each crumb but the last linking to the
	 * directory filtered by it. An unclassified profile gives Country › Name; with no country it is Directory › Name. A middle
	 * crumb (sector, industry) carries mid = true so phones can fold it into "…".
	 *
	 * @param string               $name           Profile name.
	 * @param array<string, mixed> $classification As returned by wiw_profile_classification().
	 * @return array<int, array{label: string, url: string, mid: bool}>
	 */
	function wiw_profile_crumbs( string $name, array $classification ): array {
		$crumb   = static function ( $part ): ?array {
			if ( ! is_array( $part ) || ! isset( $part['name'] ) || '' === trim( (string) $part['name'] ) ) {
				return null;
			}
			return array(
				'label' => (string) $part['name'],
				'url'   => isset( $part['url'] ) ? (string) $part['url'] : '',
			);
		};
		$country = $crumb( $classification['country'] ?? null );
		$out     = array();
		if ( null === $country ) {
			$out[] = array(
				'label' => __( 'Directory', 'whoiswho' ),
				'url'   => home_url( '/directory/' ),
				'mid'   => false,
			);
		} else {
			$out[] = $country + array( 'mid' => false );
			foreach ( array( 'sector', 'industry' ) as $level ) {
				$part = $crumb( $classification[ $level ] ?? null );
				if ( null !== $part ) {
					$out[] = $part + array( 'mid' => true );
				}
			}
		}
		$out[] = array(
			'label' => $name,
			'url'   => '',
			'mid'   => false,
		);
		return $out;
	}
}//end if

if ( ! function_exists( 'wiw_profile_crumbs_html' ) ) {
	/**
	 * Breadcrumb markup (REQ-009, REQ-043): an ordered list in a labelled nav, the last crumb marked aria-current="page". The
	 * middle crumbs stay in the markup for screen readers; on phones CSS hides them visually and shows a decorative "…".
	 *
	 * @param array<int, array{label: string, url: string, mid: bool}> $crumbs Crumbs from wiw_profile_crumbs().
	 */
	function wiw_profile_crumbs_html( array $crumbs ): string {
		$last   = count( $crumbs ) - 1;
		$items  = '';
		$folded = false;
		foreach ( $crumbs as $i => $c ) {
			if ( $c['mid'] && ! $folded ) {
				$items .= '<li class="crumbs-fold" aria-hidden="true"><span class="crumbs-sep">›</span><span>…</span></li>';
				$folded = true;
			}
			$sep    = $i > 0 ? '<span class="crumbs-sep" aria-hidden="true">›</span>' : '';
			$class  = $c['mid'] ? ' class="crumbs-mid"' : '';
			$inner  = $i === $last || '' === $c['url']
				? '<span' . ( $i === $last ? ' aria-current="page"' : '' ) . '>' . esc_html( $c['label'] ) . '</span>'
				: '<a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['label'] ) . '</a>';
			$items .= '<li' . $class . '>' . $sep . $inner . '</li>';
		}
		return '<nav class="crumbs crumbs--profile' . ( $folded ? ' crumbs--foldable' : '' ) . '" aria-label="' . esc_attr__( 'Breadcrumb', 'whoiswho' ) . '"><ol class="crumbs-list">' . $items . '</ol></nav>';
	}
}//end if

if ( ! function_exists( 'wiw_profile_display_setting' ) ) {
	/**
	 * A profile display setting from the plugin's settings service, limited to its allowed values; the default when the plugin
	 * or the key is missing (REQ-144, REQ-203).
	 *
	 * @param string   $key      Setting key.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Default.
	 */
	function wiw_profile_display_setting( string $key, array $allowed, string $fallback ): string {
		if ( ! class_exists( SettingsService::class ) ) {
			return $fallback;
		}
		try {
			$value = (string) SettingsService::for_site()->get( $key );
		} catch ( \InvalidArgumentException $e ) {
			return $fallback;
		}
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}//end if

if ( ! function_exists( 'wiw_profile_key_facts_mode' ) ) {
	/**
	 * How the key facts and sources show on the public page (REQ-144, owner 2026-11-14): show, collapsed or hidden (default).
	 */
	function wiw_profile_key_facts_mode(): string {
		return wiw_profile_display_setting( 'profile_key_facts', array( 'show', 'collapsed', 'hidden' ), 'hidden' );
	}
}

if ( ! function_exists( 'wiw_profile_credit_mode' ) ) {
	/**
	 * How an image credit shows (REQ-203, REQ-162): full (the line under the header), compact (an "i" button over the photo plus
	 * the Image credits line at the foot) or hidden. The setting hidden_when_not_required hides only an image whose licence asks
	 * for no attribution (CC0, the public domain mark) or that has no licence (owner-supplied); CC BY and CC BY-SA stay compact.
	 *
	 * @param array<string, mixed>|null $image Image from PublicProfile::image().
	 */
	function wiw_profile_credit_mode( ?array $image ): string {
		if ( null === $image || ( '' === (string) ( $image['credit'] ?? '' ) && '' === (string) ( $image['licence'] ?? '' ) ) ) {
			return 'hidden';
		}
		$mode = wiw_profile_display_setting( 'profile_image_credit', array( 'full', 'compact', 'hidden_when_not_required' ), 'compact' );
		if ( 'hidden_when_not_required' !== $mode ) {
			return $mode;
		}
		return in_array( (string) ( $image['licence'] ?? '' ), array( '', 'CC0-1.0', 'PDM-1.0' ), true ) ? 'hidden' : 'compact';
	}
}//end if

if ( ! function_exists( 'wiw_credit_text' ) ) {
	/**
	 * The credit text without a trailing licence label (issue 829, REQ-203): "Jane Doe, CC BY 4.0" becomes "Jane Doe", so the licence
	 * link that follows is not shown twice.
	 *
	 * @param string $credit Credit text.
	 */
	function wiw_credit_text( string $credit ): string {
		$licence = '(?:CC[\s-]?0(?:[\s-]?1\.0)?|CC[\s-]BY(?:[\s-](?:SA|ND|NC(?:[\s-](?:SA|ND))?))?(?:[\s-]\d\.\d)?|Public\s+domain(?:\s+mark)?(?:\s+1\.0)?|PDM(?:[\s-]1\.0)?)';
		$out     = preg_replace( '/(?:^|[\s,;:·•|\/(–—-]+)\(?\s*' . $licence . '\s*\)?[\s.]*$/iu', '', trim( $credit ) );
		return trim( is_string( $out ) ? $out : $credit, " \t\n\r\0\x0B,;:·•|/(–—-" );
	}
}

if ( ! function_exists( 'wiw_profile_credit_html' ) ) {
	/**
	 * The credit line content (REQ-203, D-024): "Photo:" or "Logo:", the credit (linked to the source page when known) and the
	 * licence (linked to its deed when known). Empty when there is nothing to credit. Every part is escaped here.
	 *
	 * @param array<string, mixed> $image Image from PublicProfile::image().
	 */
	function wiw_profile_credit_html( array $image ): string {
		$credit  = wiw_credit_text( (string) ( $image['credit'] ?? '' ) );
		$licence = (string) ( $image['licence'] ?? '' );
		if ( '' === $credit && '' === $licence ) {
			return '';
		}
		$source = (string) ( $image['source_url'] ?? '' );
		$deed   = (string) ( $image['licence_url'] ?? '' );
		$parts  = array();
		if ( '' !== $credit ) {
			$parts[] = '' !== $source
				? '<a href="' . esc_url( $source ) . '" rel="nofollow noopener">' . esc_html( $credit ) . '</a>'
				: esc_html( $credit );
		}
		if ( '' !== $licence ) {
			$parts[] = '' !== $deed
				? '<a href="' . esc_url( $deed ) . '" rel="license nofollow noopener">' . esc_html( wiw_licence_label( $licence ) ) . '</a>'
				: esc_html( wiw_licence_label( $licence ) );
		}
		$lead = 'logo' === ( $image['kind'] ?? '' ) ? __( 'Logo:', 'whoiswho' ) : __( 'Photo:', 'whoiswho' );
		return esc_html( $lead ) . ' ' . implode( ' <span aria-hidden="true">·</span> ', $parts );
	}
}//end if

if ( ! function_exists( 'wiw_credit_kses' ) ) {
	/**
	 * The tags a credit line may hold, for wp_kses.
	 *
	 * @return array<string, array<string, bool>>
	 */
	function wiw_credit_kses(): array {
		return array(
			'a'    => array(
				'href' => true,
				'rel'  => true,
			),
			'span' => array(
				'class'       => true,
				'aria-hidden' => true,
			),
			'p'    => array( 'class' => true ),
		);
	}
}

if ( ! function_exists( 'wiw_crumbs_kses' ) ) {
	/**
	 * The tags the breadcrumb may hold, for wp_kses.
	 *
	 * @return array<string, array<string, bool>>
	 */
	function wiw_crumbs_kses(): array {
		return array(
			'nav'  => array(
				'class'      => true,
				'aria-label' => true,
			),
			'ol'   => array( 'class' => true ),
			'li'   => array(
				'class'       => true,
				'aria-hidden' => true,
			),
			'a'    => array( 'href' => true ),
			'span' => array(
				'class'        => true,
				'aria-hidden'  => true,
				'aria-current' => true,
			),
		);
	}
}//end if

if ( ! function_exists( 'wiw_profile_foot_credit' ) ) {
	/**
	 * The "Image credits" line at the foot of a profile (REQ-203): present when the credit is compact, so the attribution stays on
	 * the page as text even before the "i" button is opened (and without scripts). Empty in the full and hidden modes.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	function wiw_profile_foot_credit( PublicProfile $profile ): string {
		$image = $profile->image();
		if ( null === $image || 'compact' !== wiw_profile_credit_mode( $image ) ) {
			return '';
		}
		$line = wiw_profile_credit_html( $image );
		return '' === $line ? '' : '<p class="profile-credits"><span class="profile-credits-h">' . esc_html__( 'Image credits', 'whoiswho' ) . '</span> ' . $line . '</p>';
	}
}//end if

if ( ! function_exists( 'wiw_profile_where' ) ) {
	/**
	 * The header's place line (REQ-009, REQ-003): city and country, industry, then the kind of profile. Read through place() and
	 * classification(), never the old text fields.
	 *
	 * @param PublicProfile $profile Profile.
	 * @param string        $skip    A value already shown in the role line, left out here.
	 * @return string[]
	 */
	function wiw_profile_where( PublicProfile $profile, string $skip = '' ): array {
		$place    = wiw_profile_place( $profile );
		$class    = wiw_profile_classification( $profile );
		$industry = is_array( $class['industry'] ) ? $class['industry']['name'] : '';
		$country  = '' !== $place['country'] ? $place['country'] : ( is_array( $class['country'] ) ? $class['country']['name'] : '' );
		$kind     = wiw_profile_kind( $profile->entity_type );
		return array_values(
			array_filter(
				array(
					implode( ', ', array_filter( array( $place['city'], $country ) ) ),
					$industry !== $skip ? $industry : '',
					$kind['label'],
				)
			)
		);
	}
}//end if

if ( ! function_exists( 'wiw_profile_role' ) ) {
	/**
	 * The header and card role line (REQ-003, REQ-009): the current title, else the organisation type, else the person's field.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	function wiw_profile_role( PublicProfile $profile ): string {
		foreach ( array( wiw_profile_fact( $profile, 'current_title' ), wiw_profile_org_type( $profile ), wiw_profile_fact( $profile, 'field' ) ) as $value ) {
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}
}
