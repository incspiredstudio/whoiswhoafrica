<?php
/**
 * Comment data in the PII catalogue, export and erase flow (REQ-258, REQ-059). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Comments;

use WhoIsWho\Core\Content\Comments\CommentPrivacy;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\WpTestCase;

final class CommentPrivacyTest extends WpTestCase {

	/**
	 * A comment by an address.
	 *
	 * @param string $email Address.
	 * @param string $text  Text.
	 * @return int Comment id.
	 */
	private function comment( string $email, string $text ): int {
		$post = self::factory()->post->create();
		return (int) self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post,
				'comment_author'       => 'A Reader',
				'comment_author_email' => $email,
				'comment_author_IP'    => '203.0.113.9',
				'comment_content'      => $text,
				'comment_approved'     => '1',
			)
		);
	}

	public function test_REQ_258_export_for_email_includes_comments(): void {
		$mine = $this->comment( 'reader@example.org', 'My view on this story.' );
		$this->comment( 'other@example.org', 'Someone else.' );

		$rows = ( new CommentPrivacy() )->export_for_email( 'Reader@Example.org' );

		$this->assertCount( 1, $rows );
		$this->assertSame( $mine, $rows[0]['id'] );
		$this->assertSame( 'My view on this story.', $rows[0]['content'] );
		$this->assertSame( 'reader@example.org', $rows[0]['email'] );
	}

	public function test_REQ_258_erase_request_removes_or_anonymises_comments(): void {
		$mine  = $this->comment( 'reader@example.org', 'Remove my details.' );
		$other = $this->comment( 'other@example.org', 'Keep me.' );

		$count = ( new CommentPrivacy() )->erase_for_email( 'reader@example.org' );

		$this->assertSame( 1, $count );
		$erased = get_comment( $mine );
		$this->assertSame( '', $erased->comment_author_email );
		$this->assertSame( '', $erased->comment_author_IP );
		$this->assertNotSame( 'A Reader', $erased->comment_author );
		$this->assertSame( 'other@example.org', get_comment( $other )->comment_author_email );
		$this->assertSame( array(), ( new CommentPrivacy() )->export_for_email( 'reader@example.org' ) );
	}

	public function test_REQ_258_wpdiscuz_tables_are_in_the_pii_catalogue(): void {
		$names = ( new PiiCatalogue() )->names( 'column' );

		foreach ( array( 'comments.comment_author_email', 'wc_comments_subscription.email', 'wc_follow_users.follower_email' ) as $expected ) {
			$this->assertContains( $expected, $names );
		}
	}
}
