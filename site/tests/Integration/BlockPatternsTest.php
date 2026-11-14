<?php
/**
 * Theme block patterns for the editable pages (REQ-260, REQ-052). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class BlockPatternsTest extends TestCase {

	private const SLUGS = array(
		'whoiswho/page-hero',
		'whoiswho/purpose-band',
		'whoiswho/pull-statement',
		'whoiswho/three-pillars',
		'whoiswho/link-list',
		'whoiswho/check-list',
		'whoiswho/closing-call',
		'whoiswho/legal-section',
		'whoiswho/guidance-section',
		'whoiswho/draft-note',
		'whoiswho/about-page',
		'whoiswho/faq-groups',
	);

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		// Register the theme's pattern files (WordPress does this on init for the active theme).
		foreach ( self::SLUGS as $slug ) {
			\WP_Block_Patterns_Registry::get_instance()->unregister( $slug );
		}
		_register_theme_block_patterns();
		do_action( 'init' );
	}

	public function test_REQ_260_every_planned_pattern_is_registered_in_the_page_sections_category(): void {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		foreach ( self::SLUGS as $slug ) {
			$this->assertTrue( $registry->is_registered( $slug ), $slug );
			$pattern = $registry->get_registered( $slug );
			$this->assertContains( 'whoiswho', $pattern['categories'], $slug );
			$this->assertNotSame( '', trim( (string) $pattern['content'] ), $slug );
		}
		$this->assertTrue( \WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( 'whoiswho' ) );
	}

	public function test_REQ_260_patterns_are_real_blocks_with_no_freeform_html_or_scripts(): void {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		foreach ( self::SLUGS as $slug ) {
			$content = (string) $registry->get_registered( $slug )['content'];
			foreach ( parse_blocks( $content ) as $block ) {
				if ( null === $block['blockName'] ) {
					$this->assertSame( '', trim( (string) $block['innerHTML'] ), "{$slug}: loose HTML outside blocks" );
				}
			}
			$this->assertStringNotContainsStringIgnoringCase( '<script', $content, $slug );
			$this->assertStringNotContainsString( 'style=', $content, "{$slug}: styling lives in the tokens, not in the pattern" );
		}
	}

	public function test_REQ_052_patterns_hold_no_colours_fonts_or_brand_names(): void {
		foreach ( glob( dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho/patterns/*.php' ) as $file ) {
			$code = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,8}\b|\b(rgb|hsl)a?\(/', $code, basename( $file ) );
			foreach ( array( 'Repline', 'Montreal', 'WHOISWHO', 'wiwi.africa' ) as $literal ) {
				$this->assertStringNotContainsString( $literal, $code, basename( $file ) );
			}
		}
	}

	public function test_REQ_260_three_pillars_colours_are_fixed_to_the_brand_blocks(): void {
		$content = (string) \WP_Block_Patterns_Registry::get_instance()->get_registered( 'whoiswho/three-pillars' )['content'];
		foreach ( array( 'poster--blue', 'poster--orange', 'poster--green' ) as $class ) {
			$this->assertStringContainsString( $class, $content );
		}
		$this->assertSame( 3, substr_count( $content, '"lock":{"move":true,"remove":true}' ) );
	}

	public function test_REQ_260_about_page_pattern_has_one_h1_and_no_public_draft_note(): void {
		$content = (string) \WP_Block_Patterns_Registry::get_instance()->get_registered( 'whoiswho/about-page' )['content'];
		$this->assertSame( 1, substr_count( $content, '<h1 ' ) );
		// The About page (starter and demo) is public at once, so it carries no "Draft copy for review" note (issue 806).
		$this->assertSame( 0, substr_count( $content, 'notice--draft' ) );
		$this->assertStringNotContainsString( 'Delete this note', $content );
		$this->assertSame( 3, substr_count( $content, '"lock":{"move":true,"remove":true}' ), 'only the three pillars are locked' );
	}

	public function test_REQ_260_about_uses_the_editorial_template_and_an_empty_page_falls_back_to_the_pattern(): void {
		$this->assertArrayHasKey( 'about', wiw_editorial_pages() );
		$this->assertStringContainsString( 'ab-hero', wiw_default_page_pattern( 'about' ) );
		$this->assertSame( '', wiw_default_page_pattern( 'no-such-page' ) );
	}

	public function test_REQ_260_legal_section_has_a_removable_draft_note_and_forms_have_no_pattern(): void {
		$legal = (string) \WP_Block_Patterns_Registry::get_instance()->get_registered( 'whoiswho/legal-section' )['content'];
		$this->assertStringContainsString( 'notice--draft', $legal );
		foreach ( \WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			if ( 0 === strpos( (string) $pattern['name'], 'whoiswho/' ) ) {
				$this->assertStringNotContainsString( '<form', (string) $pattern['content'], $pattern['name'] );
			}
		}
	}
}
