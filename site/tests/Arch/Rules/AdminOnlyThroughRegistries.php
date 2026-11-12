<?php
/**
 * Every admin screen is registered through ScreenRegistry and every admin action through ActionGuard, each under a named
 * capability (REQ-240). Code that calls WordPress's own menu functions, or hooks admin_post_ and wp_ajax_ directly, would skip
 * the deny-by-default check, so it is a violation outside the two registries themselves.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use PhpParser\Node;
use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * Registries only.
 */
final class AdminOnlyThroughRegistries implements Rule {

	/**
	 * WordPress menu functions that add a screen.
	 */
	public const MENU_FUNCTIONS = array( 'add_menu_page', 'add_submenu_page', 'add_options_page', 'add_management_page', 'add_users_page', 'add_posts_page', 'add_pages_page', 'add_tools_page', 'add_dashboard_page', 'add_theme_page', 'add_plugins_page', 'add_comments_page', 'add_utility_page' );

	/**
	 * Files that are the registries.
	 */
	public const ALLOWED = array( 'Platform/Admin/ScreenRegistry.php', 'Platform/Admin/ActionGuard.php' );

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			if ( in_array( $path, self::ALLOWED, true ) ) {
				continue;
			}
			foreach ( $sources->calls( $path ) as $call ) {
				if ( in_array( $call, self::MENU_FUNCTIONS, true ) ) {
					$bad[] = "$path calls $call; register the screen with ScreenRegistry";
				}
			}
			foreach ( $sources->nodes( $path, Node\Expr\FuncCall::class ) as $node ) {
				if ( ! $node->name instanceof Node\Name || 'add_action' !== $node->name->toString() ) {
					continue;
				}
				$first = $node->args[0]->value ?? null;
				if ( $first instanceof Node\Scalar\String_ && 1 === preg_match( '/^(admin_post_|wp_ajax_)/', $first->value ) ) {
					$bad[] = "$path hooks {$first->value}; register the action with ActionGuard";
				}
				if ( $first instanceof Node\Expr\BinaryOp\Concat && $first->left instanceof Node\Scalar\String_ && 1 === preg_match( '/^(admin_post_|wp_ajax_)/', $first->left->value ) ) {
					$bad[] = "$path hooks {$first->left->value}...; register the action with ActionGuard";
				}
			}
		}
		return array_values( array_unique( $bad ) );
	}
}
