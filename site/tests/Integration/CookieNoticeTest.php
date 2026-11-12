<?php
/**
 * Cookie notice and consent gating (REQ-248, REQ-062, REQ-058). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class CookieNoticeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		unset( $_COOKIE[ WIW_CONSENT_COOKIE ] );
	}

	protected function tearDown(): void {
		unset( $_COOKIE[ WIW_CONSENT_COOKIE ] );
		remove_all_filters( 'wiw_analytics_url' );
		parent::tearDown();
	}

	public function test_REQ_248_notice_has_decline_and_accept_a_name_and_is_hidden_without_script(): void {
		ob_start();
		get_template_part( 'template-parts/cookie-notice' );
		$out = (string) ob_get_clean();
		$this->assertStringContainsString( 'role="region" aria-label="Cookie consent"', $out );
		$this->assertStringContainsString( 'data-consent="essential">Decline<', $out );
		$this->assertStringContainsString( 'data-consent="all">Accept<', $out );
		$this->assertMatchesRegularExpression( '/class="cookie"[^>]* hidden>/', $out, 'hidden until the script decides' );
		$this->assertStringContainsString( '/cookies/', $out );
	}

	public function test_REQ_062_only_essential_is_accepted_before_a_choice_and_after_decline(): void {
		$this->assertSame( '', wiw_consent_choice() );
		$this->assertTrue( wiw_consent_accepted( 'essential' ) );
		$this->assertFalse( wiw_consent_accepted( 'analytics' ) );
		$_COOKIE[ WIW_CONSENT_COOKIE ] = 'essential';
		$this->assertFalse( wiw_consent_accepted( 'analytics' ) );
		$_COOKIE[ WIW_CONSENT_COOKIE ] = 'all';
		$this->assertTrue( wiw_consent_accepted( 'analytics' ) );
		$_COOKIE[ WIW_CONSENT_COOKIE ] = 'nonsense<script>';
		$this->assertSame( '', wiw_consent_choice() );
	}

	public function test_REQ_062_analytics_script_absent_before_accept(): void {
		add_filter( 'wiw_analytics_url', static fn (): string => 'https://stats.example/a.js' );
		add_filter(
			'wiw_manifest',
			static fn () => array( 'main.js' => 'main.1.js' )
		);
		wp_dequeue_script( 'wiw-main' );
		wp_deregister_script( 'wiw-main' );
		do_action( 'wp_enqueue_scripts' );
		ob_start();
		wp_print_scripts();
		do_action( 'wp_footer' );
		$out = (string) ob_get_clean();
		remove_all_filters( 'wiw_manifest' );
		$this->assertDoesNotMatchRegularExpression( '#<script[^>]+src=["\']https://stats\.example#', $out, 'the server never prints the analytics script' );
		$this->assertStringContainsString( 'window.wiwConsent=', $out );
		$this->assertStringContainsString( 'stats.example', $out, 'only as configuration for the browser to load after Accept' );
	}

	public function test_REQ_062_no_analytics_configured_by_default_and_only_https_is_accepted(): void {
		$this->assertSame( '', wiw_analytics_url() );
		add_filter( 'wiw_analytics_url', static fn (): string => 'http://insecure.example/a.js' );
		$this->assertSame( '', wiw_analytics_url() );
		remove_all_filters( 'wiw_analytics_url' );
		add_filter( 'wiw_analytics_url', static fn (): string => 'https://stats.example/a.js' );
		$this->assertSame( 'https://stats.example/a.js', wiw_analytics_url() );
	}

	public function test_REQ_248_footer_has_the_cookie_preferences_button(): void {
		ob_start();
		get_template_part( 'template-parts/site-footer' );
		$out = (string) ob_get_clean();
		$this->assertStringContainsString( 'data-cookie-reopen', $out );
	}
}
