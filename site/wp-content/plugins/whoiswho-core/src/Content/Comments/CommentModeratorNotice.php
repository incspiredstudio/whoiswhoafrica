<?php
/**
 * Tell the moderators when a comment is waiting (REQ-258). Only a comment that is held (not spam, not already approved) sends a
 * notice, to every active holder of moderate_comments (Editors and Administrators; a Reviewer is not one). The notice names the
 * story and says a comment is waiting; it carries nothing about the commenter: no name, no email, no text.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Comments;

use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Comment moderator notice.
 */
final class CommentModeratorNotice {

	/**
	 * Returns the notifier to send with (the site's by default).
	 *
	 * @var callable
	 */
	private $notifier;

	/**
	 * Constructor.
	 *
	 * @param callable|null $notifier Returns a Notifier, or null to send nothing.
	 */
	public function __construct( ?callable $notifier = null ) {
		$this->notifier = $notifier ?? static fn (): Notifier => Notifier::for_site();
	}

	/**
	 * Register the hook.
	 */
	public function register(): void {
		add_action( 'comment_post', array( $this, 'on_comment' ), 20, 2 );
	}

	/**
	 * Whether a comment verdict means it is waiting for a moderator.
	 *
	 * @param int|string $approved Verdict.
	 */
	public static function is_held( $approved ): bool {
		return 0 === $approved || '0' === $approved;
	}

	/**
	 * A comment was saved.
	 *
	 * @param int        $comment_id Comment id.
	 * @param int|string $approved   Verdict.
	 */
	public function on_comment( $comment_id, $approved ): void {
		if ( ! self::is_held( $approved ) ) {
			return;
		}
		$comment  = get_comment( (int) $comment_id );
		$notifier = ( $this->notifier )();
		if ( ! $comment instanceof \WP_Comment || ! $notifier instanceof Notifier ) {
			return;
		}
		$notifier->send( Events::COMMENT_HELD, '', $this->context( $comment ) );
	}

	/**
	 * What the notice says: the story, and where to moderate. Nothing about the commenter.
	 *
	 * @param \WP_Comment $comment Comment.
	 * @return array<string, string>
	 */
	public function context( \WP_Comment $comment ): array {
		return array(
			'lead' => sprintf(
				/* translators: 1: story title, 2: moderation address. */
				__( 'A comment is waiting for approval on "%1$s". Moderate it here: %2$s', 'whoiswho' ),
				get_the_title( (int) $comment->comment_post_ID ),
				admin_url( 'edit-comments.php?comment_status=moderated' )
			),
		);
	}
}
