<?php
/**
 * Directory module bootstrap. Registers this module's hooks in one place; domain classes arrive in later issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

use WhoIsWho\Core\Platform\ModuleInterface;

/**
 * Directory module.
 */
final class Module implements ModuleInterface {

	/**
	 * Module name.
	 */
	public function name(): string {
		return 'directory';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
	}
}
