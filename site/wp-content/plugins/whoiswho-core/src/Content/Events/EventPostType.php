<?php
/**
 * The wiw_event post type. Every capability maps to wiw_manage_events (REQ-262, REQ-244). No sponsor, Featured or sticky.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Events;

/**
 * Event post type.
 */
final class EventPostType {

	public const TYPE = 'wiw_event';
	public const CAP  = 'wiw_manage_events';

	/**
	 * Hook registration.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register' ), 5 );
		add_action( 'init', array( self::class, 'attach_industry' ), 30 );
	}

	/**
	 * Register the type.
	 */
	public static function register(): void {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Events', 'whoiswho' ),
					'singular_name' => __( 'Event', 'whoiswho' ),
					'add_new_item'  => __( 'Add event', 'whoiswho' ),
					'edit_item'     => __( 'Edit event', 'whoiswho' ),
				),
				'public'              => true,
				'show_in_rest'        => false,
				'has_archive'         => 'events',
				'exclude_from_search' => true,
				'menu_icon'           => 'dashicons-calendar-alt',
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'rewrite'             => array(
					'slug'       => 'events',
					'with_front' => false,
					'feeds'      => false,
				),
				'capability_type'     => array( 'wiw_event', 'wiw_events' ),
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
	}

	/**
	 * Give events the industry taxonomy, when the Directory module has registered it.
	 */
	public static function attach_industry(): void {
		if ( taxonomy_exists( 'wiw_industry' ) ) {
			register_taxonomy_for_object_type( 'wiw_industry', self::TYPE );
		}
	}
}
