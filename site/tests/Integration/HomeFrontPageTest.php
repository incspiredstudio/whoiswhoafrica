<?php
/**
 * Front page shell, hero and header state (M2-HOM-08): REQ-001, REQ-002, REQ-052, REQ-190, REQ-259. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\WpTestCase;

final class HomeFrontPageTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_nominations_are_open' );
		parent::tearDown();
	}

	/**
	 * Render the front page.
	 */
	private function render(): string {
		$this->go_to( home_url( '/' ) );
		ob_start();
		include get_template_directory() . '/front-page.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_052_the_title_comes_from_the_brand_config_split_in_two_lines(): void {
		$this->assertSame( array( 'Who is who', 'in Africa.' ), wiw_home_title_lines( 'Who Is Who In Africa' ) );
		$this->assertSame( array( 'Solo', '' ), wiw_home_title_lines( 'Solo' ) );
		$out = $this->render();
		$this->assertSame( 1, substr_count( $out, '<h1' ), 'one h1' );
		$this->assertStringContainsString( 'Who is who', $out );
		$this->assertStringContainsString( 'in Africa.', $out );
	}

	public function test_REQ_001_the_first_screen_states_what_the_brand_is_and_offers_a_way_in(): void {
		$out = $this->render();
		$this->assertStringContainsString( 'Documenting the influence, achievements and legacy', $out );
		$this->assertStringContainsString( '/get-listed/', $out );
		$this->assertStringContainsString( 'Browse the directory', $out );
	}

	public function test_REQ_259_the_primary_button_follows_the_nominations_switch(): void {
		add_filter( 'wiw_nominations_are_open', '__return_false' );
		$closed = wiw_home_model();
		$this->assertSame( 'Get listed', $closed['ctas']['primary']['label'] );
		remove_all_filters( 'wiw_nominations_are_open' );
		add_filter( 'wiw_nominations_are_open', '__return_true' );
		$open = wiw_home_model();
		$this->assertSame( 'Nominate someone', $open['ctas']['primary']['label'] );
		$this->assertStringEndsWith( '/nominate/', $open['ctas']['primary']['url'] );
	}

	public function test_REQ_002_the_hero_has_four_decorative_muted_looping_videos_with_poster_fallback(): void {
		$out = $this->render();
		$this->assertSame( 4, substr_count( $out, '<video' ) );
		$this->assertStringContainsString( 'muted loop playsinline preload="none"', $out );
		$this->assertStringNotContainsString( ' autoplay', $out, 'no autoplay before the script confirms motion is allowed' );
		$this->assertMatchesRegularExpression( '/<div class="h-vids" aria-hidden="true">/', $out );
		$this->assertSame( 4, substr_count( $out, 'poster="' ) );
	}

	public function test_REQ_046_the_header_state_script_is_inline_in_the_head_and_marks_the_home_page(): void {
		$this->go_to( home_url( '/' ) );
		ob_start();
		wiw_print_header_state_script();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'data-home="1"', $html );
		$this->assertStringContainsString( 'is-home', $html );
	}

	public function test_REQ_001_the_three_acts_use_the_tagline_words_with_a_mark_and_a_link_each(): void {
		$out = $this->render();
		$this->assertSame( 3, substr_count( $out, 'class="h-act h-act--' ) );
		foreach ( array( 'Influence.', 'Achievement.', 'Legacy.' ) as $word ) {
			$this->assertStringContainsString( $word, $out );
		}
		$this->assertStringContainsString( '/people/', $out );
		$this->assertStringContainsString( '/awards/', $out );
		$this->assertStringContainsString( '/stories/', $out );
		$this->assertSame( 3, substr_count( $out, 'h-act-mark' ) );
	}

	public function test_REQ_001_the_four_doors_link_to_the_four_directory_lists(): void {
		$out = $this->render();
		$this->assertSame( 4, substr_count( $out, 'class="h-door h-door--' ) );
		foreach ( array( '/people/', '/entrepreneurs/', '/businesses/', '/institutions/' ) as $path ) {
			$this->assertStringContainsString( 'href="' . home_url( $path ) . '"', $out );
		}
	}

	public function test_REQ_002_the_closing_call_has_one_button_that_follows_the_nominations_switch(): void {
		add_filter( 'wiw_nominations_are_open', '__return_false' );
		$out = $this->render();
		$this->assertStringContainsString( 'Who should the continent know?', $out );
		$this->assertSame( 1, substr_count( $out, 'class="h-final-cta"' ) );
		$part = substr( $out, (int) strpos( $out, 'h-final-cta' ) );
		$this->assertStringContainsString( 'Get listed', $part );
		$this->assertStringNotContainsString( 'Nominate someone', $part );
		remove_all_filters( 'wiw_nominations_are_open' );
		add_filter( 'wiw_nominations_are_open', '__return_true' );
		$open = $this->render();
		$this->assertStringContainsString( 'Nominate someone', substr( $open, (int) strpos( $open, 'h-final-cta' ) ) );
	}

	public function test_REQ_218_the_on_the_record_block_is_absent_when_no_public_profile_qualifies(): void {
		$this->assertSame( array(), wiw_home_record() );
		$out = $this->render();
		$this->assertStringNotContainsString( 'home-record-title', $out );
		$this->assertStringNotContainsString( 'h-peek', $out );
	}

	public function test_REQ_218_the_block_reads_profiles_only_from_the_curation_engine_and_shows_no_trust_mark(): void {
		$part = (string) file_get_contents( get_template_directory() . '/template-parts/home-record.php' );
		$this->assertStringNotContainsString( 'get_post_meta', $part );
		$this->assertStringNotContainsString( 'WP_Query', $part );
		$this->assertStringNotContainsString( 'verified', strtolower( $part ) );
		$this->assertStringNotContainsString( 'sponsored', strtolower( $part ) );
	}
}
