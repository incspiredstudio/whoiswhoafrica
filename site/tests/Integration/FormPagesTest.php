<?php
/**
 * The Contact page, the Get listed page and the done page (REQ-039, REQ-081, REQ-058, REQ-177, REQ-120). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\WpTestCase;

final class FormPagesTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		switch_theme( 'whoiswho-child' );
		$this->reset_state();
	}

	protected function tearDown(): void {
		$this->reset_state();
		parent::tearDown();
	}

	/**
	 * Clear the refused-form result and the query variables the pages read.
	 */
	private function reset_state(): void {
		wiw_form_result( array() );
		foreach ( array( WIW_FORM_PAGE_VAR, 'kind', 'from', 'ref' ) as $var ) {
			set_query_var( $var, '' );
		}
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

	/**
	 * Set the done page's query variables.
	 *
	 * @param array<string, string> $vars Variables.
	 */
	private function done_vars( array $vars ): void {
		foreach ( $vars as $name => $value ) {
			set_query_var( $name, $value );
		}
	}

	public function test_REQ_039_contact_has_the_short_form_the_promise_and_the_four_alternatives(): void {
		$out = $this->render( 'contact' );
		$this->assertSame( 1, substr_count( $out, '<h1' ) );
		$this->assertStringContainsString( 'method="post"', $out );
		$this->assertStringContainsString( home_url( '/contact/' ), $out );
		foreach ( array( 'name="name"', 'name="email"', 'name="message"', 'name="wiw_form_token"', 'name="wiw_website"' ) as $needle ) {
			$this->assertStringContainsString( $needle, $out, $needle );
		}
		$this->assertStringContainsString( 'privacy-notice', $out );
		$this->assertStringContainsString( 'A person reads every message', $out );
		$this->assertStringContainsString( 'correction form', $out );
		foreach ( array( '/directory/', '/get-listed/', '/complaints/', '/data-rights/' ) as $link ) {
			$this->assertStringContainsString( 'href="' . home_url( $link ) . '"', $out, $link );
		}
	}

	public function test_REQ_177_get_listed_has_the_fields_the_consent_tick_and_the_five_interests(): void {
		$out = $this->render( 'get-listed' );
		foreach ( array( 'name="name"', 'name="organisation"', 'name="interest"', 'name="email"', 'name="phone"', 'name="message"', 'name="consent"', 'name="wiw_form_token"' ) as $needle ) {
			$this->assertStringContainsString( $needle, $out, $needle );
		}
		foreach ( array( 'listing_a_profile', 'sponsored_story', 'media_or_editorial_work', 'awards_sponsorship', 'not_sure' ) as $interest ) {
			$this->assertStringContainsString( 'value="' . $interest . '"', $out, $interest );
		}
		$this->assertStringContainsString( 'action="' . home_url( '/subscribe/' ) . '"', $out );
	}

	public function test_REQ_120_get_listed_page_has_no_price_or_package_text(): void {
		$text = strtolower( wp_strip_all_tags( $this->render( 'get-listed' ) ) );
		foreach ( array( 'price', 'pricing', 'package', 'tier', 'cart', 'checkout', 'basket', 'per month' ) as $word ) {
			$this->assertStringNotContainsString( $word, $text, $word );
		}
	}

	public function test_REQ_177_a_refused_form_shows_the_errors_beside_the_fields(): void {
		wiw_form_result(
			array(
				'form'    => 'get_listed',
				'status'  => 'invalid',
				'message' => 'Please check the highlighted fields.',
				'errors'  => array(
					'name'    => 'required',
					'contact' => 'required',
					'consent' => 'required',
				),
				'http'    => 422,
			)
		);
		$out = $this->render( 'get-listed' );
		$this->assertStringContainsString( 'role="alert"', $out );
		$this->assertStringContainsString( 'Please check the highlighted fields.', $out );
		$this->assertStringContainsString( 'aria-invalid="true"', $out );
		$this->assertGreaterThanOrEqual( 3, substr_count( $out, 'class="err"' ) );
	}

	public function test_REQ_081_a_throttled_form_says_to_try_later_without_clearing_the_page(): void {
		wiw_form_result(
			array(
				'form'    => 'contact',
				'status'  => 'try_later',
				'message' => 'Please try again later.',
				'errors'  => array(),
				'http'    => 429,
			)
		);
		$out = $this->render( 'contact' );
		$this->assertStringContainsString( 'Please try again later.', $out );
		$this->assertStringContainsString( 'name="message"', $out );
	}

	public function test_REQ_177_done_page_shows_the_reference_and_that_nothing_is_charged(): void {
		$this->done_vars(
			array(
				'kind' => 'enquiry',
				'from' => 'get_listed',
				'ref'  => 'ENQ-0004',
			)
		);
		$out = $this->render( 'done' );
		$this->assertStringContainsString( 'Enquiry received', $out );
		$this->assertStringContainsString( 'ENQ-0004', $out );
		$this->assertStringContainsString( 'nothing is charged on this site', strtolower( $out ) );
		$this->assertStringContainsString( 'role="status"', $out );
	}

	public function test_REQ_039_done_page_after_the_contact_form_says_message_sent(): void {
		$this->done_vars(
			array(
				'kind' => 'enquiry',
				'from' => 'contact',
				'ref'  => 'ENQ-0005',
			)
		);
		$out = $this->render( 'done' );
		$this->assertStringContainsString( 'Message sent', $out );
		$this->assertStringContainsString( 'ENQ-0005', $out );
	}

	public function test_REQ_081_a_made_up_reference_is_not_echoed(): void {
		$this->done_vars(
			array(
				'kind' => 'enquiry',
				'ref'  => '<script>alert(1)</script>',
			)
		);
		$this->assertStringNotContainsString( '<script>alert', $this->render( 'done' ) );
	}

	public function test_REQ_043_get_listed_is_served_at_get_listed_and_the_old_subscribe_address_redirects_a_visit_but_not_the_post(): void {
		$this->assertSame( home_url( '/get-listed/' ), wiw_form_old_address( 'subscribe', 'GET' ) );
		$this->assertSame( home_url( '/get-listed/' ), wiw_form_old_address( 'subscribe', 'HEAD' ) );
		$this->assertSame( '', wiw_form_old_address( 'subscribe', 'POST' ), 'the form post and its refusal page stay at /subscribe/' );
		$this->assertSame( '', wiw_form_old_address( 'get-listed', 'GET' ) );
		$this->assertSame( '', wiw_form_old_address( 'contact', 'GET' ) );
	}

	public function test_REQ_081_the_routes_and_the_template_choice(): void {
		$this->assertSame( array( 'contact', 'subscribe', 'get-listed', 'claim', 'unsubscribe', 'search', 'newsletter', 'done' ), array_keys( wiw_form_pages() ) );
		set_query_var( WIW_FORM_PAGE_VAR, 'contact' );
		$this->assertStringEndsWith( 'templates/contact.php', wiw_form_template( 'x.php' ) );
		set_query_var( WIW_FORM_PAGE_VAR, 'subscribe' );
		$this->assertStringEndsWith( 'templates/get-listed.php', wiw_form_template( 'x.php' ) );
		set_query_var( WIW_FORM_PAGE_VAR, '' );
		$this->assertSame( 'x.php', wiw_form_template( 'x.php' ) );
	}

	public function test_REQ_039_the_done_address_carries_which_form_it_came_from(): void {
		$url = (string) apply_filters( 'wiw_form_done_url', home_url( '/done/?kind=enquiry&ref=ENQ-1' ), array( 'form' => 'contact' ) );
		$this->assertStringContainsString( 'from=contact', $url );
	}
}
