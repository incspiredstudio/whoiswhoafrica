<?php
/**
 * Privacy notice and consent tick on personal-data forms (REQ-058, REQ-081). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\TestCase;

final class PrivacyFormPartsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
	}

	/**
	 * Render a template part.
	 *
	 * @param string               $part Part.
	 * @param array<string, mixed> $args Args.
	 */
	private function render( string $part, array $args = array() ): string {
		ob_start();
		get_template_part( $part, null, $args );
		return (string) ob_get_clean();
	}

	public function test_REQ_058_the_privacy_notice_always_links_the_privacy_page(): void {
		$out = $this->render( 'template-parts/forms/privacy-notice' );
		$this->assertMatchesRegularExpression( '#<a href="[^"]*/privacy/">Privacy notice</a>#', $out );
		$custom = $this->render( 'template-parts/forms/privacy-notice', array( 'text' => 'Custom wording.' ) );
		$this->assertStringContainsString( 'Custom wording.', $custom );
		$this->assertStringContainsString( '/privacy/', $custom );
	}

	public function test_REQ_058_the_consent_tick_is_required_unticked_and_carries_the_policy_version(): void {
		$version = (string) SettingsService::for_site()->get( SettingsService::POLICY_VERSION );
		$out     = $this->render(
			'template-parts/forms/consent-tick',
			array(
				'name'    => 'newsletter_consent',
				'purpose' => 'newsletter',
			)
		);
		$this->assertMatchesRegularExpression( '#<input type="checkbox" value="1" id="f-newsletter_consent" name="newsletter_consent" required#', $out );
		$this->assertStringNotContainsString( 'checked', $out, 'A consent tick is never pre-ticked.' );
		$this->assertStringContainsString( '/privacy/', $out );
		$this->assertStringContainsString( '<input type="hidden" name="policy_version" value="' . esc_attr( $version ) . '">', $out );
		$this->assertStringContainsString( 'name="consent_purpose" value="newsletter"', $out );
	}

	public function test_REQ_058_every_public_form_template_includes_privacy_notice(): void {
		$root  = dirname( __DIR__, 2 ) . '/wp-content/themes';
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		$bad   = array();
		$forms = 0;
		foreach ( $it as $file ) {
			$path = str_replace( '\\', '/', (string) $file );
			if ( '.php' !== substr( $path, -4 ) || false !== strpos( $path, '/node_modules/' ) ) {
				continue;
			}
			$code = (string) file_get_contents( $path );
			foreach ( $this->post_forms( $code ) as $tag ) {
				++$forms;
				$exempt = false !== strpos( $tag, 'data-personal-data="none"' );
				if ( ! $exempt && false === strpos( $code, 'forms/privacy-notice' ) ) {
					$bad[] = $path;
				}
			}
		}
		$this->assertGreaterThan( 0, $forms, 'The rule found at least one POST form to check.' );
		$this->assertSame( array(), $bad, 'A form that collects personal data must include template-parts/forms/privacy-notice (or declare data-personal-data="none").' );
	}

	public function test_REQ_058_the_form_rule_catches_a_form_without_the_notice(): void {
		$this->assertCount( 1, $this->post_forms( "<form method=\"post\" action=\"/x\">\n" ) );
		$this->assertCount( 0, $this->post_forms( '<form class="fbar" method="get">' ) );
	}

	/**
	 * Opening tags of POST forms in a piece of code.
	 *
	 * @param string $code PHP or HTML.
	 * @return string[]
	 */
	private function post_forms( string $code ): array {
		preg_match_all( '/<form\b[^>]*\bmethod="post"[^>]*>/i', $code, $matches );
		return $matches[0];
	}
}
