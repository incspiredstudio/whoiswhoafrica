<?php
/**
 * The robots.txt the site serves (REQ-121, REQ-217). It allows every crawler everywhere and names the sitemap. It never
 * disallows a profile path: a crawler must be able to fetch a pending-review page to read its noindex tag, so blocking it would
 * stop the noindex from working. The filter runs last, so no other plugin can add a Disallow line. Generic WordPress hooks only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Seo;

/**
 * Robots.
 */
final class Robots {

	/**
	 * Attach hooks.
	 */
	public static function init(): void {
		add_filter( 'robots_txt', array( self::class, 'filter' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Replace whatever core or another plugin built with the fixed text.
	 *
	 * @param mixed $output Robots text built so far (ignored).
	 * @param mixed $is_public Whether the site is public (ignored: the site is public by design).
	 */
	public static function filter( $output = '', $is_public = true ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- hook signature.
		return self::text( home_url( '/sitemap.xml' ) );
	}

	/**
	 * The robots.txt text.
	 *
	 * @param string $sitemap_url Absolute URL of the sitemap index.
	 */
	public static function text( string $sitemap_url ): string {
		return "User-agent: *\nAllow: /\n\nSitemap: " . esc_url_raw( $sitemap_url ) . "\n";
	}
}
