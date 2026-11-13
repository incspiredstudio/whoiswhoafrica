<?php
/**
 * Export and erase of story comments by email address (REQ-258, REQ-059). The comment columns are in the PII catalogue (erase:
 * anonymise). Comments stay while the story is public, so an erase request clears the commenter's name, address and IP rather
 * than deleting the text; this runs for the data-request flow and, through the WordPress hooks, for the core Privacy tools.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Comments;

/**
 * Comment privacy.
 */
final class CommentPrivacy {

	public const ANONYMOUS = 'Anonymous';

	/**
	 * Comments by an address.
	 *
	 * @param string $email Address (compared without regard to case).
	 * @return array<int, array{id: int, post: int, name: string, email: string, ip: string, content: string, date: string}>
	 */
	public function export_for_email( string $email ): array {
		$email = strtolower( trim( $email ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return array();
		}
		$rows = array();
		foreach ( $this->comments( $email ) as $comment ) {
			$rows[] = array(
				'id'      => (int) $comment->comment_ID,
				'post'    => (int) $comment->comment_post_ID,
				'name'    => (string) $comment->comment_author,
				'email'   => strtolower( (string) $comment->comment_author_email ),
				'ip'      => (string) $comment->comment_author_IP,
				'content' => (string) $comment->comment_content,
				'date'    => (string) $comment->comment_date_gmt,
			);
		}
		return $rows;
	}

	/**
	 * Anonymise every comment by an address: name replaced, email and IP cleared, text kept.
	 *
	 * @param string $email Address.
	 * @return int Comments anonymised.
	 */
	public function erase_for_email( string $email ): int {
		$email = strtolower( trim( $email ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $this->comments( $email ) as $comment ) {
			wp_update_comment(
				array(
					'comment_ID'           => (int) $comment->comment_ID,
					'comment_author'       => self::ANONYMOUS,
					'comment_author_email' => '',
					'comment_author_IP'    => '',
					'comment_author_url'   => '',
				)
			);
			++$count;
		}
		return $count;
	}

	/**
	 * Comments of any status by an address.
	 *
	 * @param string $email Lower-case address.
	 * @return \WP_Comment[]
	 */
	private function comments( string $email ): array {
		$found = get_comments(
			array(
				'author_email' => $email,
				'status'       => 'all',
				'number'       => 0,
			)
		);
		return array_values(
			array_filter(
				$found,
				static fn ( $comment ): bool => $comment instanceof \WP_Comment && strtolower( (string) $comment->comment_author_email ) === $email
			)
		);
	}
}
