<?php
/**
 * Parent theme behaviour inside WordPress (REQ-273, REQ-044). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class ParentThemeTest extends TestCase {

	public function test_REQ_273_parent_theme_is_found_by_wordpress(): void {
		$this->requireWordPress();
		$theme = wp_get_theme( 'whoiswho' );
		$this->assertTrue( $theme->exists() );
		$this->assertSame( 'WHOISWHO', $theme->get( 'Name' ) );
	}

	public function test_REQ_273_notice_shows_when_parent_is_active_alone(): void {
		$this->requireWordPress();
		switch_theme( 'whoiswho' );
		ob_start();
		do_action( 'admin_notices' );
		$out = (string) ob_get_clean();
		$this->assertStringContainsString( 'parent theme is active on its own', $out );
	}

	public function test_REQ_044_emoji_and_embed_scripts_are_removed(): void {
		$this->requireWordPress();
		do_action( 'init' );
		$this->assertFalse( has_action( 'wp_head', 'print_emoji_detection_script' ) );
		$this->assertFalse( has_action( 'wp_print_styles', 'print_emoji_styles' ) );
		$this->assertFalse( has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) );
	}
}
