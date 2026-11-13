<?php
/**
 * Legal pages render with the draft note and the cookie page offers Cookie preferences (REQ-100, REQ-120, REQ-248). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\WpTestCase;

final class LegalPagesTest extends WpTestCase {

	/**
	 * Render the page with the given slug through the theme's template selection.
	 *
	 * @param string $slug Page slug.
	 */
	private function render_page( string $slug ): string {
		switch_theme( 'whoiswho-child' );
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => ucfirst( $slug ),
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_content' => 'Body text',
			)
		);
		$this->go_to( get_permalink( $id ) );
		$template = wiw_legal_template( get_page_template() );
		ob_start();
		include $template;
		return (string) ob_get_clean();
	}

	public function test_REQ_100_legal_pages_render_with_the_draft_note(): void {
		foreach ( array( 'privacy', 'terms', 'complaints', 'data-rights' ) as $slug ) {
			$out = $this->render_page( $slug );
			$this->assertStringContainsString( 'notice--draft', $out, $slug );
			$this->assertSame( 1, substr_count( $out, '<h1>' ), $slug );
			$this->assertStringNotContainsString( 'data-cookie-prefs', $out, $slug );
		}
	}

	public function test_REQ_100_complaints_and_data_rights_are_legal_pages(): void {
		$this->assertContains( 'complaints', wiw_legal_slugs() );
		$this->assertContains( 'data-rights', wiw_legal_slugs() );
	}

	public function test_REQ_248_cookie_policy_offers_cookie_preferences(): void {
		$out = $this->render_page( 'cookies' );
		$this->assertStringContainsString( 'data-cookie-prefs', $out );
	}

	public function test_REQ_120_draft_note_disappears_when_legal_signs_off(): void {
		add_filter( 'wiw_legal_is_final', '__return_true' );
		$out = $this->render_page( 'privacy' );
		remove_filter( 'wiw_legal_is_final', '__return_true' );
		$this->assertStringNotContainsString( 'notice--draft', $out );
	}
}
