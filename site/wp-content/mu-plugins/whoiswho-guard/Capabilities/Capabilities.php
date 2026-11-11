<?php
/**
 * Capability rules that must hold even if the core plugin fails: the fixed (Administrator-only) list is never granted to anyone
 * else, a user cannot change their own role or capabilities, and a user holds exactly one role. The fixed list is duplicated
 * here on purpose; a test compares it with the plugin's catalogue so the two cannot drift.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Capabilities;

/**
 * Capability guard.
 */
final class Capabilities {

	/**
	 * Capabilities that only an Administrator can ever hold, whatever is stored for the user or the role.
	 */
	public const FIXED = array(
		'list_users',
		'create_users',
		'edit_users',
		'promote_users',
		'delete_users',
		'manage_options',
		'install_plugins',
		'activate_plugins',
		'update_plugins',
		'install_themes',
		'switch_themes',
		'edit_theme_options',
		'wiw_manage_register',
		'wiw_toggle_nominations',
		'wiw_manage_capabilities',
	);

	/**
	 * Attach hooks.
	 */
	public static function init(): void {
		add_filter( 'user_has_cap', array( self::class, 'strip_fixed_capabilities' ), 99, 4 );
		add_filter( 'update_user_metadata', array( self::class, 'guard_capability_meta' ), 1, 4 );
		add_filter( 'add_user_metadata', array( self::class, 'guard_capability_meta' ), 1, 4 );
		add_filter( 'map_meta_cap', array( self::class, 'map_meta_cap' ), 99, 4 );
	}

	/**
	 * Take every fixed capability away from anyone who is not an Administrator, even if a grant is stored.
	 *
	 * @param array<string, bool> $allcaps Capabilities the user holds.
	 * @param array<int, string>  $caps    Capabilities being checked.
	 * @param array<int, mixed>   $args    Arguments.
	 * @param \WP_User            $user    The user.
	 * @return array<string, bool>
	 */
	public static function strip_fixed_capabilities( $allcaps, $caps, $args, $user ) {
		$roles = (array) $user->roles;
		if ( in_array( 'administrator', $roles, true ) ) {
			return $allcaps;
		}
		foreach ( self::FIXED as $cap ) {
			if ( isset( $allcaps[ $cap ] ) ) {
				$allcaps[ $cap ] = false;
			}
		}
		return $allcaps;
	}

	/**
	 * Whether this meta key holds a user's roles and capabilities.
	 *
	 * @param string $meta_key Meta key.
	 */
	private static function is_capability_meta( string $meta_key ): bool {
		global $wpdb;
		return $wpdb->get_blog_prefix() . 'capabilities' === $meta_key;
	}

	/**
	 * Refuse a write to a user's roles and capabilities when it would give them a second role, or when the user is changing
	 * their own. Setup, WP-CLI and code with no signed-in user are not affected.
	 *
	 * @param mixed  $check      Short-circuit value.
	 * @param int    $object_id  User id.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New value.
	 * @return mixed Null to allow, false to refuse.
	 */
	public static function guard_capability_meta( $check, $object_id, $meta_key, $meta_value ) {
		if ( ! self::is_capability_meta( (string) $meta_key ) || wp_installing() || ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) ) {
			return $check;
		}
		$actor = get_current_user_id();
		if ( $actor > 0 && (int) $object_id === $actor ) {
			return false;
			// Nobody changes their own role or capabilities.
		}
		if ( is_array( $meta_value ) && self::role_count( $meta_value ) > 1 ) {
			return false;
			// One role per user.
		}
		return $check;
	}

	/**
	 * How many roles a capabilities array holds.
	 *
	 * @param array<string, mixed> $caps Capabilities meta value.
	 */
	public static function role_count( array $caps ): int {
		$roles = array_keys( wp_roles()->get_names() );
		$count = 0;
		foreach ( $caps as $name => $granted ) {
			if ( $granted && in_array( $name, $roles, true ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Extension point for meta capabilities that need the guard's view (the single-profile emergency unpublish is mapped in M1).
	 *
	 * @param array<int, string> $caps    Required capabilities.
	 * @param string             $cap     Meta capability.
	 * @param int                $user_id User.
	 * @param array<int, mixed>  $args    Arguments.
	 * @return array<int, string>
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		return (array) apply_filters( 'wiw_guard_map_meta_cap', $caps, $cap, $user_id, $args );
	}
}
