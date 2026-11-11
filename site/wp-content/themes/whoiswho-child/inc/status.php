<?php
/**
 * Reports whether the child is the active theme and extends the parent. Used by tests and the deploy script.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

/**
 * Child theme status.
 *
 * @return array{child_active: bool, parent_present: bool, extends_parent: bool}
 */
function wiw_child_theme_status(): array {
	$child  = wp_get_theme( 'whoiswho-child' );
	$parent = wp_get_theme( 'whoiswho' );

	return array(
		'child_active'   => 'whoiswho-child' === get_stylesheet(),
		'parent_present' => $parent->exists(),
		'extends_parent' => $child->exists() && 'whoiswho' === $child->get_template(),
	);
}
