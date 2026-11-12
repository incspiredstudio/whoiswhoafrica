<?php
/**
 * The newsletter pages: sign-up form, done page and the confirm and expired-link pages (REQ-061, REQ-249, REQ-058). Needs the
 * WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\WpTestCase;

final class NewsletterPagesTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		switch_theme( 'whoiswho-child' );
		wiw_form_result( array() );
	}

	protected function tearDown(): void {
		wiw_form_result( array() );
		foreach ( array( WIW_FORM_PAGE_VAR, 'kind', 'from', 'ref' ) as $var ) {
			set_query_var( $var, '' );
		}
		parent::tearDown();
	}

	/**
	 * Render a template of the theme.
	 *
	 * @param string $template Template file name.
	 */
	private function render( string $template ): string {
		ob_start();
		include get_template_directory() . '/templates/' . $template . '.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_061_the_signup_page_has_email_consent_token_and_posts_to_newsletter(): void {
		$out = $this->render( 'newsletter' );
		$this->assertSame( 1, substr_count( $out, '<h1' ) );
		$this->assertStringContainsString( 'action="' . home_url( '/newsletter/' ) . '"', $out );
		foreach ( array( 'name="email"', 'name="consent"', 'name="wiw_form_token"', 'name="wiw_website"' ) as $needle ) {
			$this->assertStringContainsString( $needle, $out, $needle );
		}
		$this->assertStringContainsString( 'unsubscribe', strtolower( $out ) );
	}

	public function test_REQ_058_a_refused_signup_shows_the_errors_and_keeps_the_form(): void {
		wiw_form_result(
			array(
				'form'    => 'newsletter',
				'status'  => 'invalid',
				'message' => 'Please check the highlighted fields.',
				'errors'  => array(
					'email'   => 'invalid_email',
					'consent' => 'required',
				),
				'http'    => 422,
			)
		);
		$out = $this->render( 'newsletter' );
		$this->assertStringContainsString( 'role="alert"', $out );
		$this->assertStringContainsString( 'aria-invalid="true"', $out );
		$this->assertStringContainsString( 'name="email"', $out );
	}

	public function test_REQ_061_done_page_for_a_newsletter_signup_says_to_check_email(): void {
		set_query_var( 'kind', 'newsletter' );
		set_query_var( 'from', 'newsletter' );
		set_query_var( 'ref', '' );
		$out = $this->render( 'done' );
		$this->assertStringContainsString( 'Check your email', $out );
		$this->assertStringContainsString( 'confirm', strtolower( $out ) );
		$this->assertStringNotContainsString( 'Enquiry received', $out );
		$this->assertStringNotContainsString( 'Your reference', $out );
	}

	public function test_REQ_249_the_confirmed_page_says_you_are_subscribed_and_is_not_indexed(): void {
		$out = (string) apply_filters(
			'wiw_newsletter_confirm_page',
			'default',
			array(
				'state'   => 'confirmed',
				'message' => 'You are subscribed.',
			)
		);
		$this->assertStringContainsString( 'You are subscribed', $out );
		$this->assertStringContainsString( 'unsubscribe link', $out );
		$this->assertStringNotContainsString( 'default', $out );
	}

	public function test_REQ_249_the_expired_page_offers_a_new_signup_and_never_the_address(): void {
		$out = (string) apply_filters(
			'wiw_newsletter_confirm_page',
			'default',
			array(
				'state'   => 'expired',
				'message' => 'Link expired or already used.',
			)
		);
		$this->assertStringContainsString( 'expired or was already used', $out );
		$this->assertStringContainsString( 'href="' . home_url( '/newsletter/' ) . '"', $out );
		$this->assertStringNotContainsString( '@', wp_strip_all_tags( $out ) );
	}
}
