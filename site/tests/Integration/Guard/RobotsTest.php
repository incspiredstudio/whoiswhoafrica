<?php
/**
 * The robots.txt the guard serves (REQ-121, REQ-217). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Guard;

use WhoIsWho\Tests\WpTestCase;

final class RobotsTest extends WpTestCase {

	/**
	 * What /robots.txt would print, after other plugins have had their say.
	 *
	 * @param callable|null $other Another plugin's filter.
	 */
	private function robots( ?callable $other = null ): string {
		if ( null !== $other ) {
			add_filter( 'robots_txt', $other, 10, 2 );
		}
		ob_start();
		do_robots();
		$text = (string) ob_get_clean();
		if ( null !== $other ) {
			remove_filter( 'robots_txt', $other, 10 );
		}
		return $text;
	}

	public function test_REQ_121_robots_txt_allows_all_and_lists_sitemap(): void {
		$text = $this->robots();
		$this->assertStringContainsString( "User-agent: *\nAllow: /", $text );
		$this->assertStringContainsString( 'Sitemap: ' . home_url( '/sitemap.xml' ), $text );
		$this->assertStringNotContainsString( 'Disallow', $text );
	}

	public function test_REQ_217_robots_txt_does_not_disallow_pending_urls(): void {
		$text = $this->robots(
			static fn ( $output ): string => $output . "Disallow: /profile/\nDisallow: /wp-admin/\n"
		);
		$this->assertStringNotContainsString( '/profile/', $text, 'Another plugin cannot block profile pages.' );
		$this->assertStringNotContainsString( 'Disallow', $text );
	}

	public function test_REQ_217_robots_txt_stays_open_when_the_site_is_set_to_discourage_search_engines(): void {
		update_option( 'blog_public', '0' );
		try {
			$this->assertStringNotContainsString( 'Disallow', $this->robots() );
		} finally {
			update_option( 'blog_public', '1' );
		}
	}
}
