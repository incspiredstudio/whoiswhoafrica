<?php
/**
 * Wires the WP Super Cache page-cache plugin to the site's rules (REQ-209, REQ-121, REQ-217). The plugin (pinned through composer)
 * is loaded here only when the secrets file turns the cache on (WP_CACHE), so it can never be switched on or off from wp-admin
 * and its settings screen is hidden: its settings come from the deployed wp-cache-config.php. Each request is run through
 * PageCachePolicy; a request the policy refuses is marked DONOTCACHEPAGE, so it is neither stored nor served from a stored copy.
 * An unpublish purges the URL through the SuperCachePurgeClient.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cache\PageCache;

/**
 * Page cache setup.
 */
final class PageCache {

	/**
	 * Policy.
	 *
	 * @var PageCachePolicy
	 */
	private PageCachePolicy $policy;

	/**
	 * Constructor.
	 *
	 * @param PageCachePolicy $policy Policy.
	 */
	public function __construct( PageCachePolicy $policy ) {
		$this->policy = $policy;
	}

	/**
	 * Load the plugin from the plugins folder when the cache is switched on. Called from the core plugin file.
	 *
	 * @param string $plugins_dir Plugins folder.
	 */
	public static function load( string $plugins_dir ): void {
		if ( ! defined( 'WP_CACHE' ) || true !== WP_CACHE ) {
			return;
		}
		$file = rtrim( $plugins_dir, '/\\' ) . '/wp-super-cache/wp-cache.php';
		if ( ! function_exists( 'wp_cache_phase2' ) && is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Attach hooks.
	 */
	public static function register(): void {
		$cache = new self( new PageCachePolicy() );
		add_filter( 'wiw_purge_client', array( $cache, 'purge_client' ) );
		add_action( 'template_redirect', array( $cache, 'apply' ), 99999 );
		add_action( 'wp_footer', array( $cache, 'apply' ), 1 );
		add_filter( 'do_createsupercache', array( $cache, 'allow_store' ) );
		add_action( 'admin_menu', array( $cache, 'hide_settings' ), 999 );
	}

	/**
	 * Use the page-cache purge client when the plugin is loaded.
	 *
	 * @param mixed $client Client chosen so far.
	 * @return mixed
	 */
	public function purge_client( $client ) {
		return SuperCachePurgeClient::available() ? new SuperCachePurgeClient() : $client;
	}

	/**
	 * Facts about the current request, for the policy.
	 *
	 * @return array{method: string, path: string, query: string, cookies: string[], status: int, noindex: bool, preview: bool, public_paths: string[]}
	 */
	public function facts(): array {
		$uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only split into path and query for a comparison.
		$parts    = explode( '?', $uri, 2 );
		$method   = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared with GET only.
		$noindex  = false;
		$response = headers_list();
		foreach ( $response as $header ) {
			if ( 0 === stripos( $header, 'X-Robots-Tag:' ) && false !== stripos( $header, 'noindex' ) ) {
				$noindex = true;
			}
		}
		$status = (int) http_response_code();
		return array(
			'method'       => $method,
			'path'         => $parts[0],
			'query'        => $parts[1] ?? '',
			'cookies'      => array_map( 'strval', array_keys( $_COOKIE ) ),
			'status'       => $status > 0 ? $status : 200,
			'noindex'      => $noindex || is_404(),
			'preview'      => is_preview() || is_customize_preview(),
			'public_paths' => array_map( 'strval', (array) apply_filters( 'wiw_page_cache_public_paths', PageCachePolicy::PUBLIC_PATHS ) ),
		);
	}

	/**
	 * Mark this request as not cacheable when the policy says no.
	 */
	public function apply(): void {
		if ( ! $this->policy->decide( $this->facts() )['cache'] && ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the constant the cache plugin reads.
		}
	}

	/**
	 * The plugin's own last check before it stores a page.
	 *
	 * @param mixed $allow Whether the plugin would store the page.
	 */
	public function allow_store( $allow ): bool {
		return ! empty( $allow ) && $this->policy->decide( $this->facts() )['cache'];
	}

	/**
	 * Hide the plugin's settings screen: its settings are code, not clicks.
	 */
	public function hide_settings(): void {
		remove_submenu_page( 'options-general.php', 'wpsupercache' );
	}
}
