<?php
/**
 * Base email templates (REQ-075, REQ-052, REQ-043). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class EmailTemplatesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
	}

	public function test_REQ_075_email_layout_has_text_and_html_variants(): void {
		foreach ( wiw_email_samples() as $name => $sample ) {
			$mail = wiw_render_email( $sample );
			$this->assertStringContainsString( '<!DOCTYPE html>', $mail['html'], $name );
			$this->assertStringContainsString( (string) $sample['cta']['url'], $mail['html'], $name );
			$this->assertSame( trim( $mail['text'] ), wp_strip_all_tags( $mail['text'] ), "{$name}: the text twin has no markup" );
			$this->assertStringContainsString( (string) $sample['cta']['url'], $mail['text'], $name );
			$this->assertStringContainsString( (string) $sample['heading'], $mail['text'], $name );
			$this->assertStringContainsString( wiw_brand( 'legal_name' ), $mail['text'], $name );
			$this->assertStringContainsString( wiw_brand( 'legal_name' ), $mail['html'], $name );
		}
	}

	public function test_REQ_075_unsubscribe_block_appears_only_for_marketing_messages(): void {
		$samples = wiw_email_samples();
		$tx      = wiw_render_email( $samples['transactional'] );
		$mk      = wiw_render_email( $samples['marketing'] );
		$this->assertStringNotContainsString( 'Unsubscribe', $tx['html'] );
		$this->assertStringNotContainsString( 'Unsubscribe', $tx['text'] );
		$this->assertStringContainsString( 'Unsubscribe', $mk['html'] );
		$this->assertStringContainsString( 'Unsubscribe', $mk['text'] );
		// A transactional message never shows it, even if a link is passed.
		$forced = wiw_render_email( array_merge( $samples['transactional'], array( 'unsubscribe_url' => 'https://example.test/u/x' ) ) );
		$this->assertStringNotContainsString( 'example.test/u/x', $forced['html'] );
	}

	public function test_REQ_052_email_templates_have_no_brand_literals(): void {
		$files = glob( dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho/emails/*.php' );
		$this->assertNotEmpty( $files );
		foreach ( (array) $files as $file ) {
			$code = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,8}\b/', $code, basename( $file ) . ' has a colour literal' );
			$this->assertDoesNotMatchRegularExpression( '/\b(rgb|hsl)a?\(/i', $code, basename( $file ) . ' has a colour function' );
			foreach ( array( 'Repline', 'Montreal', 'WHOISWHO', 'whoiswhoafrica', 'wiwi.africa', 'Influence.' ) as $literal ) {
				$this->assertStringNotContainsString( $literal, $code, basename( $file ) . ' has ' . $literal );
			}
		}
	}

	public function test_REQ_052_style_values_come_from_the_child_tokens(): void {
		$style = wiw_email_style();
		$this->assertNotSame( '', $style['cta'] );
		$this->assertStringStartsWith( '#', $style['cta'] );
		$this->assertStringContainsString( 'Repline', $style['headline'] );
		$mail = wiw_render_email( wiw_email_samples()['transactional'] );
		$this->assertStringContainsString( 'background-color:' . $style['cta'], $mail['html'] );
	}

	public function test_REQ_233_sample_links_carry_no_personal_data(): void {
		foreach ( wiw_email_samples() as $name => $sample ) {
			foreach ( array( $sample['cta']['url'], $sample['unsubscribe_url'] ?? '' ) as $url ) {
				$this->assertDoesNotMatchRegularExpression( '/[@?&]|%40/', (string) $url, "{$name}: no email, name or query in a link" );
			}
		}
	}

	public function test_REQ_043_html_has_alt_text_a_language_and_a_text_first_body(): void {
		$html = wiw_render_email( wiw_email_samples()['transactional'] )['html'];
		$this->assertMatchesRegularExpression( '/<img [^>]*alt="[^"]+"/', $html );
		$this->assertMatchesRegularExpression( '/<html lang="[a-z-]+"/i', $html );
		$this->assertStringContainsString( 'role="presentation"', $html );
	}
}
