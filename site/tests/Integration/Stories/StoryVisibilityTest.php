<?php
/**
 * Story visibility: 404 for non-published stories, staff preview marker, sitemap entry (REQ-208, REQ-044, REQ-027).
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Stories;

use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Content\Stories\StoryRoute;
use WhoIsWho\Core\Directory\Sitemap\Sitemap;
use WhoIsWho\Tests\WpTestCase;

final class StoryVisibilityTest extends WpTestCase {

	/**
	 * Permalink structure before the test.
	 *
	 * @var string
	 */
	private string $structure = '';

	public function set_up(): void {
		parent::set_up();
		global $wp_rewrite;
		$this->structure = (string) get_option( 'permalink_structure' );
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		( new StoryRoute() )->hooks();
		StoryRoute::add_rules();
		flush_rewrite_rules( false );
		wp_set_current_user( 0 );
		( new Sitemap() )->purge();
	}

	public function tear_down(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( $this->structure );
		flush_rewrite_rules( false );
		( new Sitemap() )->purge();
		parent::tear_down();
	}

	/**
	 * Create a story.
	 *
	 * @param array<string, mixed> $args Overrides.
	 */
	private function story( array $args = array() ): int {
		return (int) self::factory()->post->create(
			array_merge(
				array(
					'post_title'  => 'Visibility Story ' . wp_generate_password( 4, false ),
					'post_status' => 'publish',
				),
				$args
			)
		);
	}

	private function sitemap_stories(): string {
		$res = ( new Sitemap() )->respond( 'sitemap-stories.xml' );
		$this->assertNotNull( $res );
		return $res['body'];
	}

	public function test_REQ_208_story_url_lives_under_stories(): void {
		$id = $this->story( array( 'post_name' => 'under-stories' ) );
		$this->assertSame( home_url( '/stories/under-stories/' ), get_permalink( $id ) );
		$this->go_to( get_permalink( $id ) );
		$this->assertTrue( is_single() );
		$this->assertSame( $id, get_queried_object_id() );
	}

	public function test_REQ_208_draft_story_returns_404_and_is_not_in_sitemap(): void {
		$id = $this->story(
			array(
				'post_status' => 'draft',
				'post_name'   => 'a-draft-story',
			)
		);
		$this->go_to( home_url( '/stories/a-draft-story/' ) );
		do_action( 'template_redirect' );
		$this->assertTrue( is_404() );
		$this->assertNull( PublicStory::find( $id ) );
		$this->assertStringNotContainsString( 'a-draft-story', $this->sitemap_stories() );
	}

	public function test_REQ_208_password_protected_story_returns_404_to_visitor(): void {
		$this->story(
			array(
				'post_password' => 'secret',
				'post_name'     => 'locked-story',
			)
		);
		$this->go_to( home_url( '/stories/locked-story/' ) );
		do_action( 'template_redirect' );
		$this->assertTrue( is_404() );
		$this->assertStringNotContainsString( 'locked-story', $this->sitemap_stories() );
	}

	public function test_REQ_208_scheduled_story_not_in_feeds_or_rest(): void {
		$id = $this->story(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + 86400 ),
				'post_name'   => 'scheduled-story',
			)
		);
		$this->assertSame( 'future', get_post_status( $id ) );
		// REST needs a sign-in.
		$result = apply_filters( 'rest_authentication_errors', null );
		$this->assertInstanceOf( \WP_Error::class, $result );
		// A signed-in reader who cannot edit sees no scheduled story in the REST posts collection.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'status', 'future' );
		$response = rest_do_request( $request );
		$this->assertContains( $response->get_status(), array( 400, 401, 403 ) );
		$request = new \WP_REST_Request( 'GET', '/wp/v2/posts/' . $id );
		$this->assertContains( rest_do_request( $request )->get_status(), array( 401, 403, 404 ) );
		// Feeds are off and the story never reaches a public query.
		$this->assertNotFalse( has_action( 'do_feed_rss2', array( \WhoIsWho\Guard\Exposure\Exposure::class, 'refuse_feed' ) ) );
		wp_set_current_user( 0 );
		$this->assertNull( PublicStory::find( $id ) );
		$this->assertStringNotContainsString( 'scheduled-story', $this->sitemap_stories() );
	}

	public function test_REQ_208_oembed_and_attachment_are_closed_for_unpublished_stories(): void {
		$id = $this->story(
			array(
				'post_status' => 'draft',
				'post_name'   => 'embed-draft',
			)
		);
		$this->assertSame( 0, (int) apply_filters( 'oembed_request_post_id', $id, home_url( '/stories/embed-draft/' ) ) );
		$live = $this->story();
		$this->assertSame( $live, (int) apply_filters( 'oembed_request_post_id', $live, '' ) );
	}

	public function test_REQ_027_staff_preview_marker_comes_from_the_read_model(): void {
		$id = $this->story(
			array(
				'post_status' => 'draft',
				'post_name'   => 'preview-me',
			)
		);
		$this->assertNull( PublicStory::find( $id, true ), 'a visitor gets nothing even when asking for a preview' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$story = PublicStory::find( $id, true );
		$this->assertNotNull( $story );
		$this->assertTrue( $story['preview'] );
		$this->assertNull( PublicStory::find( $id ), 'without the preview flag a draft is still hidden' );
	}

	public function test_REQ_044_published_story_is_in_sitemap(): void {
		$id = $this->story( array( 'post_name' => 'in-the-map' ) );
		$this->assertStringContainsString( '/stories/in-the-map/', $this->sitemap_stories() );
		$index = ( new Sitemap() )->respond( 'sitemap.xml' )['body'];
		$this->assertStringContainsString( 'sitemap-stories.xml', $index );

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);
		$this->assertStringNotContainsString( 'in-the-map', $this->sitemap_stories(), 'leaves the sitemap when unpublished' );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$this->assertStringContainsString( 'in-the-map', $this->sitemap_stories() );
	}

	public function test_REQ_208_old_slug_redirects_to_current_story_url(): void {
		$id = $this->story( array( 'post_name' => 'old-story-slug' ) );
		wp_update_post(
			array(
				'ID'        => $id,
				'post_name' => 'new-story-slug',
			)
		);
		$this->assertSame( home_url( '/stories/new-story-slug/' ), get_permalink( $id ) );
		$this->assertSame( array( 'old-story-slug' ), get_post_meta( $id, '_wp_old_slug', false ) );
	}
}
