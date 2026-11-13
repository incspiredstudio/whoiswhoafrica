<?php
/**
 * Stable page URLs (REQ-260). Editing a page never changes its address. When an Administrator changes a slug or parent, the old
 * address is remembered and answers a 301 to the new one (core does this for posts but not for pages). Fixed routes (About, FAQ,
 * the award rules, Nominate and the legal pages) cannot be moved or deleted by anyone but an Administrator. Page revisions stay on.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Pages;

/**
 * Page routes.
 */
final class PageRoutes {

	public const OLD_URI_META = '_wiw_old_page_uri';

	/**
	 * Whether the listeners are attached (one set, however many times the module registers).
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_filter( 'wp_insert_post_data', array( $this, 'keep_fixed_route' ), 10, 2 );
		add_action( 'post_updated', array( $this, 'remember_old_address' ), 10, 3 );
		add_filter( 'pre_trash_post', array( $this, 'protect_fixed_route' ), 10, 2 );
		add_filter( 'pre_delete_post', array( $this, 'protect_fixed_route' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'redirect_old_address' ), 1 );
		add_filter( 'wp_revisions_to_keep', array( $this, 'keep_page_revisions' ), 99, 2 );
	}

	/**
	 * The fixed routes: page paths that other pages, menus and printed material link to.
	 *
	 * @return string[]
	 */
	public static function fixed_paths(): array {
		$paths = apply_filters(
			'wiw_fixed_page_paths',
			array( 'about', 'faq', 'awards/rules', 'nominate', 'get-listed', 'contact', 'privacy', 'terms', 'cookies', 'data-rights', 'complaints' )
		);
		return is_array( $paths ) ? array_values( array_map( 'strval', $paths ) ) : array();
	}

	/**
	 * Whether a page sits on a fixed route.
	 *
	 * @param int $page_id Page id.
	 */
	private function is_fixed( int $page_id ): bool {
		$post = get_post( $page_id );
		return $post instanceof \WP_Post && 'page' === $post->post_type && in_array( get_page_uri( $post ), self::fixed_paths(), true );
	}

	/**
	 * A fixed route keeps its slug and parent when anyone but an Administrator saves the page.
	 *
	 * @param array<string, mixed> $data    Slashed data about to be saved.
	 * @param array<string, mixed> $postarr Raw arguments.
	 * @return array<string, mixed>
	 */
	public function keep_fixed_route( $data, $postarr ): array {
		$data = (array) $data;
		$id   = (int) ( $postarr['ID'] ?? 0 );
		if ( $id < 1 || 'page' !== ( $data['post_type'] ?? '' ) || current_user_can( 'manage_options' ) || ! $this->is_fixed( $id ) ) {
			return $data;
		}
		$current = get_post( $id );
		if ( $current instanceof \WP_Post ) {
			$data['post_name']   = $current->post_name;
			$data['post_parent'] = $current->post_parent;
		}
		return $data;
	}

	/**
	 * Refuse to trash or delete a fixed route unless the user is an Administrator.
	 *
	 * @param mixed    $check Earlier answer.
	 * @param \WP_Post $post  Post.
	 * @return mixed
	 */
	public function protect_fixed_route( $check, $post ) {
		if ( null === $check && 'page' === $post->post_type && ! current_user_can( 'manage_options' ) && $this->is_fixed( $post->ID ) ) {
			return false;
		}
		return $check;
	}

	/**
	 * Remember the old address of a published page whose path changed.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $after   After.
	 * @param \WP_Post $before  Before.
	 */
	public function remember_old_address( int $post_id, \WP_Post $after, \WP_Post $before ): void {
		if ( 'page' !== $after->post_type || 'publish' !== $before->post_status ) {
			return;
		}
		$old = $this->path_of( $before );
		$new = $this->path_of( $after );
		if ( $old === $new ) {
			return;
		}
		// An address that is live again is no longer an old one (no loops).
		delete_post_meta( $post_id, self::OLD_URI_META, $new );
		if ( '' !== $old && ! in_array( $old, (array) get_post_meta( $post_id, self::OLD_URI_META, false ), true ) ) {
			add_post_meta( $post_id, self::OLD_URI_META, $old );
		}
	}

	/**
	 * The path of a page from its own slug and its parents' slugs.
	 *
	 * @param \WP_Post $page Page.
	 */
	private function path_of( \WP_Post $page ): string {
		$parts  = array( $page->post_name );
		$parent = (int) $page->post_parent;
		$guard  = 0;
		while ( $parent > 0 && $guard < 10 ) {
			$up = get_post( $parent );
			if ( ! $up instanceof \WP_Post ) {
				break;
			}
			array_unshift( $parts, $up->post_name );
			$parent = (int) $up->post_parent;
			++$guard;
		}
		return implode( '/', array_filter( $parts, static fn ( string $p ): bool => '' !== $p ) );
	}

	/**
	 * Where an old page address should go, or null. Only a published page answers.
	 *
	 * @param string $path Request path without slashes at the ends.
	 */
	public function redirect_target( string $path ): ?string {
		$path = trim( $path, '/' );
		if ( '' === $path ) {
			return null;
		}
		$found = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::OLD_URI_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one lookup on a 404.
				'meta_value'     => $path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one lookup on a 404.
			)
		);
		if ( array() === $found ) {
			return null;
		}
		$id = (int) $found[0];
		return $this->path_of( get_post( $id ) ) === $path ? null : (string) get_permalink( $id );
	}

	/**
	 * A 404 on an old page address becomes a permanent redirect.
	 */
	public function redirect_old_address(): void {
		if ( ! is_404() ) {
			return;
		}
		global $wp;
		$path   = $wp instanceof \WP ? (string) $wp->request : '';
		$target = $this->redirect_target( rawurldecode( $path ) );
		if ( null !== $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}

	/**
	 * Revisions stay on for pages: a site that turned them off gets a generous default back.
	 *
	 * @param mixed    $num  Revisions to keep.
	 * @param \WP_Post $post Post.
	 * @return mixed
	 */
	public function keep_page_revisions( $num, $post ) {
		if ( 'page' === $post->post_type && 0 === (int) $num ) {
			return 50;
		}
		return $num;
	}
}
