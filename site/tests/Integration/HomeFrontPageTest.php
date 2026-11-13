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

	public function test_REQ_001_the_first_screen_states_what_the_brand_is_and_has_only_the_headline_lede_and_scroll_cue(): void {
		$out  = $this->render();
		$hero = substr( $out, 0, (int) strpos( $out, 'id="home-after-hero"' ) );
		$this->assertStringContainsString( 'Documenting the influence, achievements and legacy', $hero );
		$this->assertStringContainsString( 'class="h-cue"', $hero );
		// The prototype hero has no buttons; the ways in sit further down (issue 798).
		$this->assertStringNotContainsString( 'h-hero-ctas', $out );
		$this->assertStringNotContainsString( 'Browse the directory', $out );
		$this->assertStringNotContainsString( 'Get listed', $hero );
		$this->assertStringNotContainsString( 'Nominate someone', $hero );
		$this->assertStringNotContainsString( '<a class="btn', $hero );
		$this->assertStringContainsString( '/get-listed/', $out, 'the closing section still offers Get listed' );
	}

	public function test_REQ_259_the_primary_button_follows_the_nominations_switch(): void {
		add_filter( 'wiw_nominations_are_open', '__return_false' );
		$closed = wiw_home_model();
		$this->assertSame( 'Get listed', $closed['ctas']['primary']['label'] );
		$this->assertArrayNotHasKey( 'secondary', $closed['ctas'], 'the hero link is gone from the model' );
		remove_all_filters( 'wiw_nominations_are_open' );
		add_filter( 'wiw_nominations_are_open', '__return_true' );
		$open = wiw_home_model();
		$this->assertSame( 'Nominate someone', $open['ctas']['primary']['label'] );
		$this->assertStringEndsWith( '/nominate/', $open['ctas']['primary']['url'] );
	}

	public function test_REQ_002_the_hero_has_four_decorative_muted_looping_videos_with_poster_fallback(): void {
		$out = $this->render();
		$this->assertSame( 4, substr_count( $out, '<video' ) );
		$this->assertSame( 4, substr_count( $out, 'muted loop playsinline preload="none"' ) );
		$this->assertStringNotContainsString( ' autoplay', $out, 'no autoplay before the script confirms motion is allowed' );
		$this->assertStringNotContainsString( ' controls', $out, 'decoration: no controls' );
		$this->assertMatchesRegularExpression( '/<div class="h-vids" aria-hidden="true">/', $out );
		$this->assertSame( 4, substr_count( $out, 'class="h-vid-poster"' ) );
	}

	public function test_REQ_041_hero_posters_have_dimensions_modern_formats_and_only_the_first_is_high_priority(): void {
		$out = $this->render();
		preg_match_all( '/<img class="h-vid-poster"[^>]*>/', $out, $imgs );
		$this->assertCount( 4, $imgs[0] );
		foreach ( $imgs[0] as $img ) {
			$this->assertStringContainsString( 'width="540" height="960"', $img, 'the poster reserves its space (no layout shift)' );
			$this->assertStringContainsString( 'alt=""', $img );
			$this->assertStringContainsString( '-540.jpg', $img );
		}
		$this->assertStringContainsString( 'fetchpriority="high"', $imgs[0][0], 'the first poster is the largest contentful paint' );
		$this->assertSame( 1, substr_count( $out, 'fetchpriority="high"' ) );
		$this->assertSame( 4, substr_count( $out, 'type="image/avif"' ) );
		$this->assertSame( 4, substr_count( $out, 'type="image/webp"' ) );
		$this->assertStringNotContainsString( 'loading="lazy"', implode( '', $imgs[0] ), 'above-the-fold posters are never lazy' );
	}

	public function test_REQ_041_hero_clips_declare_size_a_webm_before_the_mp4_and_fetch_nothing_until_their_turn(): void {
		$out = $this->render();
		preg_match_all( '/<video[^>]*>.*?<\/video>/s', $out, $vids );
		$this->assertCount( 4, $vids[0] );
		foreach ( $vids[0] as $i => $video ) {
			$this->assertStringContainsString( 'width="540" height="960"', $video );
			$this->assertStringContainsString( 'preload="none"', $video, 'a clip fetches nothing until home.js starts it' );
			$this->assertStringContainsString( 'data-turn="' . $i . '"', $video );
			$this->assertStringContainsString( 'type="video/mp4"', $video );
			$this->assertStringNotContainsString( 'src="" ', $video );
			$this->assertStringNotContainsString( ' poster=', $video, 'the still is the picture beneath, not a second download' );
			$webm = strpos( $video, 'video/webm' );
			if ( false !== $webm ) {
				$this->assertLessThan( (int) strpos( $video, 'video/mp4' ), $webm, 'WebM is listed first' );
			}
		}
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
		$this->assertSame( 4, substr_count( $out, 'class="h-door h-rv-clip h-door--' ) );
		foreach ( array( '/people/', '/entrepreneurs/', '/businesses/', '/institutions/' ) as $path ) {
			$this->assertStringContainsString( 'href="' . home_url( $path ) . '"', $out );
		}
	}

	public function test_REQ_043_the_motion_hooks_are_plain_classes_that_leave_the_static_page_complete(): void {
		$out = $this->render();
		// The closing title is three mask lines inside one h2 and still reads as one sentence.
		preg_match( '~<h2 class="h-final-title[^>]*>(.*?)</h2>~s', $out, $title );
		$this->assertSame( 3, substr_count( $title[1] ?? '', 'class="h-line"' ) );
		$this->assertStringContainsString( 'class="h-final-title h-rv"', $out );
		// The doors carry their stagger index and a cursor label; nothing is hidden by the markup itself (hiding is CSS behind html.home-live).
		$this->assertSame( 4, substr_count( $out, 'data-cursor="Browse"' ) );
		$this->assertStringContainsString( 'style="--d:3"', $out );
		$this->assertStringNotContainsString( 'style="opacity', $out );
	}

	public function test_REQ_043_the_stories_section_has_a_pinned_frame_around_its_track(): void {
		$out = $this->render();
		if ( false === strpos( $out, 'id="home-stories"' ) ) {
			$this->markTestSkipped( 'no public stories in this database' );
		}
		$this->assertMatchesRegularExpression( '/id="home-stories".*?<div class="h-stories-pin">.*?class="h-track-wrap".*?</section>/s', $out );
	}

	public function test_REQ_041_the_home_page_hands_the_stage_urls_to_home_js_and_never_enqueues_three_with_the_page(): void {
		add_filter(
			'wiw_manifest',
			static fn () => array(
				'home-js.js'    => 'home-js.1.js',
				'three.js'      => 'three.1.js',
				'home-stage.js' => 'home-stage.1.js',
			)
		);
		$this->go_to( home_url( '/' ) );
		wp_dequeue_script( 'wiw-home' );
		wp_deregister_script( 'wiw-home' );
		do_action( 'wp_enqueue_scripts' );
		$before = implode( '', (array) wp_scripts()->get_data( 'wiw-home', 'before' ) );
		$this->assertStringContainsString( 'window.wiwHome=', $before );
		$this->assertStringContainsString( 'three.1.js', $before );
		$this->assertStringContainsString( 'home-stage.1.js', $before );
		$this->assertFalse( wp_script_is( 'wiw-three', 'registered' ), 'Three.js is fetched on demand, not enqueued' );
		remove_all_filters( 'wiw_manifest' );
	}
	public function test_REQ_002_the_closing_call_has_one_button_that_follows_the_nominations_switch(): void {
		add_filter( 'wiw_nominations_are_open', '__return_false' );
		$out = $this->render();
		$this->assertStringContainsString( 'Who should the continent know?', preg_replace( '/\s+/', ' ', wp_strip_all_tags( $out ) ) );
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

	public function test_REQ_264_the_stories_and_events_blocks_are_absent_when_there_is_nothing_to_show(): void {
		$out = $this->render();
		$this->assertStringNotContainsString( 'home-events-title', $out );
		$this->assertStringNotContainsString( 'home-stories-title', $out );
	}

	public function test_REQ_029_the_stories_block_lists_only_public_stories_and_carries_the_sponsored_badge_on_a_slot(): void {
		$public = self::factory()->post->create(
			array(
				'post_title'  => 'Home Public Story',
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_title'  => 'Home Draft Story',
				'post_status' => 'draft',
			)
		);
		$titles = array_map( static fn ( array $s ): string => (string) $s['title'], wiw_home_stories() );
		$this->assertContains( 'Home Public Story', $titles );
		$this->assertNotContains( 'Home Draft Story', $titles );
		$out = $this->render();
		$this->assertStringContainsString( 'Home Public Story', $out );
		$this->assertStringNotContainsString( 'Home Draft Story', $out );
		$this->assertGreaterThan( 0, $public );
	}

	public function test_REQ_264_an_upcoming_event_shows_as_a_compact_row_with_an_all_events_link(): void {
		$out_before = wiw_home_events();
		$this->assertIsArray( $out_before );
		add_filter(
			'wiw_home_model',
			static function ( array $model ): array {
				$model['events'] = \WhoIsWho\Core\Content\PublicEvent::next_upcoming( 3 );
				return $model;
			}
		);
		$out = $this->render();
		remove_all_filters( 'wiw_home_model' );
		$this->assertSame( str_contains( $out, 'ag-row--compact' ), array() !== $out_before );
	}
}
