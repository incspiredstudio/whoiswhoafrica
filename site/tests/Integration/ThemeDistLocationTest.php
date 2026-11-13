<?php
/**
 * A theme installed from a zip carries its own built assets: dist/ inside the theme wins over dist/ beside wp-content
 * (REQ-046, REQ-056). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\WpTestCase;

final class ThemeDistLocationTest extends WpTestCase {

	private string $own = '';

	protected function setUp(): void {
		parent::setUp();
		require_once dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho/inc/enqueue.php';
		$this->own = get_template_directory() . '/dist';
	}

	protected function tearDown(): void {
		if ( is_file( $this->own . '/manifest.json' ) ) {
			unlink( $this->own . '/manifest.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( is_dir( $this->own ) ) {
			rmdir( $this->own ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		parent::tearDown();
	}

	public function test_REQ_056_without_a_theme_dist_the_folder_beside_wp_content_is_used(): void {
		$this->assertStringEndsWith( '/../dist', wiw_dist_dir() );
		$this->assertStringEndsWith( '/dist/', wiw_dist_url() );
		$this->assertStringNotContainsString( '/themes/', wiw_dist_url() );
	}

	public function test_REQ_056_a_dist_folder_inside_the_theme_wins(): void {
		mkdir( $this->own ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $this->own . '/manifest.json', '{}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertSame( $this->own, wiw_dist_dir() );
		$this->assertStringContainsString( '/themes/', wiw_dist_url() );
		$this->assertStringEndsWith( '/dist/', wiw_dist_url() );
	}
}
