<?php
/**
 * Security headers and the CSP nonce (REQ-046). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Security;

use WhoIsWho\Guard\Headers\Headers;
use WhoIsWho\Tests\WpTestCase;

final class SecurityHeadersTest extends WpTestCase {

	public function test_REQ_046_every_response_carries_security_headers(): void {
		$public = Headers::headers( true, false, 'abc' );
		foreach ( array( 'Strict-Transport-Security', 'X-Content-Type-Options', 'Referrer-Policy', 'Permissions-Policy', 'Content-Security-Policy', 'X-Frame-Options' ) as $name ) {
			$this->assertArrayHasKey( $name, $public, $name );
		}
		$this->assertSame( 'nosniff', $public['X-Content-Type-Options'] );
		$this->assertStringContainsString( 'max-age=31536000', $public['Strict-Transport-Security'] );

		$http = Headers::headers( false, false, 'abc' );
		$this->assertArrayNotHasKey( 'Strict-Transport-Security', $http, 'HSTS is only sent over HTTPS' );

		$admin = Headers::headers( true, true, 'abc' );
		$this->assertArrayHasKey( 'Content-Security-Policy-Report-Only', $admin, 'wp-admin starts report-only' );
		$this->assertArrayNotHasKey( 'Content-Security-Policy', $admin );
		$this->assertArrayHasKey( 'X-Content-Type-Options', $admin );
	}

	public function test_REQ_046_csp_blocks_inline_script_without_nonce(): void {
		$csp = Headers::csp( 'NONCE123' );
		preg_match( '/script-src ([^;]+)/', $csp, $m );
		$this->assertStringContainsString( "'nonce-NONCE123'", $m[1], 'scripts need the nonce' );
		$this->assertStringNotContainsString( 'unsafe-inline', $m[1], 'no inline script without the nonce' );
		$this->assertStringNotContainsString( 'unsafe-eval', $m[1] );
		$this->assertStringContainsString( "frame-ancestors 'none'", $csp );
		$this->assertStringContainsString( "object-src 'none'", $csp );
		$this->assertStringContainsString( "base-uri 'self'", $csp );
	}

	public function test_REQ_046_extra_sources_come_from_one_config_and_apply(): void {
		$csp = Headers::csp( 'n', array( 'img-src' => array( 'https://cdn.example.org' ) ) );
		$this->assertStringContainsString( "img-src 'self' data: https://cdn.example.org", $csp );
		$this->assertSame( array( 'script-src', 'style-src', 'img-src', 'font-src', 'connect-src', 'frame-src', 'media-src' ), array_keys( Headers::sources() ) );
	}

	/**
	 * Signed in, the admin bar on public pages loads WordPress's Dashicons stylesheet, whose font is embedded as a data: URL, so
	 * font-src allows data: (and nothing else new) on public pages (issue 806).
	 */
	public function test_REQ_046_public_font_src_allows_the_admin_bar_data_font(): void {
		$dashicons = (string) file_get_contents( ABSPATH . WPINC . '/css/dashicons.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertStringContainsString( 'url("data:application/x-font-woff', $dashicons, 'the cause: the admin bar font is a data: URL' );
		$public = Headers::headers( true, false, 'abc' )['Content-Security-Policy'];
		$this->assertMatchesRegularExpression( "/(^|; )font-src 'self' data:(;|$)/", $public );
		$this->assertSame( array( 'data:' ), Headers::sources()['font-src'] );
	}

	public function test_REQ_046_the_nonce_helper_is_stable_per_request_and_reaches_script_tags(): void {
		$this->assertTrue( function_exists( 'wiw_csp_nonce' ) );
		$this->assertSame( wiw_csp_nonce(), wiw_csp_nonce() );
		$this->assertGreaterThanOrEqual( 16, strlen( wiw_csp_nonce() ) );

		$tag = wp_get_script_tag( array( 'src' => 'https://example.org/a.js' ) );
		$this->assertStringContainsString( 'nonce="' . wiw_csp_nonce() . '"', $tag );
		$inline = wp_get_inline_script_tag( 'console.log(1);' );
		$this->assertStringContainsString( 'nonce="' . wiw_csp_nonce() . '"', $inline );
	}

	public function test_REQ_046_cookies_are_secure_in_production_only(): void {
		$this->assertFalse( apply_filters( 'secure_signon_cookie', false ), 'the test environment is not production' );
		$this->assertTrue( Headers::wants_secure_cookie( 'production', 'https://example.org' ) );
		$this->assertFalse( Headers::wants_secure_cookie( 'production', 'http://example.org' ), 'a site still on http keeps working cookies' );
		$this->assertFalse( Headers::wants_secure_cookie( 'staging', 'https://example.org' ) );
	}

	public function test_REQ_046_https_redirect_waits_for_an_https_site_address(): void {
		$this->assertTrue( Headers::should_redirect( 'production', false, 'https://example.org' ) );
		$this->assertTrue( Headers::should_redirect( 'production', false, 'HTTPS://example.org/' ) );
		$this->assertFalse( Headers::should_redirect( 'production', true, 'https://example.org' ), 'already on https' );
		$this->assertFalse( Headers::should_redirect( 'production', false, 'http://example.org' ), 'no certificate yet: no lockout' );
		$this->assertFalse( Headers::should_redirect( 'local', false, 'https://example.org' ) );
	}
}
