<?php
/**
 * Review module bootstrap. Registers this module's hooks in one place; domain classes arrive in later issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review;

use WhoIsWho\Core\Platform\ModuleInterface;

/**
 * Review module.
 */
final class Module implements ModuleInterface {

	/**
	 * Module name.
	 */
	public function name(): string {
		return 'review';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		( new StatusGuard() )->register();
		Admin\ReviewQueueScreen::hooks();
		Bulk\BulkUnpublish::for_site()->register();
		Admin\BulkUnpublishPanel::hooks();
		Admin\DuplicatesScreen::hooks();
	}
}
