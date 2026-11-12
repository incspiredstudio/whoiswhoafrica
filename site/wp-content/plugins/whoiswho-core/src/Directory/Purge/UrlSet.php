<?php
/**
 * The public URLs a profile appears on: the page itself, the list pages, its industry pages, the sitemap and the share image
 * (REQ-209, REQ-217). Purging this set is what makes a state change visible at once.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Purge;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Directory\Url\ProfileSlugs;

/**
 * URL set builder.
 */
final class UrlSet {

	/**
	 * URLs to purge for one profile, without duplicates.
	 *
	 * @param int $profile_id Profile post id.
	 * @return string[]
	 */
	public function for_profile( int $profile_id ): array {
		$post = get_post( $profile_id );
		if ( ! $post instanceof \WP_Post || ProfileType::POST_TYPE !== $post->post_type ) {
			return array();
		}
		$urls = array( ProfileSlugs::url_for_slug( $post->post_name ) );

		// List pages the profile can appear on; the list templates add their own through the filter.
		$urls[] = home_url( '/directory/' );
		$urls[] = home_url( '/directory/' . $post->post_name . '/' );

		$industries = wp_get_object_terms( $profile_id, Taxonomies::INDUSTRY );
		if ( is_array( $industries ) ) {
			foreach ( $industries as $term ) {
				$link = get_term_link( $term );
				if ( is_string( $link ) ) {
					$urls[] = $link;
				}
			}
		}
		$urls[] = home_url( '/industries/' );
		$urls[] = home_url( '/sitemap.xml' );

		$image = (int) get_post_meta( $profile_id, PublicProfile::IMAGE_META, true );
		if ( $image > 0 ) {
			$src = wp_get_attachment_url( $image );
			if ( is_string( $src ) && '' !== $src ) {
				$urls[] = $src;
			}
		}

		/**
		 * Extra URLs to purge for a profile (list pages, share image routes).
		 *
		 * @param string[] $urls       URLs so far.
		 * @param int      $profile_id Profile.
		 */
		$urls = apply_filters( 'wiw_purge_profile_urls', $urls, $profile_id );
		return array_values( array_unique( array_filter( $urls, 'is_string' ) ) );
	}
}
