<?php
/**
 * Rules for stories: a story needs one of the six types before it can be public (REQ-027).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

/**
 * Story rules.
 */
final class StoryRules {

	/**
	 * Guards against re-entry while a post is sent back to draft.
	 *
	 * @var bool
	 */
	private static bool $busy = false;

	/**
	 * Whether the story has a valid type.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function has_valid_type( int $post_id ): bool {
		return '' !== StoryTypes::of( $post_id );
	}

	/**
	 * Send a story without a type back to draft. Runs after the post and its terms are saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function enforce_type( int $post_id, \WP_Post $post ): void {
		if ( self::$busy || 'post' !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
			return;
		}
		if ( self::has_valid_type( $post_id ) ) {
			return;
		}
		self::$busy = true;
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		self::$busy = false;
	}
}
