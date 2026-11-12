<?php
/**
 * Import module bootstrap. Registers this module's hooks in one place; domain classes arrive in later issues.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import;

use WhoIsWho\Core\Platform\ModuleInterface;

/**
 * Import module.
 */
final class Module implements ModuleInterface {

	/**
	 * Module name.
	 */
	public function name(): string {
		return 'import';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		( new Apply\ApplyJob() )->register();
		Result\ImportResult::hooks();
	}
}
