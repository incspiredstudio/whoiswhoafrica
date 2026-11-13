<?php
/**
 * The comments region of a story (REQ-258): approved comments with an initial, no email, the form with the consent tick and the
 * moderation note, and nothing on pages. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\WpTestCase;

final class StoryCommentsTemplateTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
	}

	/**
	 * Render the comments part for a post.
	 *
	 * @param int $post_id Post id.
	 */
	private function render( int $post_id ): string {
		ob_start();
		get_template_part( 'template-parts/comments', null, array( 'post_id' => $post_id ) );
		return (string) ob_get_clean();
	}

	public function test_REQ_258_a_story_shows_approved_comments_with_an_initial_and_never_the_email(): void {
		$post = self::factory()->post->create(
			array(
				'post_status'    => 'publish',
				'comment_status' => 'open',
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post,
				'comment_author'       => 'adaeze o.',
				'comment_author_email' => 'adaeze@example.org',
				'comment_content'      => 'A clear account.',
				'comment_approved'     => '1',
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post,
				'comment_author'   => 'Held Person',
				'comment_content'  => 'Not yet.',
				'comment_approved' => '0',
			)
		);
		$html = $this->render( $post );
		$this->assertStringContainsString( 'adaeze o.', $html );
		$this->assertStringContainsString( '>A<', str_replace( array( "\n", "\t" ), '', $html ) );
		$this->assertStringContainsString( 'A clear account.', $html );
		$this->assertStringNotContainsString( 'Not yet.', $html );
		$this->assertStringNotContainsString( 'adaeze@example.org', $html );
		$this->assertStringNotContainsString( 'gravatar', $html );
	}

	public function test_REQ_258_the_form_has_name_email_consent_and_the_moderation_note(): void {
		$post = self::factory()->post->create(
			array(
				'post_status'    => 'publish',
				'comment_status' => 'open',
			)
		);
		$html = $this->render( $post );
		$this->assertStringContainsString( 'name="author"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'name="wiw_comment_consent"', $html );
		$this->assertStringContainsString( 'Comments are checked before they appear', $html );
	}

	public function test_REQ_122_the_form_draws_the_hidden_honeypot_field(): void {
		$post = self::factory()->post->create(
			array(
				'post_status'    => 'publish',
				'comment_status' => 'open',
			)
		);
		$html = $this->render( $post );
		$this->assertMatchesRegularExpression( '/<input[^>]*name="wiw_comment_url"[^>]*tabindex="-1"[^>]*autocomplete="off"/', $html );
		$this->assertMatchesRegularExpression( '/<div[^>]*aria-hidden="true"[^>]*>\s*<label[^>]*>[^<]*<\/label>\s*<input[^>]*wiw_comment_url/', $html );
	}

	public function test_REQ_258_nothing_is_drawn_where_comments_are_closed_and_empty(): void {
		$post = self::factory()->post->create(
			array(
				'post_status'    => 'publish',
				'comment_status' => 'closed',
			)
		);
		$this->assertSame( '', trim( $this->render( $post ) ) );
	}

	public function test_REQ_258_a_held_comment_redirects_with_the_thank_you_marker(): void {
		$post    = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment = get_comment(
			(int) self::factory()->comment->create(
				array(
					'comment_post_ID'  => $post,
					'comment_approved' => '0',
				)
			)
		);
		$this->assertStringContainsString( 'wiw_comment=held', wiw_comment_redirect( 'http://x/', $comment ) );
		$this->assertContains( 'wiw_comment', wiw_comment_query_vars( array() ) );
	}
}
