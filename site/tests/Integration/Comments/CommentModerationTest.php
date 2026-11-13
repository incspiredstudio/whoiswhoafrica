<?php
/**
 * Comment moderation rights and the moderator notice (REQ-258). Needs the WordPress test suite. Roles are reset in setUp because
 * other tests commit the transaction.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Comments;

use WhoIsWho\Core\Content\Comments\CommentModeratorNotice;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Tests\WpTestCase;

final class CommentModerationTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function comment( int $post_id, string $approved = '0' ): int {
		return (int) self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => 'Ada Obi',
				'comment_author_email' => 'ada.commenter@example.test',
				'comment_content'      => 'A held comment.',
				'comment_approved'     => $approved,
			)
		);
	}

	public function test_REQ_258_reviewer_cannot_moderate_comments(): void {
		$reviewer = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		$post     = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment  = $this->comment( $post );
		wp_set_current_user( $reviewer );
		$this->assertFalse( current_user_can( 'moderate_comments' ) );
		$this->assertFalse( current_user_can( 'edit_comment', $comment ) );
	}

	public function test_REQ_258_editor_can_approve_and_remove_comments(): void {
		$post    = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment = $this->comment( $post );
		foreach ( array( 'wiw_editor', 'administrator' ) as $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
			$this->assertTrue( current_user_can( 'moderate_comments' ), $role );
		}
		$this->assertTrue( wp_set_comment_status( $comment, 'approve' ) );
		$this->assertSame( '1', (string) get_comment( $comment )->comment_approved );
		$this->assertNotFalse( wp_delete_comment( $comment, true ) );
		$this->assertNull( get_comment( $comment ) );
	}

	public function test_REQ_258_moderator_notice_has_no_commenter_email(): void {
		$this->assertSame( 'capability:moderate_comments', Events::all()[ Events::COMMENT_HELD ]['audience'] );
		$notice  = new CommentModeratorNotice(
			static fn (): ?Notifier => null
		);
		$post    = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'The story title',
			)
		);
		$comment = $this->comment( $post );
		$context = $notice->context( get_comment( $comment ) );
		$json    = (string) wp_json_encode( $context );
		$this->assertStringContainsString( 'The story title', $json );
		foreach ( array( 'ada.commenter', 'example.test', 'Ada Obi', 'A held comment' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $json, 'nothing about the commenter is in the notice' );
		}
	}

	public function test_REQ_258_only_a_held_comment_triggers_a_notice(): void {
		$this->assertTrue( CommentModeratorNotice::is_held( 0 ) );
		$this->assertTrue( CommentModeratorNotice::is_held( '0' ) );
		$this->assertFalse( CommentModeratorNotice::is_held( 1 ) );
		$this->assertFalse( CommentModeratorNotice::is_held( 'spam' ) );
		$this->assertFalse( CommentModeratorNotice::is_held( 'trash' ) );
	}
}
