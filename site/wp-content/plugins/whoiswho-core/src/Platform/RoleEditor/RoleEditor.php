<?php
/**
 * The per-user capability and role screen (REQ-244, REQ-235): the User Role Editor plugin from wordpress.org, pinned through
 * composer and loaded by this plugin (so it cannot be deactivated from wp-admin). Every capability it checks of its own (ure_*)
 * is decided here from one named capability, wiw_manage_capabilities, which only the Administrator holds. Nobody else can see
 * or open its screens or call its actions, whatever is stored for their role. It is kept from writing role definitions by
 * itself, capabilities on the fixed (Administrator-only) list are hidden from its screens, and the guard plugin still refuses
 * such a grant if one is attempted. Every grant, revoke and role edit it makes is audited by Capabilities\CapabilityAudit.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\RoleEditor;

use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;

/**
 * Role editor setup.
 */
final class RoleEditor {

	public const GATE = 'wiw_manage_capabilities';

	/**
	 * Catalogue.
	 *
	 * @var CapabilityCatalogue
	 */
	private CapabilityCatalogue $catalogue;

	/**
	 * Constructor.
	 *
	 * @param CapabilityCatalogue $catalogue Capability catalogue.
	 */
	public function __construct( CapabilityCatalogue $catalogue ) {
		$this->catalogue = $catalogue;
	}

	/**
	 * Load the plugin from the plugins folder. Called from the core plugin file, before plugins_loaded.
	 *
	 * @param string $plugins_dir Plugins folder.
	 */
	public static function load( string $plugins_dir ): void {
		$file = rtrim( $plugins_dir, '/\\' ) . '/user-role-editor/user-role-editor.php';
		if ( ! class_exists( '\URE_Core', false ) && is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Attach hooks.
	 */
	public static function register(): void {
		$editor = new self( new CapabilityCatalogue() );
		add_filter( 'user_has_cap', array( $editor, 'gate' ), 100 );
		add_filter( 'ure_grant_all_caps_to_admin', '__return_false' );
		add_filter( 'ure_full_capabilites', array( $editor, 'hide_fixed_from_full_list' ) );
		add_filter( 'ure_built_in_wp_caps', array( $editor, 'hide_fixed_from_groups' ) );
	}

	/**
	 * Whether a capability belongs to the plugin.
	 *
	 * @param string $cap Capability.
	 */
	public static function is_plugin_capability( string $cap ): bool {
		return 0 === strpos( $cap, 'ure_' );
	}

	/**
	 * Decide the plugin's own capabilities from the one gate, ignoring anything stored for a role or user.
	 *
	 * @param mixed $allcaps Capabilities the user holds.
	 * @return array<string, bool>
	 */
	public function gate( $allcaps ): array {
		$allcaps = is_array( $allcaps ) ? $allcaps : array();
		$allowed = ! empty( $allcaps[ self::GATE ] );
		foreach ( array_keys( $allcaps ) as $cap ) {
			if ( self::is_plugin_capability( (string) $cap ) ) {
				$allcaps[ $cap ] = $allowed;
			}
		}
		foreach ( array( 'ure_edit_roles', 'ure_create_roles', 'ure_delete_roles', 'ure_create_capabilities', 'ure_delete_capabilities', 'ure_manage_options', 'ure_reset_roles' ) as $cap ) {
			$allcaps[ $cap ] = $allowed;
		}
		return $allcaps;
	}

	/**
	 * Remove fixed capabilities from the plugin's full list.
	 *
	 * @param mixed $caps_list Full list, keyed by capability.
	 * @return mixed
	 */
	public function hide_fixed_from_full_list( $caps_list ) {
		if ( ! is_array( $caps_list ) ) {
			return $caps_list;
		}
		return array_diff_key( $caps_list, array_flip( $this->catalogue->fixed() ) );
	}

	/**
	 * Remove fixed capabilities from the plugin's built-in capability groups.
	 *
	 * @param mixed $caps Built-in capabilities, keyed by capability.
	 * @return mixed
	 */
	public function hide_fixed_from_groups( $caps ) {
		if ( ! is_array( $caps ) ) {
			return $caps;
		}
		return array_diff_key( $caps, array_flip( $this->catalogue->fixed() ) );
	}
}
