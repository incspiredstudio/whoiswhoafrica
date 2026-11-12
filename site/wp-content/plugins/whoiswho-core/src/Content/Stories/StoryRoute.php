<?php
/**
 * Story URLs and visibility on the public door (REQ-208, REQ-027): a story lives at /stories/:slug/ (core's old-slug redirect
 * keeps renamed stories working), and a story that is not public (draft, scheduled, pending, private, password-protected,
 * trashed) answers 404 to a visitor and is never offered to oEmbed. Staff see unpublished stories through the read model's
 * preview marker. The sitemap lists published stories only (Directory\Sitemap\Sitemap).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

/**
 * Story route.
 */
final class StoryRoute {

	/**
	 * Attach hooks.
	 */
	public static function hooks(): void {
		$self = new self();
		add_filter( 'post_link', array( $self, 'permalink' ), 10, 2 );
		add_action( 'init', array( self::class, 'add_rules' ) );
		add_action( 'template_redirect', array( $self, 'close_non_public' ), 0 );
		add_filter( 'oembed_request_post_id', array( $self, 'no_oembed_unless_public' ), 100 );
	}

	/**
	 * Add the single-story rewrite rule.
	 */
	public static function add_rules(): void {
		add_rewrite_rule( '^stories/([^/]+)/?$', 'index.php?name=$matches[1]', 'top' );
	}

	/**
	 * Story permalinks sit under /stories/. Only plain posts; pretty permalinks only.
	 *
	 * @param string   $url  Permalink.
	 * @param \WP_Post $post Post.
	 */
	public function permalink( $url, \WP_Post $post ): string {
		$url = (string) $url;
		if ( 'post' !== $post->post_type || '' === (string) get_option( 'permalink_structure' ) || '' === $post->post_name ) {
			return $url;
		}
		return home_url( '/stories/' . $post->post_name . '/' );
	}

	/**
	 * A password-protected story is not public: visitors get 404 (core would show the password form). Staff who can edit it keep
	 * the page. Drafts, scheduled and trashed stories already 404 in core's query.
	 */
	public function close_non_public(): void {
		global $wp_query;
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ( 'publish' === $post->post_status && '' === $post->post_password ) ) {
			return;
		}
		if ( current_user_can( 'edit_post', $post->ID ) && 'trash' !== $post->post_status ) {
			return;
		}
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * No oEmbed answer for a story that is not public.
	 *
	 * @param mixed $post_id Post id found for the URL.
	 */
	public function no_oembed_unless_public( $post_id ): int {
		$post_id = (int) $post_id;
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( $post instanceof \WP_Post && 'post' === $post->post_type && ( 'publish' !== $post->post_status || '' !== $post->post_password ) ) {
			return 0;
		}
		return $post_id;
	}
}
