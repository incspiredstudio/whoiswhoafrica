<?php
/**
 * Brand helper: templates, email templates and share cards read brand strings only through wiw_brand() (REQ-052, REQ-190).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_brand_config' ) ) {
	/**
	 * The brand configuration, read once per request.
	 *
	 * @return array<string, string>
	 */
	function wiw_brand_config(): array {
		static $config = null;
		if ( null === $config ) {
			$config = require dirname( __DIR__ ) . '/config/brand.php';
		}
		return $config;
	}
}

if ( ! function_exists( 'wiw_brand' ) ) {
	/**
	 * One brand value by key: name, long_name, edition, legal_name, tagline, domain.
	 *
	 * @param string $key Brand key.
	 * @return string Empty string for an unknown key.
	 */
	function wiw_brand( string $key ): string {
		$config = wiw_brand_config();
		return $config[ $key ] ?? '';
	}
}
