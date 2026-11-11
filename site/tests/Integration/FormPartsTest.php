<?php
/**
 * Shared form and notice parts (REQ-081, REQ-043, REQ-058). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class FormPartsTest extends TestCase {

	/**
	 * Render a template part to a string.
	 *
	 * @param string               $part Part slug.
	 * @param array<string, mixed> $args Args.
	 */
	private function render( string $part, array $args ): string {
		ob_start();
		get_template_part( $part, null, $args );
		return (string) ob_get_clean();
	}

	public function test_REQ_081_form_part_contains_honeypot_and_token(): void {
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		$out = $this->render( 'template-parts/forms/bot-check', array( 'form' => 'contact' ) );
		$this->assertStringContainsString( 'name="wiw_website"', $out );
		$this->assertStringContainsString( 'name="wiw_form_token"', $out );
		$this->assertMatchesRegularExpression( '/<label for="wiw_website">/', $out );
		$this->assertMatchesRegularExpression( '/value="[A-Za-z0-9_-]{20,}"/', $out, 'a signed token is issued' );
	}

	public function test_REQ_043_field_has_label_and_hint_is_attached(): void {
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		$out = $this->render(
			'template-parts/forms/field',
			array(
				'name'     => 'email',
				'label'    => 'Your email',
				'type'     => 'email',
				'required' => true,
				'hint'     => 'Only used to reply.',
				'error'    => 'Enter a valid email',
			)
		);
		$this->assertStringContainsString( '<label for="f-email">', $out );
		$this->assertStringContainsString( 'aria-describedby="f-email-hint f-email-err"', $out );
		$this->assertStringContainsString( 'aria-invalid="true"', $out );
		$this->assertStringContainsString( 'role="alert"', $out );
	}

	public function test_REQ_043_select_textarea_and_checkbox_variants_render(): void {
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		$select = $this->render(
			'template-parts/forms/field',
			array(
				'name'    => 'who',
				'label'   => 'Who',
				'type'    => 'select',
				'options' => array( 'a' => 'A <b>' ),
			)
		);
		$this->assertStringContainsString( '<select', $select );
		$this->assertStringContainsString( 'A &lt;b&gt;', $select );
		$this->assertStringContainsString(
			'<textarea',
			$this->render(
				'template-parts/forms/field',
				array(
					'name'  => 'm',
					'label' => 'M',
					'type'  => 'textarea',
				)
			)
		);
		$this->assertStringContainsString(
			'type="checkbox"',
			$this->render(
				'template-parts/forms/field',
				array(
					'name'  => 'c',
					'label' => 'I agree',
					'type'  => 'checkbox',
				)
			)
		);
	}

	public function test_REQ_043_done_part_shows_reference_and_way_back(): void {
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		$out = $this->render(
			'template-parts/done',
			array(
				'title'     => 'Thank you',
				'reference' => 'WIW-123',
				'back_url'  => 'https://example.test/',
			)
		);
		$this->assertStringContainsString( 'role="status"', $out );
		$this->assertStringContainsString( 'WIW-123', $out );
		$this->assertStringContainsString( 'href="https://example.test/"', $out );
	}

	public function test_REQ_058_privacy_notice_and_notice_escape_their_text(): void {
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		$out = $this->render( 'template-parts/forms/privacy-notice', array( 'text' => 'We use <this>' ) );
		$this->assertStringContainsString( 'We use &lt;this&gt;', $out );
		$this->assertSame( '', trim( $this->render( 'template-parts/forms/privacy-notice', array() ) ) );
		$notice = $this->render(
			'template-parts/notice',
			array(
				'kind' => 'sponsored',
				'text' => 'Sponsored',
			)
		);
		$this->assertStringContainsString( 'notice--sponsored', $notice );
	}
}
