<?php
/**
 * WordPress's own sample content (REQ-056): a new WordPress site publishes "Hello world!" (with a sample comment) and a
 * "Sample Page". On first run they are moved to the trash, but only when they are untouched WordPress defaults: the default id
 * (1 or 2), the default slug, and never edited (modified equals created). Anything the owner changed is kept. It runs once per
 * site, so a post put back from the trash stays.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

/**
 * Default content.
 */
final class DefaultContent {

	public const OPTION = 'wiw_default_content_retired';

	/**
	 * The defaults: id => post type and slug.
	 */
	private const DEFAULTS = array(
		1 => array( 'post', 'hello-world' ),
		2 => array( 'page', 'sample-page' ),
	);

	/**
	 * Move the untouched defaults to the trash, once.
	 *
	 * @return int[] Ids moved to the trash.
	 */
	public static function retire(): array {
		if ( '1' === get_option( self::OPTION ) ) {
			return array();
		}
		update_option( self::OPTION, '1', false );
		$done = array();
		foreach ( self::DEFAULTS as $id => $default ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post && self::untouched( $post, $default[0], $default[1] ) && false !== wp_trash_post( $id ) ) {
				$done[] = $id;
			}
		}
		return $done;
	}

	/**
	 * Whether a post is still WordPress's untouched default.
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $type Default post type.
	 * @param string   $slug Default slug.
	 */
	private static function untouched( \WP_Post $post, string $type, string $slug ): bool {
		return $type === $post->post_type
			&& $slug === $post->post_name
			&& 'publish' === $post->post_status
			&& $post->post_modified_gmt === $post->post_date_gmt
			&& $post->post_modified === $post->post_date;
	}
}
