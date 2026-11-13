<?php
/**
 * The wiw_sponsor post type for the "Supported by" disclosure (REQ-186, REQ-199). A record has a name (the title), a logo (the
 * featured image) and a link, and nothing else: no editor, no commercial field. It has no public URL of its own. It is managed
 * under wiw_set_sponsored and attached to a page or an award category through post meta, so no Awards table or class is involved.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Content\PublicSponsor;

/**
 * Sponsor post type.
 */
final class SponsorPostType {

	public const CAP = 'wiw_set_sponsored';

	/**
	 * Hook registration.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register' ), 5 );
	}

	/**
	 * Register the type and its two meta keys.
	 */
	public static function register(): void {
		register_post_type(
			PublicSponsor::TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Supported by', 'whoiswho' ),
					'singular_name' => __( 'Sponsor', 'whoiswho' ),
					'add_new_item'  => __( 'Add sponsor', 'whoiswho' ),
					'edit_item'     => __( 'Edit sponsor', 'whoiswho' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'supports'            => array( 'title', 'thumbnail' ),
				'capability_type'     => array( 'wiw_sponsor', 'wiw_sponsors' ),
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'edit_posts'             => self::CAP,
					'edit_others_posts'      => self::CAP,
					'publish_posts'          => self::CAP,
					'read_private_posts'     => self::CAP,
					'delete_posts'           => self::CAP,
					'delete_private_posts'   => self::CAP,
					'delete_published_posts' => self::CAP,
					'delete_others_posts'    => self::CAP,
					'edit_private_posts'     => self::CAP,
					'edit_published_posts'   => self::CAP,
				),
			)
		);
		register_post_meta(
			PublicSponsor::TYPE,
			PublicSponsor::META_LINK,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_post_meta(
			PublicSponsor::TYPE,
			PublicSponsor::META_ATT,
			array(
				'type'              => 'string',
				'single'            => false,
				'show_in_rest'      => false,
				'sanitize_callback' => array( self::class, 'sanitize_ref' ),
			)
		);
	}

	/**
	 * Keep an attachment reference only when it is page:ID or award_category:ID.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_ref( $value ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		return 1 === preg_match( '/^(page|award_category):[0-9]+$/', $text ) ? $text : '';
	}
}
