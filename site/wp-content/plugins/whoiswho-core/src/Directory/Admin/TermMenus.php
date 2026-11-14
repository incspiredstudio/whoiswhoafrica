<?php
/**
 * Menu entries for the classification lists (REQ-009, REQ-240): Directory > Industries, Directory > Countries & cities and
 * Directory > Organisation types, each opening the native term screen of its list for profiles
 * (edit-tags.php?taxonomy=…&post_type=wiw_profile). While a term screen of these lists is open, the Directory group is open and
 * the list's own item is current (see ProfileType::menu_current). Stories and events share the industry list, so WordPress also
 * lists it under Posts and Events: those entries stay, labelled as the shared list so nobody takes them for a separate one.
 *
 * The items need manage_terms (Editors and Administrators), the capability the taxonomies use; the term screens check it again.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;

/**
 * Directory menu entries for the term lists.
 */
final class TermMenus {

	public const CAPABILITY = 'manage_terms';

	/**
	 * Taxonomy => menu label, in menu order.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			Taxonomies::INDUSTRY => __( 'Industries', 'whoiswho' ),
			Taxonomies::PLACE    => __( 'Countries & cities', 'whoiswho' ),
			Taxonomies::ORG_TYPE => __( 'Organisation types', 'whoiswho' ),
		);
	}

	/**
	 * The term screen address of a list, for profiles.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public static function url( string $taxonomy ): string {
		return 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . ProfileType::POST_TYPE;
	}

	/**
	 * Register the links in the admin, and the shared-list labels under Posts and Events.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				self::register();
				ScreenRegistry::shared()->hook();
			},
			1
		);
		add_action( 'admin_menu', array( self::class, 'label_shared_lists' ), 1000 );
	}

	/**
	 * Register the three links in the Directory group.
	 *
	 * @param ScreenRegistry|null $registry Registry; the shared one by default.
	 */
	public static function register( ?ScreenRegistry $registry = null ): void {
		$registry = $registry ?? ScreenRegistry::shared();
		$links    = $registry->links();
		foreach ( self::labels() as $taxonomy => $label ) {
			if ( ! isset( $links[ self::url( $taxonomy ) ] ) ) {
				$registry->register_link( self::url( $taxonomy ), $label, self::CAPABILITY, 'directory' );
			}
		}
	}

	/**
	 * The industry list under Posts and Events is the same list as Directory > Industries: say so in its label.
	 */
	public static function label_shared_lists(): void {
		global $submenu;
		if ( ! is_array( $submenu ) ) {
			return;
		}
		$label = __( 'Industries (shared with profiles)', 'whoiswho' );
		foreach ( array( 'edit.php', 'edit.php?post_type=' . EventPostType::TYPE ) as $parent ) {
			if ( ! isset( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
				continue;
			}
			foreach ( $submenu[ $parent ] as $index => $item ) {
				if ( isset( $item[2] ) && 0 === strpos( (string) $item[2], 'edit-tags.php?taxonomy=' . Taxonomies::INDUSTRY ) ) {
					$submenu[ $parent ][ $index ][0] = $label; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- relabelling one menu item WordPress added.
				}
			}
		}
	}
}
