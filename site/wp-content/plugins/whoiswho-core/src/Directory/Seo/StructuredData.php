<?php
/**
 * Schema.org JSON-LD for a profile page (REQ-006, REQ-139, REQ-140, REQ-217): Person for individuals and entrepreneurs,
 * Organization for businesses and institutions. It is built only from the allowlisted, sourced facts of the public read model,
 * never carries an email, phone, address, age or any forbidden attribute, and is not output at all for a page whose state policy
 * has no schema (pending review, unpublished, anything not public). Schema another plugin tries to add to such a page is
 * emptied through the filters of the common SEO plugins.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Seo;

use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Url\ProfileRoute;

/**
 * Structured data.
 */
final class StructuredData {

	/**
	 * Hook the output and the filters.
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'print_schema' ), 2 );
		add_filter( 'wpseo_json_ld_output', array( $this, 'block_foreign_output' ), 99 );
		add_filter( 'wpseo_schema_graph', array( $this, 'block_foreign_graph' ), 99 );
		add_filter( 'rank_math/json_ld', array( $this, 'block_foreign_graph' ), 99 );
		add_filter( 'aioseo_schema_output', array( $this, 'block_foreign_graph' ), 99 );
	}

	/**
	 * Print the markup for the profile page being served.
	 */
	public function print_schema(): void {
		$profile = $this->current_profile();
		if ( null !== $profile ) {
			echo $this->render( $profile ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with the hex flags; no markup can break out of the script element.
		}
	}

	/**
	 * The data for a profile, or null when its state allows none.
	 *
	 * @param PublicProfile $profile Profile (from the read model).
	 * @return array<string, mixed>|null
	 */
	public function for_profile( PublicProfile $profile ): ?array {
		if ( ! $profile->policy->has_schema ) {
			return null;
		}
		$person = in_array( $profile->entity_type, array( 'individual', 'entrepreneur' ), true );
		$data   = array(
			'@context' => 'https://schema.org',
			'@type'    => $person ? 'Person' : 'Organization',
			'name'     => $profile->name,
			'url'      => $profile->url,
		);
		if ( $person ) {
			$this->put( $data, 'jobTitle', $this->text( $profile, 'current_title' ) );
			$org = $this->text( $profile, 'current_organisation' );
			if ( '' !== $org ) {
				$data['worksFor'] = array(
					'@type' => 'Organization',
					'name'  => $org,
				);
			}
			$this->put( $data, 'knowsAbout', $this->text( $profile, 'industry' ) );
			$this->put( $data, 'award', $this->items( $profile, 'published_awards' ) );
			$roles = $this->items( $profile, 'public_record_roles' );
			if ( array() !== $roles ) {
				$data['hasOccupation'] = array_map(
					static fn ( string $role ): array => array(
						'@type' => 'Occupation',
						'name'  => $role,
					),
					$roles
				);
			}
			$this->put( $data, 'sameAs', $this->urls( $this->text( $profile, 'official_website' ) ) );
		} else {
			$this->put( $data, 'description', $this->text( $profile, 'summary' ) );
			$this->put( $data, 'foundingDate', $this->text( $profile, 'year_founded' ) );
			$this->put( $data, 'sameAs', $this->urls( $this->text( $profile, 'website' ) ) );
		}//end if
		return $data;
	}

	/**
	 * The script element, or an empty string when the profile has no schema.
	 *
	 * @param PublicProfile $profile Profile.
	 */
	public function render( PublicProfile $profile ): string {
		$data = $this->for_profile( $profile );
		if ( null === $data ) {
			return '';
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		return false === $json ? '' : '<script type="application/ld+json">' . $json . "</script>\n";
	}

	/**
	 * Switch off another plugin's on/off schema filter on a profile page that carries none.
	 *
	 * @param mixed $output Value so far.
	 * @return mixed
	 */
	public function block_foreign_output( $output ) {
		return $this->foreign_blocked() ? false : $output;
	}

	/**
	 * Empty another plugin's schema graph on a profile page that carries none.
	 *
	 * @param mixed $graph Value so far.
	 * @return mixed
	 */
	public function block_foreign_graph( $graph ) {
		return $this->foreign_blocked() ? array() : $graph;
	}

	/**
	 * Whether the page being served is a profile page whose state allows no schema.
	 */
	private function foreign_blocked(): bool {
		$id   = (int) get_query_var( ProfileRoute::ID_VAR );
		$slug = get_query_var( ProfileRoute::QUERY_VAR );
		if ( $id < 1 ) {
			return is_string( $slug ) && '' !== $slug;
		}
		$profile = PublicProfile::find_by_id( $id );
		return null === $profile || ! $profile->policy->has_schema;
	}

	/**
	 * The profile of the page being served, when it is one.
	 */
	private function current_profile(): ?PublicProfile {
		$id = (int) get_query_var( ProfileRoute::ID_VAR );
		return $id > 0 ? PublicProfile::find_by_id( $id ) : null;
	}

	/**
	 * A sourced fact as text.
	 *
	 * @param PublicProfile $profile Profile.
	 * @param string        $key     Field.
	 */
	private function text( PublicProfile $profile, string $key ): string {
		$value = $profile->facts[ $key ]['value'] ?? '';
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * A sourced list fact as strings.
	 *
	 * @param PublicProfile $profile Profile.
	 * @param string        $key     Field.
	 * @return string[]
	 */
	private function items( PublicProfile $profile, string $key ): array {
		$value = $profile->facts[ $key ]['value'] ?? array();
		$items = is_array( $value ) ? $value : ( is_string( $value ) && '' !== $value ? array( $value ) : array() );
		return array_values( array_filter( array_map( static fn ( $item ): string => is_scalar( $item ) ? trim( (string) $item ) : '', $items ), static fn ( string $item ): bool => '' !== $item ) );
	}

	/**
	 * An address as a one-item list when it is a web address.
	 *
	 * @param string $url Address.
	 * @return string[]
	 */
	private function urls( string $url ): array {
		return 1 === preg_match( '#^https?://#i', $url ) ? array( esc_url_raw( $url ) ) : array();
	}

	/**
	 * Set a key when the value is not empty.
	 *
	 * @param array<string, mixed>     $data  Data.
	 * @param string                   $key   Key.
	 * @param string|array<int, mixed> $value Value.
	 */
	private function put( array &$data, string $key, $value ): void {
		if ( '' !== $value && array() !== $value ) {
			$data[ $key ] = $value;
		}
	}
}
