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
	 * The users routes are never available to anyone who cannot list users.
	 *
	 * @param array<string, mixed> $endpoints Routes.
	 * @return array<string, mixed>
	 */
	public static function hide_user_routes( array $endpoints ): array {
		if ( current_user_can( 'list_users' ) ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
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
