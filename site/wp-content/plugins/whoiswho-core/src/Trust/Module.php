<?php
/**
 * Trust module bootstrap. Registers this module's hooks in one place; domain classes arrive in later issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Platform\ModuleInterface;

/**
 * Trust module.
 */
final class Module implements ModuleInterface {

	/**
	 * Module name.
	 */
	public function name(): string {
		return 'trust';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		ClaimIntake::register();
		Admin\ClaimsScreen::hooks();
	}
}
