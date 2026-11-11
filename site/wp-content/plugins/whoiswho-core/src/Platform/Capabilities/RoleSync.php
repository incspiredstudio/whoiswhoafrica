<?php
/**
 * Creates the staff roles and adds new capabilities to them. It only ever adds capabilities it has not synced before, so it never
 * removes a grant an Administrator added, and never re-adds a default an Administrator deliberately took away.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Capabilities;

/**
 * Role and capability sync.
 */
final class RoleSync {

	public const OPTION_VERSION = 'wiw_caps_version';
	public const OPTION_SYNCED  = 'wiw_caps_synced';

	/**
	 * Built-in roles that are removed on activation (REQ-235).
	 */
	public const REMOVED_ROLES = array( 'subscriber', 'contributor', 'author', 'editor' );

	/**
	 * The catalogue.
	 *
	 * @var CapabilityCatalogue
	 */
	private CapabilityCatalogue $catalogue;

	/**
	 * Constructor.
	 *
	 * @param CapabilityCatalogue $catalogue Catalogue.
	 */
	public function __construct( CapabilityCatalogue $catalogue ) {
		$this->catalogue = $catalogue;
	}

	/**
	 * Activation: remove the built-in roles, close registration, then sync.
	 */
	public function activate(): void {
		foreach ( self::REMOVED_ROLES as $role ) {
			remove_role( $role );
		}
		update_option( 'default_role', '' );
		update_option( 'users_can_register', 0 );
		$this->sync();
	}

	/**
	 * Run the sync when the catalogue version changed. Cheap: one option read.
	 */
	public function maybe_sync(): void {
		if ( (int) get_option( self::OPTION_VERSION, 0 ) < $this->catalogue->version() ) {
			$this->sync();
		}
	}

	/**
	 * Create missing roles and grant capabilities not synced before.
	 */
	public function sync(): void {
		foreach ( $this->catalogue->roles() as $name => $def ) {
			if ( null === get_role( $name ) ) {
				add_role( $name, $def['label'], array() );
			}
		}

		$synced = get_option( self::OPTION_SYNCED, array() );
		$synced = is_array( $synced ) ? $synced : array();
		$new    = array_values( array_diff( $this->catalogue->all(), $synced ) );

		$targets = array( 'administrator' => 'administrator' );
		foreach ( $this->catalogue->roles() as $name => $def ) {
			$targets[ $name ] = $def['bundle'];
		}

		foreach ( $targets as $role_name => $bundle ) {
			$role = get_role( $role_name );
			if ( null === $role ) {
				continue;
			}
			$defaults = $this->catalogue->for_bundle( $bundle );
			foreach ( $new as $cap ) {
				if ( in_array( $cap, $defaults, true ) ) {
					$role->add_cap( $cap );
				}
			}
		}

		update_option( self::OPTION_SYNCED, $this->catalogue->all(), false );
		update_option( self::OPTION_VERSION, $this->catalogue->version(), false );
	}
}
