<?php
/**
 * Stories list template and route (REQ-029, REQ-249, REQ-026, REQ-263, REQ-257). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Tests\Support\FixtureSponsoredSource;
use WhoIsWho\Tests\TestCase;

final class StoriesListTemplateTest extends TestCase {

	/**
	 * Fixture sponsored source.
	 *
	 * @var FixtureSponsoredSource
	 */
	private FixtureSponsoredSource $source;

	/**
	 * Author of the stories.
	 *
	 * @var int
	 */
	private int $author = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		$this->author = (int) wp_insert_user(
			array(
				'user_login'   => 'storieswriter' . wp_rand(),
				'user_pass'    => wp_generate_password(),
				'display_name' => 'Ada Writer',
			)
		);
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
		StoryTypes::register();
		StoryTypes::ensure_terms();
		StoryFields::register_meta();
		foreach ( get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $leftover ) {
			wp_delete_post( (int) $leftover, true );
		}
		update_option( 'sticky_posts', array() );
		$this->source = new FixtureSponsoredSource();
		add_filter( 'wiw_sponsored_source', fn (): FixtureSponsoredSource => $this->source );
		add_filter( 'wiw_story_today', static fn (): string => '2026-12-10' );
		$this->reset_vars();
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_sponsored_source' );
		remove_all_filters( 'wiw_story_today' );
		remove_all_actions( 'wiw_stories_signup' );
		$this->reset_vars();
		parent::tearDown();
	}

	/**
	 * Clear the list query variables.
	 */
	private function reset_vars(): void {
		foreach ( array( 'wiw_stories', 'paged', 'type', 'industry' ) as $var ) {
			set_query_var( $var, '' );
		}
	}

	/**
	 * Create a published story.
	 *
	 * @param string $title Title.
	 * @param string $type  Story type slug.
	 * @param string $date  Post date.
	 */
	private function story( string $title, string $type = 'news', string $date = '2026-11-01 10:00:00' ): int {
		$id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => 'Body text.',
				'post_excerpt' => 'One line about ' . $title,
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => $date,
				'post_author'  => $this->author,
			)
		);
		$this->assertIsInt( $id );
		wp_set_object_terms( $id, $type, StoryTypes::TAXONOMY );
		return $id;
	}

	/**
	 * Render the list template.
	 */
	private function render(): string {
		set_query_var( 'wiw_stories', '1' );
		ob_start();
		include get_template_directory() . '/templates/stories.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_029_the_route_is_registered_and_the_page_has_no_hero_events_filter_or_events_strip(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules( false );
		$rules = (array) $wp_rewrite->wp_rewrite_rules();
		$this->assertArrayHasKey( '^stories/?$', $rules );
		$this->assertArrayHasKey( '^stories/page/([0-9]+)/?$', $rules );
		$this->assertStringEndsWith( '/stories/', wiw_stories_url() );
		$this->assertStringEndsWith(
			'/stories/page/2/?type=news',
			wiw_stories_url(
				array(
					'type' => 'news',
					'page' => 2,
				)
			)
		);
		$this->story( 'Stories Alpha' );
		$out = $this->render();
		$this->assertSame( 1, substr_count( $out, '<h1' ) );
		$this->assertStringNotContainsString( 'ev-hero', $out );
		$this->assertStringNotContainsString( 'name="city"', $out, 'no Events filter' );
		$this->assertStringNotContainsString( 'Upcoming', $out, 'no upcoming-events strip' );
		$wp_rewrite->set_permalink_structure( '' );
		$wp_rewrite->flush_rules( false );
	}

	public function test_REQ_249_cards_show_type_date_reading_time_headline_lede_and_author_newest_first(): void {
		$this->story( 'Stories Older', 'interview', '2026-10-01 10:00:00' );
		$this->story( 'Stories Newer', 'feature', '2026-11-05 10:00:00' );
		$this->story( 'Stories Middle', 'news', '2026-10-20 10:00:00' );
		$out = $this->render();
		$this->assertLessThan( strpos( $out, 'Stories Older' ), strpos( $out, 'Stories Newer' ), 'newest first' );
		$this->assertStringContainsString( 'Feature', $out );
		$this->assertStringContainsString( '5 Nov 2026', $out );
		$this->assertMatchesRegularExpression( '/\d+ min read/', $out );
		$this->assertStringContainsString( 'One line about Stories Newer', $out );
		$this->assertMatchesRegularExpression( '/By\s+\S+/', $out );
		$this->assertSame( 1, substr_count( $out, 'scard--lead' ) );
		$this->assertSame( 2, substr_count( $out, 'scard--tile' ) );
	}

	public function test_REQ_029_filtering_by_type_narrows_and_a_filter_with_no_match_offers_to_clear(): void {
		$this->story( 'Stories Interview One', 'interview' );
		$this->story( 'Stories News One', 'news' );
		set_query_var( 'type', 'interview' );
		$out = $this->render();
		$this->assertStringContainsString( 'Stories Interview One', $out );
		$this->assertStringNotContainsString( 'Stories News One', $out );
		$this->assertStringContainsString( 'name="type" value="interview"', $out, 'the type survives an industry change' );
		set_query_var( 'type', 'opinion' );
		$none = $this->render();
		$this->assertStringContainsString( 'No stories match', $none );
		$this->assertStringContainsString( 'Clear filters', $none );
		set_query_var( 'type', 'not-a-type' );
		$this->assertStringContainsString( 'No stories match', $this->render() );
	}

	public function test_REQ_026_a_draft_is_never_named(): void {
		$this->story( 'Stories Public One' );
		wp_insert_post(
			array(
				'post_title'  => 'Stories Hidden Draft',
				'post_status' => 'draft',
				'post_type'   => 'post',
			)
		);
		$out = $this->render();
		$this->assertStringContainsString( 'Stories Public One', $out );
		$this->assertStringNotContainsString( 'Stories Hidden Draft', $out );
	}

	public function test_REQ_263_a_sponsored_story_shows_the_badge_and_sponsor_in_its_slot_once_and_only_between_dates(): void {
		$id = $this->story( 'Stories Sponsored One' );
		$this->story( 'Stories Other One' );
		$this->source->placements[ $id ] = array(
			'sponsor'   => 'Acme Sponsor',
			'placement' => 'stories',
			'start'     => '2026-12-01',
			'end'       => '2026-12-31',
		);
		$out                             = $this->render();
		$this->assertStringContainsString( 'stories-slot', $out );
		$this->assertStringContainsString( 'badge--sponsored', $out );
		$this->assertStringContainsString( 'Paid for by Acme Sponsor', $out );
		$this->assertSame( 1, substr_count( $out, '>Stories Sponsored One</a>' ), 'shown once' );
		$this->source->placements[ $id ]['end'] = '2026-12-05';
		$after                                  = $this->render();
		$this->assertStringNotContainsString( 'stories-slot', $after );
		$this->assertStringNotContainsString( 'badge--sponsored', $after );
		$this->assertStringContainsString( 'Stories Sponsored One', $after, 'the story stays after the dates end' );
	}

	public function test_REQ_257_the_signup_block_is_filled_by_the_newsletter_hook_after_the_first_nine_stories(): void {
		for ( $i = 1; $i <= 11; $i++ ) {
			$this->story( sprintf( 'Stories Many %02d', $i ), 'news', sprintf( '2026-10-%02d 10:00:00', $i ) );
		}
		$this->assertStringNotContainsString( 'stories-signup', $this->render(), 'no sign-up handler, no block' );
		add_action(
			'wiw_stories_signup',
			static function (): void {
				echo '<p>SIGNUP-FORM</p>';
			}
		);
		$out = $this->render();
		$this->assertStringContainsString( 'Get new stories in your inbox.', $out );
		$at = (int) strpos( $out, 'SIGNUP-FORM' );
		$this->assertSame( 6, substr_count( substr( $out, 0, $at ), '<article class="scard scard--card' ), 'six cards in the grid before the sign-up' );
		$this->assertSame( 2, substr_count( substr( $out, $at ), '<article class="scard scard--card' ), 'the rest follow it' );
	}

	public function test_REQ_029_the_cover_size_is_a_registered_16_9_crop(): void {
		$sizes = wp_get_additional_image_sizes();
		$this->assertArrayHasKey( 'wiw-story', $sizes );
		$this->assertSame( 960, $sizes['wiw-story']['width'] );
		$this->assertSame( 540, $sizes['wiw-story']['height'] );
		$this->assertTrue( $sizes['wiw-story']['crop'] );
	}
}
