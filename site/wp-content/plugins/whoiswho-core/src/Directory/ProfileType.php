<?php
/**
 * Registers the profile post type (REQ-003, REQ-004, REQ-005); the non-public hidden status comes from the guard mu-plugin.
 *
 * The profile type is never public in the WordPress sense: no archive, no feed, no REST, no core search and no core permalink.
 * Public URLs are served by the plugin's own rewrite and read model, so core can never expose a profile by accident. Every
 * non-public state is stored as the one status wiw_hidden; the real state lives in the state table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

/**
 * Profile type and status registration.
 */
final class ProfileType {

	/**
	 * Post type.
	 */
	public const POST_TYPE = 'wiw_profile';

	/**
	 * Post status for every non-public state.
	 */
	public const STATUS_HIDDEN = 'wiw_hidden';

	/**
	 * Register the type. Hooked to init.
	 */
	public function register(): void {
		// The wiw_hidden status itself is registered by the guard mu-plugin (init, priority 0), so it exists even if this plugin is off.
		if ( class_exists( '\WhoIsWho\Guard\Guard' ) ) {
			\WhoIsWho\Guard\Guard::guard_post_type( self::POST_TYPE );
		}

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => _x( 'Profiles', 'post type general name', 'whoiswho' ),
					'singular_name' => _x( 'Profile', 'post type singular name', 'whoiswho' ),
					'add_new_item'  => __( 'Add profile', 'whoiswho' ),
					'edit_item'     => __( 'Edit profile', 'whoiswho' ),
					'search_items'  => __( 'Search profiles', 'whoiswho' ),
					'not_found'     => __( 'No profiles found.', 'whoiswho' ),
					'all_items'     => __( 'Profiles', 'whoiswho' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'delete_with_user'    => false,
				'hierarchical'        => false,
				'menu_icon'           => 'dashicons-id-alt',
				'supports'            => array( 'title' ),
				'capability_type'     => array( 'wiw_profile', 'wiw_profiles' ),
				'map_meta_cap'        => true,
			)
		);
	}
}
