<?php
/**
 * Guard bootstrap: registers the hidden post status and the registry of guarded post types.
 * It uses only generic WordPress hooks and never calls the core plugin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard;

/**
 * The guard.
 */
final class Guard {

	/**
	 * The non-public post status for records that must never appear publicly.
	 */
	public const STATUS_HIDDEN = 'wiw_hidden';

	/**
	 * Guarded post types, added by other code (the profile type is added in M1).
	 *
	 * @var string[]
	 */
	private static array $guarded_types = array();

	/**
	 * Attach hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_status' ), 0 );
		Hardening\Hardening::init();
		Exposure\Exposure::init();
		Login\Login::init();
		Headers\Headers::init();
		Uploads\Uploads::init();
		Capabilities\Capabilities::init();
		Status\StatusWriteGuard::init();
	}

	/**
	 * Register wiw_hidden as a non-public status.
	 */
	public static function register_status(): void {
		register_post_status(
			self::STATUS_HIDDEN,
			array(
				'label'                     => _x( 'Hidden', 'post status', 'whoiswho' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'private'                   => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of hidden records. */
				'label_count'               => _n_noop( 'Hidden <span class="count">(%s)</span>', 'Hidden <span class="count">(%s)</span>', 'whoiswho' ),
			)
		);
	}

	/**
	 * Add a post type to the guarded set.
	 *
	 * @param string $post_type Post type slug.
	 */
	public static function guard_post_type( string $post_type ): void {
		if ( ! in_array( $post_type, self::$guarded_types, true ) ) {
			self::$guarded_types[] = $post_type;
		}
	}

	/**
	 * The guarded post types.
	 *
	 * @return string[]
	 */
	public static function guarded_post_types(): array {
		return self::$guarded_types;
	}
}
