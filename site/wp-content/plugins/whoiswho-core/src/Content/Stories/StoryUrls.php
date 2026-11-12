<?php
/**
 * The addresses of public stories, for the sitemap (REQ-044, REQ-208): published, not password-protected. Drafts, scheduled,
 * pending, private and trashed stories never appear.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

/**
 * Public story addresses.
 */
final class StoryUrls {

	/**
	 * Public stories, oldest first.
	 *
	 * @param int $limit Most to return.
	 * @return array<int, array{url: string, modified: string}>
	 */
	public static function published( int $limit ): array {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => $limit,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( null !== $post ) {
				$out[] = array(
					'url'      => (string) get_permalink( $post ),
					'modified' => $post->post_modified_gmt,
				);
			}
		}
		return $out;
	}
}
