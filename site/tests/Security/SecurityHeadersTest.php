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

// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- the tests feed literal script tags to the CSP finisher.
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

	/**
	 * Issue 719: a page cache stores the page body. The finished body must carry no per-request token, and the policy must be
	 * derived from the body alone (hashes of the trusted inline scripts), so a stored body and a stored or replayed header always
	 * agree and no visitor ever gets a body whose scripts the header blocks.
	 */
	public function test_REQ_046_finished_page_trades_the_nonce_for_script_hashes(): void {
		$code = 'document.documentElement.classList.add("at-top");';
		$html = '<html><head><script nonce="N1" data-home="1">' . $code . '</script>'
			. '<script nonce="N1" src="/wp-content/themes/whoiswho/assets/js/main.js" defer></script>'
			. '<script type="application/ld+json" nonce="N1">{"@type":"Person"}</script>'
			. '</head><body><p>Hi</p><script>alert("injected")</script></body></html>';

		list( $body, $sources ) = Headers::finalise( $html, 'N1', 'https://example.org' );

		$this->assertStringNotContainsString( 'nonce=', $body, 'no per-request token is left in the stored body' );
		$this->assertStringContainsString( '<script data-home="1">' . $code . '</script>', $body, 'the script itself is unchanged' );
		$hash = "'sha256-" . base64_encode( hash( 'sha256', $code, true ) ) . "'"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$this->assertContains( $hash, $sources, 'the trusted inline script is allowed by its hash' );
		$injected = "'sha256-" . base64_encode( hash( 'sha256', 'alert("injected")', true ) ) . "'"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$this->assertNotContains( $injected, $sources, 'a script without this request\'s nonce is never trusted' );
		$this->assertCount( 1, $sources, 'same-origin files and data blocks need no extra source' );

		$csp = Headers::csp( '', array(), $sources );
		preg_match( '/script-src ([^;]+)/', $csp, $m );
		$this->assertStringContainsString( $hash, $m[1] );
		$this->assertStringNotContainsString( 'nonce-', $m[1] );
		$this->assertStringNotContainsString( 'unsafe-inline', $m[1] );
	}

	public function test_REQ_046_two_requests_for_the_same_page_give_the_same_body_and_policy(): void {
		$page   = static function ( string $nonce ): string {
			return '<head><script nonce="' . $nonce . '">window.wiwMotion={"lenis":"x"};</script></head><body>x</body>';
		};
		$first  = Headers::finalise( $page( 'AAAA' ), 'AAAA', 'https://example.org' );
		$second = Headers::finalise( $page( 'BBBB' ), 'BBBB', 'https://example.org' );
		$this->assertSame( $first, $second, 'a cached copy of either request matches the policy of both' );
	}

	public function test_REQ_046_a_trusted_script_from_another_host_keeps_working(): void {
		$html                   = '<script nonce="N" src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>'
			. '<script nonce="N" src="//cdn.example.net/x.js"></script><script src="https://evil.example/x.js"></script>';
		list( $body, $sources ) = Headers::finalise( $html, 'N', 'https://example.org' );
		$this->assertSame( array( 'https://cdn.example.net', 'https://challenges.cloudflare.com' ), $sources );
		$this->assertStringNotContainsString( 'evil.example', implode( ' ', $sources ) );
		$this->assertStringNotContainsString( 'nonce=', $body );
	}

	public function test_REQ_046_output_that_is_not_a_whole_html_page_is_left_alone(): void {
		$this->assertNull( Headers::finish_policy( '<rss></rss>', 'N', 'application/rss+xml; charset=UTF-8', true ), 'feeds keep the early header' );
		$this->assertNull( Headers::finish_policy( '<p nonce="N">part</p>', 'N', 'text/html; charset=UTF-8', false ), 'a page flushed in parts keeps its nonce and header' );
		$done = Headers::finish_policy( '<script nonce="N">a()</script>', 'N', 'text/html; charset=UTF-8', true );
		$this->assertIsArray( $done );
		$this->assertStringNotContainsString( 'nonce', $done[0] );
		$this->assertStringContainsString( "'sha256-", $done[1] );
		$this->assertStringContainsString( "frame-ancestors 'none'", $done[1] );
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
