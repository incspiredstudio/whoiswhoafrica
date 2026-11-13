<?php
/**
 * Commerce module bootstrap. Registers this module's hooks in one place; domain classes arrive in later issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Platform\ModuleInterface;

/**
 * Commerce module.
 */
final class Module implements ModuleInterface {

	/**
	 * Module name.
	 */
	public function name(): string {
		return 'commerce';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		SponsorPostType::hooks();
		PlacementJob::register();
		DashboardTiles::hooks();
		SponsoredStoriesScreen::hooks();
		Admin\CustomersScreen::hooks();
	}
}
