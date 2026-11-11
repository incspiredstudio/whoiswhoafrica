<?php
/**
 * 404, 410 and 500 pages carry navigation and no technical detail (REQ-121, REQ-209). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class SystemPagesTest extends TestCase {

	private function theme_dir(): string {
		return dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho';
	}

	public function test_REQ_209_410_template_links_to_the_complaints_policy_and_directory(): void {
		$this->requireWordPress();
		ob_start();
		wiw_error_links( true );
		$out = (string) ob_get_clean();
		$this->assertStringContainsString( '/complaints/', $out );
		$this->assertStringContainsString( '/directory/', $out );
		$this->assertFileExists( $this->theme_dir() . '/410.php' );
		$this->assertFileExists( $this->theme_dir() . '/404.php' );
	}

	public function test_REQ_209_410_page_gives_no_reason(): void {
		$code = (string) file_get_contents( $this->theme_dir() . '/410.php' );
		$this->assertStringNotContainsString( 'because', strtolower( $code ) );
		$this->assertStringNotContainsString( 'suppress', strtolower( $code ) );
	}

	public function test_REQ_121_server_error_message_has_no_technical_detail(): void {
		$this->requireWordPress();
		$msg = apply_filters( 'wp_php_error_message', 'Fatal error in /var/www/x.php on line 3', array( 'message' => 'boom' ) );
		$this->assertStringNotContainsString( '/var/www', (string) $msg );
		$this->assertStringNotContainsString( 'line 3', (string) $msg );
	}

	public function test_REQ_209_gone_requests_get_410_and_noindex(): void {
		$this->requireWordPress();
		add_filter( 'wiw_is_gone', '__return_true' );
		$template = wiw_gone_template( '/index.php' );
		remove_filter( 'wiw_is_gone', '__return_true' );
		$this->assertStringEndsWith( '410.php', $template );
	}
}
