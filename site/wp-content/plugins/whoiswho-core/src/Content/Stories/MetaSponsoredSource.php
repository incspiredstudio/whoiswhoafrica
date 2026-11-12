<?php
/**
 * The default sponsored source: reads the sponsor fields that StoryFlags writes on the story (REQ-263, REQ-182). It only reads.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

use WhoIsWho\Core\Content\SponsoredSource;

/**
 * Story-field sponsored source.
 */
final class MetaSponsoredSource implements SponsoredSource {

	/**
	 * A story's placement.
	 *
	 * @param int $story_id Story.
	 * @return array{sponsor: string, placement: string, start: string, end: string}|null
	 */
	public function placement( int $story_id ): ?array {
		$name = (string) get_post_meta( $story_id, 'wiw_sponsor_name', true );
		if ( '' === $name ) {
			return null;
		}
		return array(
			'sponsor'   => $name,
			'placement' => (string) get_post_meta( $story_id, 'wiw_sponsor_placement', true ),
			'start'     => (string) get_post_meta( $story_id, 'wiw_sponsor_start', true ),
			'end'       => (string) get_post_meta( $story_id, 'wiw_sponsor_end', true ),
		);
	}

	/**
	 * Stories whose placement covers a surface.
	 *
	 * @param string $surface home or stories.
	 * @return array<int, int>
	 */
	public function story_ids( string $surface ): array {
		if ( ! in_array( $surface, array( 'home', 'stories' ), true ) ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a handful of sponsored stories.
					array(
						'key'     => 'wiw_sponsor_placement',
						'value'   => array( $surface, 'both' ),
						'compare' => 'IN',
					),
				),
			)
		);
		return array_map( 'intval', $ids );
	}
}
