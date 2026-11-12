<?php
/**
 * Builds the page title, description, canonical, robots and share-card tags for a profile, a directory list and the pages that
 * must not be indexed (REQ-006, REQ-044, REQ-217). A profile's tags are built only from PublicProfile, so a page that is not
 * public can never be described to a crawler. Pure data in, data out: Output renders and hooks it. The brand name and edition
 * come from the theme through filters, never from a template or from here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Seo;

use WhoIsWho\Core\Directory\PublicProfile;

/**
 * SEO tag builder.
 */
final class Tags {

	/**
	 * Fixed description sentence per list.
	 *
	 * @var array<string, array{0: string, 1: string}> List => title, description.
	 */
	private const LISTS = array(
		'all'           => array( 'Directory', 'Browse the verified public record of people, entrepreneurs, businesses and institutions across Africa.' ),
		'people'        => array( 'People', 'Browse people on the public record across Africa, each fact shown with its source.' ),
		'entrepreneurs' => array( 'Entrepreneurs', 'Browse entrepreneurs on the public record across Africa, each fact shown with its source.' ),
		'businesses'    => array( 'Businesses', 'Browse businesses on the public record across Africa, each fact shown with its source.' ),
		'institutions'  => array( 'Institutions', 'Browse institutions on the public record across Africa, each fact shown with its source.' ),
	);

	/**
	 * The brand name, from theme configuration.
	 */
	public static function brand(): string {
		$site = get_bloginfo( 'name' );
		$name = apply_filters( 'wiw_brand_name', $site );
		return is_string( $name ) && '' !== $name ? $name : $site;
	}

	/**
	 * Tags for a profile.
	 *
	 * @param PublicProfile $profile Profile (already filtered by state).
	 * @return array<string, mixed> title, description, canonical, robots (string or null), og (property => content).
	 */
	public function for_profile( PublicProfile $profile ): array {
		$brand = self::brand();
		$role  = $this->role_line( $profile );
		$title = $profile->name . ( '' !== $role ? ', ' . $role : '' ) . ' | ' . $brand;

		$text = $this->first_sentence( (string) ( $profile->facts['bio']['value'] ?? $profile->facts['summary']['value'] ?? '' ) );
		if ( '' === $text ) {
			$org  = (string) ( $profile->facts['current_organisation']['value'] ?? '' );
			$city = (string) ( $profile->facts['hq_city']['value'] ?? '' );
			$text = trim( $profile->name . ( '' !== $role ? ' is ' . $role : '' ) . ( '' !== $org ? ' at ' . $org : '' ) . ( '' !== $city ? ' in ' . $city : '' ), ' ' ) . '.';
		}
		$description = $text;
		if ( null !== $profile->label ) {
			$description = $profile->label . ' ' . $text;
		}

		$og    = array(
			'og:type'        => 'organisation' === $profile->entity_type || 'institution' === $profile->entity_type ? 'website' : 'profile',
			'og:site_name'   => $brand,
			'og:title'       => $profile->name,
			'og:description' => $this->line_under( $brand, $role, $profile->label, $text ),
			'og:url'         => $profile->url,
		);
		$image = $this->image_url( $profile );
		if ( '' !== $image ) {
			$og['og:image'] = $image;
		}
		return array(
			'title'       => $title,
			'description' => $description,
			'canonical'   => $profile->url,
			'robots'      => $profile->noindex ? 'noindex' : null,
			'og'          => $profile->policy->share_card ? $og : array(),
		);
	}

	/**
	 * Tags for a directory list page. Filtered and sorted views share the unfiltered list's canonical; a later page carries its own.
	 *
	 * @param string $list_name     List name (all, people, entrepreneurs, businesses, institutions).
	 * @param string $list_url Address of the unfiltered list.
	 * @param int    $page     Page number.
	 * @return array<string, mixed>
	 */
	public function for_list( string $list_name, string $list_url, int $page = 1 ): array {
		$brand     = self::brand();
		$entry     = self::LISTS[ $list_name ] ?? self::LISTS['all'];
		$canonical = trailingslashit( $list_url );
		if ( $page > 1 ) {
			$canonical .= 'page/' . $page . '/';
		}
		return array(
			'title'       => $entry[0] . ( $page > 1 ? ' (page ' . $page . ')' : '' ) . ' | ' . $brand,
			'description' => $entry[1],
			'canonical'   => $canonical,
			'robots'      => null,
			'og'          => array(
				'og:type'        => 'website',
				'og:site_name'   => $brand,
				'og:title'       => $entry[0],
				'og:description' => $entry[1],
				'og:url'         => $canonical,
			),
		);
	}

	/**
	 * Tags for a page that must not be indexed (search, done pages, single-use link pages, the forms).
	 *
	 * @param string $title Plain page title.
	 * @return array<string, mixed>
	 */
	public function for_noindex( string $title ): array {
		return array(
			'title'       => $title . ' | ' . self::brand(),
			'description' => '',
			'canonical'   => '',
			'robots'      => 'noindex, nofollow',
			'og'          => array(),
		);
	}

	/**
	 * Render tags as escaped HTML for the head.
	 *
	 * @param array<string, mixed> $tags Output of one of the builders.
	 */
	public function render( array $tags ): string {
		$html = '';
		if ( ! empty( $tags['description'] ) ) {
			$html .= '<meta name="description" content="' . esc_attr( (string) $tags['description'] ) . '">' . "\n";
		}
		if ( ! empty( $tags['robots'] ) ) {
			$html .= '<meta name="robots" content="' . esc_attr( (string) $tags['robots'] ) . '">' . "\n";
		}
		if ( ! empty( $tags['canonical'] ) ) {
			$html .= '<link rel="canonical" href="' . esc_url( (string) $tags['canonical'] ) . '">' . "\n";
		}
		foreach ( (array) ( $tags['og'] ?? array() ) as $property => $content ) {
			$html .= '<meta property="' . esc_attr( (string) $property ) . '" content="' . esc_attr( (string) $content ) . '">' . "\n";
		}
		if ( ! empty( $tags['og'] ) ) {
			$html .= '<meta name="twitter:card" content="' . ( isset( $tags['og']['og:image'] ) ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		}
		return $html;
	}

	/**
	 * Title or industry line of a profile.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	private function role_line( PublicProfile $profile ): string {
		foreach ( array( 'current_title', 'org_type', 'industry' ) as $key ) {
			$value = $profile->facts[ $key ]['value'] ?? '';
			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * The first sentence of a text, at most 160 characters.
	 *
	 * @param string $text Text.
	 */
	private function first_sentence( string $text ): string {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) ?? '' );
		if ( '' === $text ) {
			return '';
		}
		if ( preg_match( '/^(.+?[.!?])(\s|$)/u', $text, $m ) ) {
			$text = $m[1];
		}
		return mb_strlen( $text ) > 160 ? rtrim( mb_substr( $text, 0, 157 ) ) . '...' : $text;
	}

	/**
	 * The line under the title on a share card: brand, role, the pending label when it applies. Never a trust or Sponsored label.
	 *
	 * @param string      $brand Brand.
	 * @param string      $role  Title or industry.
	 * @param string|null $label Pending label.
	 * @param string      $text  Description.
	 */
	private function line_under( string $brand, string $role, ?string $label, string $text ): string {
		$parts = array_filter( array( $brand, $role, $label ), static fn ( $p ): bool => is_string( $p ) && '' !== $p );
		return implode( ' - ', $parts ) . ( null === $label ? ( '' !== $text ? '. ' . $text : '' ) : '' );
	}

	/**
	 * Portrait or logo URL, else the generic brand card the theme supplies; empty when neither exists.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	private function image_url( PublicProfile $profile ): string {
		if ( null !== $profile->image_id ) {
			$url = wp_get_attachment_image_url( $profile->image_id, 'large' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}
		$fallback = apply_filters( 'wiw_share_fallback_image', '' );
		return is_string( $fallback ) ? $fallback : '';
	}
}
