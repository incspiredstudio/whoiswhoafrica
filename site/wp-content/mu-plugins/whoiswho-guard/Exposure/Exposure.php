<?php
/**
 * Closes the WordPress surfaces that could leak hidden content or users: REST, author archives and enumeration, feeds,
 * core sitemaps, oEmbed and embeds, attachment pages, and the sign-in error text. Uses only generic WordPress hooks.
 * Hidden content (status wiw_hidden) is never public, so core already answers 404 for its real URL, shortlink and preview;
 * the rules here keep every other door closed too.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Exposure;

/**
 * Exposure rules.
 */
final class Exposure {

	/**
	 * Attach hooks.
	 */
	public static function init(): void {
		add_filter( 'rest_authentication_errors', array( self::class, 'require_staff_for_rest' ), 99 );
		add_filter( 'rest_endpoints', array( self::class, 'hide_user_routes' ) );

		add_filter( 'wp_sitemaps_enabled', '__return_false' );

		add_action( 'template_redirect', array( self::class, 'close_surfaces' ), 0 );
		foreach ( array( 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments' ) as $hook ) {
			add_action( $hook, array( self::class, 'refuse_feed' ), 1 );
		}

		add_filter( 'login_errors', array( self::class, 'generic_login_error' ) );
		add_action( 'pre_get_posts', array( self::class, 'exclude_guarded_from_search' ) );

		// Guarded types (the profile type) have no door of their own: no REST, archive, feed, shortlink, embed or sitemap entry.
		add_filter( 'register_post_type_args', array( self::class, 'lock_guarded_type' ), 99, 2 );
		add_filter( 'pre_get_shortlink', array( self::class, 'no_shortlink_for_guarded' ), 10, 2 );
		add_filter( 'oembed_request_post_id', array( self::class, 'no_oembed_for_guarded' ), 99 );
		add_filter( 'wp_sitemaps_post_types', array( self::class, 'drop_guarded_from_sitemaps' ) );
		add_filter( 'the_posts', array( self::class, 'strip_guarded_from_public_queries' ), 99, 2 );
	}

	/**
	 * Force the closed settings on a guarded post type, whatever registered it.
	 *
	 * @param array<string, mixed> $args      Arguments.
	 * @param string               $post_type Post type.
	 * @return array<string, mixed>
	 */
	public static function lock_guarded_type( array $args, string $post_type ): array {
		if ( ! in_array( $post_type, \WhoIsWho\Guard\Guard::guarded_post_types(), true ) ) {
			return $args;
		}
		$args['show_in_rest']        = false;
		$args['has_archive']         = false;
		$args['publicly_queryable']  = false;
		$args['exclude_from_search'] = true;
		return $args;
	}

	/**
	 * No shortlink for a guarded post.
	 *
	 * @param mixed $shortlink Current value (false to use the default).
	 * @param int   $post_id   Post id.
	 * @return mixed
	 */
	public static function no_shortlink_for_guarded( $shortlink, $post_id ) {
		return self::is_guarded( (int) $post_id ) ? '' : $shortlink;
	}

	/**
	 * No oEmbed answer for a guarded post.
	 *
	 * @param int $post_id Post id found for the URL.
	 */
	public static function no_oembed_for_guarded( $post_id ): int {
		return self::is_guarded( (int) $post_id ) ? 0 : (int) $post_id;
	}

	/**
	 * Guarded types never enter core sitemaps (they are also off).
	 *
	 * @param array<string, mixed> $types Post types.
	 * @return array<string, mixed>
	 */
	public static function drop_guarded_from_sitemaps( array $types ): array {
		foreach ( \WhoIsWho\Guard\Guard::guarded_post_types() as $type ) {
			unset( $types[ $type ] );
		}
		return $types;
	}

	/**
	 * Last line of defence: a public (front-end) query never hands a guarded post to a template, a feed or a third-party plugin.
	 * Profiles reach pages only through the plugin's read model.
	 *
	 * @param array<int, \WP_Post> $posts Posts.
	 * @param \WP_Query            $query Query.
	 * @return array<int, \WP_Post>
	 */
	public static function strip_guarded_from_public_queries( array $posts, \WP_Query $query ): array {
		// admin-ajax.php counts as admin even for a visitor (wp_ajax_nopriv_* handlers of any plugin), so that is still public.
		$staff_side = is_admin() && ! ( wp_doing_ajax() && ! is_user_logged_in() );
		if ( $staff_side || ( defined( 'WP_CLI' ) && WP_CLI ) || $query->get( 'wiw_allow_guarded' ) ) {
			return $posts;
		}
		$guarded = \WhoIsWho\Guard\Guard::guarded_post_types();
		if ( array() === $guarded ) {
			return $posts;
		}
		return array_values( array_filter( $posts, static fn ( \WP_Post $post ): bool => ! in_array( $post->post_type, $guarded, true ) ) );
	}

	/**
	 * Whether a post id belongs to a guarded type.
	 *
	 * @param int $post_id Post id.
	 */
	private static function is_guarded( int $post_id ): bool {
		$type = $post_id > 0 ? get_post_type( $post_id ) : false;
		return false !== $type && in_array( $type, \WhoIsWho\Guard\Guard::guarded_post_types(), true );
	}

	/**
	 * No public REST: every route needs a signed-in user.
	 *
	 * @param mixed $result Earlier authentication result.
	 * @return mixed
	 */
	public static function require_staff_for_rest( $result ) {
		if ( null !== $result ) {
			return $result;
		}
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sign in is required.', 'whoiswho' ), array( 'status' => 401 ) );
		}
		return $result;
	}

	/**
	 * The users routes are never available to anyone who cannot list users. One exception: a signed-in user who can edit posts
	 * keeps exactly /wp/v2/users/me (their own record), which the block editor and dashboard need.
	 *
	 * @param array<string, mixed> $endpoints Routes.
	 * @return array<string, mixed>
	 */
	public static function hide_user_routes( array $endpoints ): array {
		if ( current_user_can( 'list_users' ) ) {
			return $endpoints;
		}
		$keep_me = is_user_logged_in() && current_user_can( 'edit_posts' );
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( $keep_me && '/wp/v2/users/me' === (string) $route ) {
				continue;
			}
			if ( 0 === strpos( (string) $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * Answer 404 for author pages, ?author= enumeration, attachment pages and embeds.
	 */
	public static function close_surfaces(): void {
		global $wp_query;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of the query string, nothing is stored.
		$author_probe = isset( $_GET['author'] ) || isset( $_GET['author_name'] );
		if ( $author_probe || is_author() || is_attachment() || is_embed() ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Feeds are off.
	 */
	public static function refuse_feed(): void {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		wp_die( esc_html__( 'Not found.', 'whoiswho' ), '', array( 'response' => 404 ) );
	}

	/**
	 * One plain message for every sign-in failure.
	 */
	public static function generic_login_error(): string {
		return esc_html__( 'Sign in failed. Check your details and try again.', 'whoiswho' );
	}

	/**
	 * Core search never returns guarded types or hidden content.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function exclude_guarded_from_search( \WP_Query $query ): void {
		if ( ! $query->is_main_query() || ! $query->is_search() || is_admin() ) {
			return;
		}
		$guarded = \WhoIsWho\Guard\Guard::guarded_post_types();
		if ( array() === $guarded ) {
			return;
		}
		$types = $query->get( 'post_type' );
		if ( empty( $types ) || 'any' === $types ) {
			$types = array_values( array_diff( get_post_types( array( 'exclude_from_search' => false ) ), $guarded ) );
		} else {
			$types = array_values( array_diff( (array) $types, $guarded ) );
		}
		$query->set( 'post_type', $types );
	}
}
