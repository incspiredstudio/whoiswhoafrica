<?php
/**
 * The theme asks for its required plugins and degrades safely without them (REQ-046, REQ-056). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\WpTestCase;

final class RequiredPluginNoticeTest extends WpTestCase {

	private function themeDir(): string {
		return dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho';
	}

	public function set_up(): void {
		parent::set_up();
		require_once $this->themeDir() . '/inc/dependencies.php';
	}

	public function test_REQ_046_core_plugin_is_reported_active_when_loaded(): void {
		$this->assertSame( 'active', wiw_core_state() );
		$this->assertTrue( wiw_core_ready() );
		$this->assertTrue( wiw_guard_present() );
		$this->assertSame( array(), wiw_dependency_items( 'active', true, true, true ) );
	}

	public function test_REQ_046_inactive_core_offers_a_nonce_protected_activate_link(): void {
		$items = wiw_dependency_items( 'inactive', true, true, true );
		$this->assertCount( 1, $items );
		$this->assertStringContainsString( 'action=activate', $items[0]['url'] );
		$this->assertStringContainsString( rawurlencode( 'whoiswho-core/whoiswho-core.php' ), $items[0]['url'] );
		$this->assertStringContainsString( '_wpnonce=', $items[0]['url'] );
	}

	public function test_REQ_046_no_activate_link_for_someone_who_cannot_activate(): void {
		$items = wiw_dependency_items( 'inactive', true, false, false );
		$this->assertCount( 1, $items );
		$this->assertSame( '', $items[0]['url'] );
	}

	public function test_REQ_046_missing_core_points_to_the_upload_screen_only_for_installers(): void {
		$with = wiw_dependency_items( 'missing', true, true, true );
		$this->assertStringContainsString( 'plugin-install.php?tab=upload', $with[0]['url'] );
		$this->assertStringContainsString( 'whoiswho-core.zip', $with[0]['text'] );
		$without = wiw_dependency_items( 'missing', true, true, false );
		$this->assertSame( '', $without[0]['url'] );
	}

	public function test_REQ_046_missing_guard_gets_a_copy_instruction_not_a_link(): void {
		$items = wiw_dependency_items( 'active', false, true, true );
		$this->assertCount( 1, $items );
		$this->assertSame( 'guard', $items[0]['key'] );
		$this->assertStringContainsString( 'mu-plugins', $items[0]['text'] );
		$this->assertSame( '', $items[0]['url'] );
	}

	public function test_REQ_046_notice_markup_is_escaped(): void {
		$html = wiw_dependency_notice_html(
			array(
				array(
					'key'   => 'core"><script>',
					'text'  => '<script>alert(1)</script>',
					'url'   => 'javascript:alert(1)',
					'label' => '<b>x</b>',
				),
			)
		);
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertSame( '', wiw_dependency_notice_html( array() ) );
	}

	public function test_REQ_046_notice_is_printed_on_the_plugins_screen_for_an_administrator_only(): void {
		// The core plugin is active in the suite, so nothing is printed; the printing path must not error.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'plugins' );
		ob_start();
		wiw_print_dependency_notice();
		$this->assertSame( '', ob_get_clean() );
		wp_set_current_user( 0 );
		set_current_screen( 'front' );
	}

	public function test_REQ_056_holding_page_is_a_neutral_page_that_calls_no_plugin_code(): void {
		$source = (string) file_get_contents( $this->themeDir() . '/safe-mode.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertStringContainsString( 'status_header( 503 )', $source );
		$this->assertStringNotContainsString( 'WhoIsWho\\Core', $source );
		$this->assertStringNotContainsString( 'wiw_', str_replace( 'wiw_core', '', $source ) );
		$this->assertStringNotContainsString( 'get_header', $source );
		$this->assertStringNotContainsString( 'WP_Query', $source );
	}

	public function test_REQ_056_holding_page_renders_without_the_theme_files(): void {
		wp_set_current_user( 0 );
		ob_start();
		include $this->themeDir() . '/safe-mode.php';
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'check back soon', $html );
		$this->assertStringContainsString( '<h1>', $html );
	}

	public function test_REQ_056_functions_php_loads_the_theme_only_when_the_core_plugin_is_ready(): void {
		$source = (string) file_get_contents( $this->themeDir() . '/functions.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$guard  = strpos( $source, 'wiw_core_ready()' );
		$loop   = strpos( $source, "foreach ( array( 'setup'" );
		$this->assertNotFalse( $guard );
		$this->assertNotFalse( $loop );
		$this->assertLessThan( $loop, $guard, 'the guard runs before any theme file that calls the plugin' );
		$this->assertStringContainsString( 'safe-mode.php', $source );
	}
}
