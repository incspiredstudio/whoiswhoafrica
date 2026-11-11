<?php
/**
 * Header, drawer and Nominate-link rule (REQ-259, REQ-229, REQ-043). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class HeaderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_nominations_are_open' );
		parent::tearDown();
	}

	/**
	 * Render a template part.
	 *
	 * @param string $part Part.
	 */
	private function render( string $part ): string {
		ob_start();
		get_template_part( $part );
		return (string) ob_get_clean();
	}

	public function test_REQ_259_nominate_link_is_absent_everywhere_while_the_flag_is_off(): void {
		$header = $this->render( 'template-parts/site-header' );
		$this->assertStringNotContainsString( '/nominate/', $header, 'header and drawer' );
		$this->assertStringNotContainsString( '/nominate/', $this->render( 'template-parts/site-footer' ), 'footer' );
	}

	public function test_REQ_259_nominate_link_shows_in_header_drawer_and_footer_when_open(): void {
		add_filter( 'wiw_nominations_are_open', '__return_true' );
		$header = $this->render( 'template-parts/site-header' );
		$this->assertGreaterThanOrEqual( 2, substr_count( $header, '/nominate/' ), 'mega menu and drawer' );
		$this->assertStringContainsString( '/nominate/', $this->render( 'template-parts/site-footer' ) );
	}

	public function test_REQ_229_no_sign_in_sign_up_or_account_menu(): void {
		$header = strtolower( $this->render( 'template-parts/site-header' ) );
		foreach ( array( 'sign in', 'sign up', 'log in', 'login', 'register', 'my account', 'wp-login' ) as $word ) {
			$this->assertStringNotContainsString( $word, $header, $word );
		}
		$this->assertStringContainsString( 'get listed', $header );
	}

	public function test_REQ_043_drawer_and_search_are_labelled_dialogs_and_menu_buttons_state_their_target(): void {
		$header = $this->render( 'template-parts/site-header' );
		$this->assertStringContainsString( 'id="drawer" role="dialog" aria-modal="true"', $header );
		$this->assertStringContainsString( 'id="searchOv" role="dialog" aria-modal="true"', $header );
		$this->assertStringContainsString( 'aria-controls="mega-directory"', $header );
		$this->assertStringContainsString( 'aria-expanded="false"', $header );
		$this->assertStringContainsString( '<details>', $header );
		$this->assertStringContainsString( 'aria-label="Main"', $header );
	}

	public function test_REQ_259_the_groups_are_directory_awards_stories_events_about(): void {
		$model = wiw_nav_model();
		$this->assertSame( array( 'Directory', 'Awards', 'Stories', 'Events', 'About' ), array_map( static fn ( $k ) => $model[ $k ]['label'], array( 'directory', 'awards', 'stories', 'events', 'about' ) ) );
	}
}
