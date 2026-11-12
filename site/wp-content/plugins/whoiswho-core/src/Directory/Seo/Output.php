<?php
/**
 * Writes the SEO tags into the page head and the document title (REQ-006, REQ-044, REQ-217). No SEO plugin is installed, so
 * this is the only source of title, description, canonical, robots and share tags; core's own canonical and robots output are
 * removed so nothing is written twice. The page kind is found from the main query: a profile (the profile route's id), a
 * search, a single-use link page, or a page another module marks noindex or as a list through the filters below.
 *
 * Filters: wiw_seo_noindex_request (bool, title), wiw_seo_list_context (array{list: string, url: string, page: int}|null).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Seo;

use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Platform\Links\LinkRoute;

/**
 * Head output.
 */
final class Output {

	/**
	 * Tag builder.
	 *
	 * @var Tags
	 */
	private Tags $tags;

	/**
	 * Constructor.
	 *
	 * @param Tags|null $tags Builder.
	 */
	public function __construct( ?Tags $tags = null ) {
		$this->tags = $tags ?? new Tags();
	}

	/**
	 * Hook the output.
	 */
	public function register(): void {
		add_action( 'wp', array( $this, 'take_over' ) );
		add_action( 'wp_head', array( $this, 'print_tags' ), 1 );
		add_filter( 'pre_get_document_title', array( $this, 'title' ), 20 );
	}

	/**
	 * Remove core's canonical and robots output on pages this class describes.
	 */
	public function take_over(): void {
		if ( null !== $this->current() ) {
			remove_action( 'wp_head', 'rel_canonical' );
			remove_action( 'wp_head', 'wp_robots', 1 );
		}
	}

	/**
	 * The tags for the page being served, or null when this page is not one this class describes.
	 *
	 * @return array<string, mixed>|null
	 */
	public function current(): ?array {
		if ( is_admin() ) {
			return null;
		}
		$profile_id = (int) get_query_var( ProfileRoute::ID_VAR );
		if ( $profile_id > 0 ) {
			$profile = PublicProfile::find_by_id( $profile_id );
			return null === $profile ? $this->tags->for_noindex( __( 'Not available', 'whoiswho' ) ) : $this->tags->for_profile( $profile );
		}
		if ( is_string( get_query_var( ProfileRoute::QUERY_VAR ) ) && '' !== get_query_var( ProfileRoute::QUERY_VAR ) ) {
			return $this->tags->for_noindex( __( 'Not available', 'whoiswho' ) );
		}
		if ( is_search() || '' !== (string) get_query_var( LinkRoute::QUERY_VAR ) || true === apply_filters( 'wiw_seo_noindex_request', false ) ) {
			return $this->tags->for_noindex( is_search() ? __( 'Search', 'whoiswho' ) : __( 'Page', 'whoiswho' ) );
		}
		$list = apply_filters( 'wiw_seo_list_context', null );
		if ( is_array( $list ) && isset( $list['list'], $list['url'] ) ) {
			return $this->tags->for_list( (string) $list['list'], (string) $list['url'], (int) ( $list['page'] ?? 1 ) );
		}
		return null;
	}

	/**
	 * Print the tags.
	 */
	public function print_tags(): void {
		$tags = $this->current();
		if ( null !== $tags ) {
			echo $this->tags->render( $tags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tags::render escapes every value.
		}
	}

	/**
	 * The document title.
	 *
	 * @param mixed $title Title so far.
	 * @return mixed
	 */
	public function title( $title ) {
		$tags = $this->current();
		return null === $tags ? $title : (string) $tags['title'];
	}
}
