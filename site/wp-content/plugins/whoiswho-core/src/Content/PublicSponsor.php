<?php
/**
 * The only way public code reads sponsor records (REQ-186, REQ-199, REQ-183). A sponsor has a name, a logo and a link, nothing
 * commercial. This read model lives in Content so that the Awards pages can show a "Supported by" disclosure without referencing
 * the commercial module. A sponsor has no effect on any nomination, winner, profile or label.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content;

/**
 * Public sponsor read model.
 */
final class PublicSponsor {

	public const TYPE      = 'wiw_sponsor';
	public const META_LINK = 'wiw_sponsor_link';
	public const META_ATT  = 'wiw_sponsor_attached';

	/**
	 * The disclosure that goes with every Supported by block.
	 */
	public static function disclosure(): string {
		return __( 'Sponsors support this programme. They do not choose or influence nominees or winners.', 'whoiswho' );
	}

	/**
	 * Published sponsors attached to a page or an award category, in title order.
	 *
	 * @param string $kind page or award_category.
	 * @param int    $id   Page id or category id.
	 * @return array<int, array{name: string, logo_url: string, link: string}>
	 */
	public static function for_subject( string $kind, int $id ): array {
		$ref   = $kind . ':' . $id;
		$posts = get_posts(
			array(
				'post_type'        => self::TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => 50,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, bounded set of sponsors.
					array(
						'key'   => self::META_ATT,
						'value' => $ref,
					),
				),
			)
		);
		$out   = array();
		foreach ( $posts as $post ) {
			$out[] = self::card( $post );
		}
		return $out;
	}

	/**
	 * A sponsor card: name, logo and link only.
	 *
	 * @param \WP_Post $post Sponsor post.
	 * @return array{name: string, logo_url: string, link: string}
	 */
	private static function card( \WP_Post $post ): array {
		$logo = get_the_post_thumbnail_url( $post, 'medium' );
		return array(
			'name'     => get_the_title( $post ),
			'logo_url' => is_string( $logo ) ? $logo : '',
			'link'     => esc_url_raw( (string) get_post_meta( $post->ID, self::META_LINK, true ) ),
		);
	}
}
